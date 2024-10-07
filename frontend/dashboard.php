<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require __DIR__ . '/../config.php';
require __DIR__ . '/../backend/database.php';
require __DIR__ . '/../classes/Application.php';

error_log("Dashboard loaded.");

$csrf_token = generate_csrf_token();

try {
    error_log("Attempting to connect to database");
    $db = @db_connect();
    error_log("Database connection successful.");

    $user_id = $_SESSION['user_id'];
    $user = User::getById($db, $user_id);

    if (!$user) {
        error_log("Failed to retrieve user with ID: $user_id");
        throw new Exception('Error retrieving user data');
    }
} catch (Exception $e) {
    error_log("Error in dashboard.php.");
    die('An error occurred while processing your request. Please try again later.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkRateLimit('dashboard_rate_limit', 10, 60)) {
        $response = ['success' => false, 'message' => 'Rate limit exceeded. Please try again later.'];
        echo json_encode($response);
        exit;
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response = ['success' => false, 'message' => 'Invalid CSRF token'];
        echo json_encode($response);
        exit;
    }

    $response = ['success' => false, 'message' => ''];

    header('Content-Type: application/json');

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $jobTitle = filter_input(INPUT_POST, 'jobTitle', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $company = filter_input(INPUT_POST, 'company', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $appliedDate = filter_input(INPUT_POST, 'appliedDate', FILTER_CALLBACK, ['options' => function($date) {
                    return (DateTime::createFromFormat('Y-m-d', $date) !== false) ? $date : false;
                }]);
                $applicationLink = filter_input(INPUT_POST, 'applicationLink', FILTER_VALIDATE_URL);

                if (!$jobTitle || !$company || !$status || !$appliedDate) {
                    $response['message'] = 'Invalid input data';
                    echo json_encode($response);
                    exit;
                }

                $newId = Application::create(
                    $db,
                    $user_id,
                    $jobTitle,
                    $company,
                    $status,
                    $appliedDate,
                    $applicationLink
                );
                $response['success'] = $newId !== false;
                $response['message'] = $response['success'] ? 'Operation completed successfully' : 'An error occurred';
                break;
            case 'update':
                $applicationId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $newStatus = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                if ($applicationId && $newStatus) {
                    $application = Application::getById($db, $applicationId);
                    if ($application && $application->getUserId() == $user_id) {
                        $application->setStatus($newStatus);
                        $response['success'] = $application->update($db);
                        $response['message'] = $response['success'] ? 'Operation completed successfully' : 'An error occurred';
                    } else {
                        $response['message'] = 'Invalid application ID';
                    }
                } else {
                    $response['message'] = 'Invalid input data';
                }
                break;
            case 'delete':
                $applicationId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
                    $response['message'] = 'Invalid CSRF token';
                } elseif ($applicationId) {
                    $application = Application::getById($db, $applicationId);
                    if ($application && $application->getUserId() == $user_id) {
                        $deleteResult = Application::delete($db, $applicationId);
                        $response['success'] = $deleteResult;
                        $response['message'] = $response['success'] ? 'Operation completed successfully' : 'An error occurred';
                    } else {
                        $response['message'] = 'Invalid application ID';
                    }
                } else {
                    $response['message'] = 'Invalid input data';
                }
                break;
            case 'deleteAll':
                $applications = $user->getApplications($db);
                $allDeleted = true;
                foreach ($applications as $application) {
                    if (!Application::delete($db, $application->getId())) {
                        $allDeleted = false;
                        break;
                    }
                }
                $response['success'] = $allDeleted;
                $response['message'] = $response['success'] ? 'Operation completed successfully' : 'An error occurred';
                break;
            case 'getMetrics':
                $metrics = calculateMetrics($db, $user_id);
                if ($metrics === null) {
                    $response['success'] = false;
                    $response['message'] = 'Error calculating metrics';
                } else {
                    $response['success'] = true;
                    $response['metrics'] = $metrics;
                }
                break;
        }
    } else {
        $response['message'] = 'No action specified';
    }

    echo json_encode($response);
    exit;
}

$allowed_sort_columns = ['applied_date', 'last_updated', 'job_title', 'company', 'status'];
$allowed_sort_orders = ['asc', 'desc'];
$sort_by = isset($_GET['sort']) && in_array(strtolower($_GET['sort']), $allowed_sort_columns) ? strtolower($_GET['sort']) : 'applied_date';
$sort_order = isset($_GET['order']) && in_array(strtolower($_GET['order']), $allowed_sort_orders) ? strtolower($_GET['order']) : 'desc';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$status_filter = isset($_GET['statuses']) && !empty($_GET['statuses']) ? explode(',', $_GET['statuses']) : [];

$total_applications = $user->getTotalApplications($db, $status_filter);

$total_pages = ceil($total_applications / $per_page);

$applications = $user->getApplications($db, $sort_by, $sort_order, $per_page, $offset, $status_filter);

$statusOptions = [
    'not_yet_applied' => 'Not Yet Applied',
    'applied' => 'Applied',
    'phone_screen' => 'Phone Screen',
    'interviewing' => 'Interviewing',
    'offer_received' => 'Offer Received',
    'offer_accepted' => 'Offer Accepted',
    'offer_rejected' => 'Offer Rejected',
    'rejected' => 'Rejected',
    'ghosted' => 'Ghosted'
];

function generateStatusSelect($currentStatus, $options) {
    $select = '<select name="status">';
    foreach ($options as $value => $label) {
        $selected = $currentStatus === $value ? 'selected' : '';
        $select .= "<option value=\"$value\" $selected>$label</option>";
    }
    $select .= '</select>';
    return $select;
}

function calculateMetrics($db, $user_id) {
    global $statusOptions;
    $applications = Application::getAllForUser($db, $user_id);
    if (!is_array($applications) || empty($applications)) {
        return null;
    }
    $totalApplications = count($applications);

    if ($totalApplications === 0) {
        return [
            'applicationRate' => 0,
            'totalApplications' => 0,
            'totalDays' => 0,
            'responseRate' => 0,
            'responsesReceived' => 0,
            'averageResponseTime' => 0,
            'responseTimeCounts' => [],
            'weekdayCounts' => array_fill(0, 7, ['submitted' => 0, 'responses' => 0])
        ];
    }

    $firstApplicationDate = min(array_map(function($app) {
        return strtotime($app->getAppliedDate());
    }, $applications));

    $lastApplicationDate = max(array_map(function($app) {
        return strtotime($app->getAppliedDate());
    }, $applications));

    $totalDays = max(1, ceil(($lastApplicationDate - $firstApplicationDate) / (60 * 60 * 24)) + 1);
    $applicationRate = round($totalApplications / $totalDays, 2);

    $responsesReceived = 0;
    $totalResponseTime = 0;
    $responseTimeCounts = array_fill(0, 15, 0);
    $weekdayCounts = array_fill(0, 7, ['submitted' => 0, 'responses' => 0]);

    foreach ($applications as $app) {
        $status = $app->getStatus();
        $appliedDate = strtotime($app->getAppliedDate());
        $weekday = date('N', $appliedDate) - 1;
        $weekdayCounts[$weekday]['submitted']++;

        if (!in_array($status, ['not_yet_applied', 'applied', 'ghosted'])) {
            $responsesReceived++;
            $responseTime = $app->getResponseTime();
            if ($responseTime !== null) {
                $totalResponseTime += $responseTime;
                $responseTimeIndex = min(14, $responseTime);
                $responseTimeCounts[$responseTimeIndex]++;
            }

            $responseWeekday = date('N', strtotime($app->getLastUpdated())) - 1;
            $weekdayCounts[$responseWeekday]['responses']++;
        }
    }

    $responseRate = round(($responsesReceived / $totalApplications) * 100, 2);
    $averageResponseTime = ($totalResponseTime > 0 && $responsesReceived > 0)  ? round($totalResponseTime / $responsesReceived) : 0;

    return [
        'applicationRate' => $applicationRate,
        'totalApplications' => $totalApplications,
        'totalDays' => $totalDays,
        'responseRate' => $responseRate,
        'responsesReceived' => $responsesReceived,
        'averageResponseTime' => $averageResponseTime,
        'responseTimeCounts' => $responseTimeCounts,
        'weekdayCounts' => $weekdayCounts
    ];
}

ob_end_clean();
?>

<!--<h1>Dashboard</h1>-->
<br>
<div class="action-buttons">
    <button id="addApplicationBtn" class="action-btn add-application-btn">
        <img src="/icons/plus.svg" alt="Add" width="24" height="24">
        <span>Add New Application</span>
    </button>
    <button id="deleteAllApplicationsBtn" class="action-btn delete-all-btn">
        <img src="/icons/trashWhite.svg" alt="Delete All" width="24" height="24">
        <span>Delete All Applications</span>
    </button>
    <button id="metricsBtn" class="action-btn metrics-btn">
        <img src="/icons/metrics.svg" alt="Metrics" width="24" height="24">
        <span>Metrics</span>
    </button><!--
    <button id="settingsBtn" class="action-btn settings-btn">
        <img src="/icons/settings.svg" alt="Settings" width="24" height="24">
        <span>Settings</span>
    </button>-->
</div>

<div class="pagination-info">
    <?php
    $start = $offset + 1;
    $end = min($offset + $per_page, $total_applications);
    echo "{$start} - {$end} of {$total_applications}";
    ?>
</div>

<table id="applicationTable" data-csrf-token="<?php echo $csrf_token; ?>">
    <thead>
        <tr>
            <th>Job Title</th>
            <th>Company</th>
            <th class="sortable" data-sort="applied_date"><img src="/icons/arrow-down.svg" alt="Sort" class="sort-icon"> Date Added</th>
            <th class="sortable" data-sort="last_updated"><img src="/icons/arrow-down.svg" alt="Sort" class="sort-icon"> Last Updated</th>
            <th class="status-header">
                <img src="/icons/filter.svg" alt="Filter" class="filter-icon" id="statusFilterIcon">
                Status
                <div class="status-filter-modal" id="statusFilterModal">
                    <div class="status-filter-content">
                        <label><input type="checkbox" id="selectAllStatuses"> Select All</label>
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <label><input type="checkbox" name="statusFilter" value="<?php echo $value; ?>"> <?php echo $label; ?></label>
                        <?php endforeach; ?>
                        <div class="filter-buttons">
                            <button id="applyFilter">Apply</button>
                            <button id="cancelFilter">Cancel</button>
                        </div>
                    </div>
                </div>
            </th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($applications as $application): ?>
            <tr data-id="<?php echo $application->getId(); ?>">
                <td><?php echo htmlspecialchars($application->getJobTitle()); ?></td>
                <td><?php echo htmlspecialchars($application->getCompany()); ?></td>
                <td data-full-date="<?php echo htmlspecialchars($application->getAppliedDate()); ?>"><?php echo htmlspecialchars(date('Y-m-d', strtotime($application->getAppliedDate()))); ?></td>
                <td data-full-date="<?php echo htmlspecialchars($application->getLastUpdated()); ?>"><?php echo htmlspecialchars(date('Y-m-d', strtotime($application->getLastUpdated()))); ?></td>
                <td><?php echo generateStatusSelect(htmlspecialchars($application->getStatus()), $statusOptions); ?></td>
                <td class="action-buttons">
                    <?php if (!empty($application->getUrl())): ?>
                        <div class="action-button-wrapper">
                            <a href="<?php echo htmlspecialchars($application->getUrl()); ?>" target="_blank" rel="noopener noreferrer" title="Application Link" class="link-icon">
                                <img src="/icons/link.svg" alt="Application Link" width="24" height="24">
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="action-button-wrapper">
                            <div class="placeholder"></div>
                        </div>
                    <?php endif; ?>
                    <!--<div class="action-button-wrapper">
                        <div class="sparkle-application" data-id="<?php echo $application->getId(); ?>" title="Sparkle Application">
                            <img src="/icons/sparkles.svg" alt="Sparkle Application" width="24" height="24">
                        </div>
                    </div>-->
                    <div class="action-button-wrapper">
                        <div class="update-status" data-id="<?php echo $application->getId(); ?>" title="Update Status">
                            <img src="/icons/save.svg" alt="Update Status" width="24" height="24">
                        </div>
                    </div>
                    <div class="action-button-wrapper">
                        <div class="delete-application" data-id="<?php echo $application->getId(); ?>" title="Delete Application">
                            <img src="/icons/trash.svg" alt="Delete Application" width="24" height="24">
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="pagination-buttons">
    <?php
    $queryParams = $_GET;
    if ($page > 1) {
        $queryParams['page'] = $page - 1;
        $prevLink = '?' . http_build_query($queryParams);
        echo "<a href=\"$prevLink\" class=\"pagination-btn prev-btn\">&laquo; Previous</a>";
    }
    if ($page < $total_pages) {
        $queryParams['page'] = $page + 1;
        $nextLink = '?' . http_build_query($queryParams);
        echo "<a href=\"$nextLink\" class=\"pagination-btn next-btn\">Next &raquo;</a>";
    }
    ?>
</div>

<!-- Add New Application Modal -->
<div id="addApplicationModal" class="modal">
    <div class="modal-content">
        <h2>Add New Application</h2>
        <form id="addApplicationForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <input type="text" id="jobTitle" name="jobTitle" placeholder="Job Title" required>
            
            <input type="text" id="company" name="company" placeholder="Company Name" required>
            
            <input type="date" id="appliedDate" name="appliedDate" required>
            
            <?php echo generateStatusSelect('not_yet_applied', $statusOptions); ?>
            
            <input type="url" id="applicationLink" name="applicationLink" placeholder="Application URL">
            
            <div class="modal-buttons">
                <button type="submit">Save</button>
                <button type="button" class="cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete All Applications Modal -->
<div id="deleteAllModal" class="modal">
    <div class="modal-content">
        <h2>Delete All Applications</h2>
        <p>Are you sure you want to delete all applications? This action cannot be undone.</p>
        <div class="modal-buttons">
            <button id="confirmDeleteAll">Delete All</button>
            <button class="cancel">Cancel</button>
        </div>
    </div>
</div>

<!-- Add this after the deleteAllModal -->
<div id="deleteApplicationModal" class="modal">
    <div class="modal-content">
        <h2>Delete Application</h2>
        <p>Are you sure you want to delete this application? This action cannot be undone.</p>
        <div class="modal-buttons">
            <button id="confirmDeleteApplication">Delete</button>
            <button class="cancel">Cancel</button>
        </div>
    </div>
</div>

<!-- Metrics Modal -->
<div id="metricsModal" class="modal">
    <div class="modal-content">
        <h2>Application Metrics</h2>
        <div id="metricsContent">
            <p id="applicationRate"></p>
            <p id="responseRate"></p>
            <p id="responseTime"></p>
            <div class="chart-container">
                <canvas id="responseTimeChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="weekdayChart"></canvas>
            </div>
        </div>
        <div class="modal-buttons">
            <button class="cancel">Close</button>
        </div>
    </div>
</div>

<script nonce="<?php echo $nonce; ?>">
document.addEventListener('DOMContentLoaded', function() {
    const addApplicationForm = document.getElementById('addApplicationForm');
    const addApplicationModal = document.getElementById('addApplicationModal');
    const deleteAllModal = document.getElementById('deleteAllModal');
    const deleteApplicationModal = document.getElementById('deleteApplicationModal');
    const applicationTable = document.getElementById('applicationTable');
    const csrfToken = applicationTable.dataset.csrfToken;
    let applicationToDelete = null;

    function showModal(modal) {
        modal.style.display = 'flex';
    }

    function hideModals() {
        [addApplicationModal, deleteAllModal, deleteApplicationModal].forEach(modal => {
            modal.style.display = 'none';
        });
    }

    function handleFetchResponse(response, successCallback) {
        response.json().then(data => {
            if (data.success) {
                successCallback();
                location.reload();
            } else {
                console.error('Operation failed');
            }
        }).catch(error => {
            console.error('Fetch error:', error);
        });
    }

    document.getElementById('addApplicationBtn').addEventListener('click', () => showModal(addApplicationModal));
    document.getElementById('deleteAllApplicationsBtn').addEventListener('click', () => showModal(deleteAllModal));
    document.querySelectorAll('.cancel').forEach(button => button.addEventListener('click', hideModals));

    addApplicationForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(addApplicationForm);
        formData.append('action', 'add');
        formData.append('route', 'dashboard');

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(response => handleFetchResponse(response, () => hideModals()));
    });

    document.getElementById('confirmDeleteAll').addEventListener('click', function() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=deleteAll&csrf_token=${csrfToken}&route=dashboard`
        }).then(response => handleFetchResponse(response, () => hideModals()));
    });

    document.querySelectorAll('.delete-application').forEach(element => {
        element.addEventListener('click', function() {
            applicationToDelete = this.dataset.id;
            showModal(deleteApplicationModal);
        });
    });

    document.getElementById('confirmDeleteApplication').addEventListener('click', function() {
        if (applicationToDelete) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=delete&id=${applicationToDelete}&csrf_token=${csrfToken}&route=dashboard`
            }).then(response => handleFetchResponse(response, () => hideModals()));
        }
    });

    // Update sort icons and URLs
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || 'applied_date';
    const currentOrder = urlParams.get('order') || 'desc';

    document.querySelectorAll('.sortable').forEach(th => {
        th.addEventListener('click', function(e) {
            e.preventDefault();
            const sort = this.dataset.sort;
            const newOrder = sort === currentSort && currentOrder === 'desc' ? 'asc' : 'desc';
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('sort', sort);
            newUrl.searchParams.set('order', newOrder);
            window.location.href = newUrl.toString();
        });
    });

    function updateSortIcons() {
        document.querySelectorAll('.sortable').forEach(th => {
            const sortIcon = th.querySelector('.sort-icon');
            if (th.dataset.sort === currentSort) {
                sortIcon.style.display = 'inline';
                sortIcon.src = currentOrder === 'asc' ? '/icons/arrow-up.svg' : '/icons/arrow-down.svg';
            } else {
                sortIcon.style.display = 'none';
            }
        });
    }

    updateSortIcons();

    // Add tooltips for full dates
    document.querySelectorAll('td[data-full-date]').forEach(td => {
        td.title = new Date(td.dataset.fullDate).toLocaleString();
    });

    // Update last updated cell with full date
    document.querySelectorAll('.update-status').forEach(element => {
        element.addEventListener('click', function() {
            const applicationId = this.dataset.id;
            const statusSelect = this.closest('tr').querySelector('select[name="status"]');
            const newStatus = statusSelect.value;

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=update&id=${applicationId}&status=${newStatus}&csrf_token=${csrfToken}&route=dashboard`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Status updated successfully');
                    // Update the last updated column
                    const lastUpdatedCell = this.closest('tr').querySelector('td:nth-child(4)');
                    const now = new Date();
                    lastUpdatedCell.textContent = now.toISOString().split('T')[0];
                    lastUpdatedCell.dataset.fullDate = now.toISOString();
                    lastUpdatedCell.title = now.toLocaleString();
                } else {
                    console.error('Failed to update status:', data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
        });
    });

    // Status filtering
    const statusFilterIcon = document.getElementById('statusFilterIcon');
    const statusFilterModal = document.getElementById('statusFilterModal');
    const selectAllCheckbox = document.getElementById('selectAllStatuses');
    const statusCheckboxes = document.querySelectorAll('input[name="statusFilter"]');
    const applyFilterBtn = document.getElementById('applyFilter');
    const cancelFilterBtn = document.getElementById('cancelFilter');

    if (statusFilterIcon && statusFilterModal) {
        statusFilterIcon.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            statusFilterModal.classList.toggle('show');
        });
        
        document.addEventListener('click', function(event) {
            if (!statusFilterModal.contains(event.target) && event.target !== statusFilterIcon) {
                statusFilterModal.classList.remove('show');
            }
        });

        selectAllCheckbox.addEventListener('change', function() {
            statusCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        applyFilterBtn.addEventListener('click', function() {
            const selectedStatuses = Array.from(statusCheckboxes)
                .filter(checkbox => checkbox.checked)
                .map(checkbox => checkbox.value);

            const currentUrl = new URL(window.location.href);
            if (selectedStatuses.length > 0) {
                currentUrl.searchParams.set('statuses', selectedStatuses.join(','));
            } else {
                currentUrl.searchParams.delete('statuses');
            }
            currentUrl.searchParams.set('page', '1'); // Reset to first page when applying filter
            window.location.href = currentUrl.toString();
        });

        cancelFilterBtn.addEventListener('click', function() {
            statusFilterModal.classList.remove('show');
        });

        // Set initial checkbox states based on URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const activeStatuses = urlParams.get('statuses')?.split(',') || [];
        statusCheckboxes.forEach(checkbox => {
            checkbox.checked = activeStatuses.includes(checkbox.value);
        });
        selectAllCheckbox.checked = activeStatuses.length === statusCheckboxes.length;
    }

    document.getElementById('metricsBtn').addEventListener('click', () => {
        showModal(document.getElementById('metricsModal'));
        fetchMetrics();
    });

    document.querySelector('#metricsModal .cancel').addEventListener('click', () => {
        hideModal(document.getElementById('metricsModal'));
    });

    function fetchMetrics() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=getMetrics&csrf_token=${csrfToken}&route=dashboard`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Error parsing JSON:', text);
                throw new Error('Invalid JSON response');
            }
        })
        .then(data => {
            if (data.success) {
                displayMetrics(data.metrics);
            } else {
                console.error('Failed to fetch metrics:', data.message);
                alert('Failed to fetch metrics: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('An error occurred while fetching metrics: ' + error.message);
        });
    }

    function displayMetrics(metrics) {
        document.getElementById('applicationRate').textContent = `Application Rate: ${metrics.applicationRate}/day (${metrics.totalApplications} applications / ${metrics.totalDays} days)`;
        document.getElementById('responseRate').textContent = `Response Rate: ${metrics.responseRate}% (${metrics.responsesReceived}/${metrics.totalApplications} applications)`;
        document.getElementById('responseTime').textContent = `Average Response Time: ${metrics.averageResponseTime} days`;

        if (window.responseTimeChart instanceof Chart) {
            window.responseTimeChart.destroy();
        }
        if (window.weekdayChart instanceof Chart) {
            window.weekdayChart.destroy();
        }

        window.responseTimeChart = createResponseTimeChart(metrics.responseTimeCounts);
        window.weekdayChart = createWeekdayChart(metrics.weekdayCounts);
    }

    function createResponseTimeChart(data) {
        const ctx = document.getElementById('responseTimeChart').getContext('2d');
        const labels = Object.keys(data).map(key => key === '14' ? '14+' : key);
        
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Response Time Distribution',
                    data: Object.values(data),
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Applications'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Response Time (Days)'
                        }
                    }
                }
            }
        });
    }

    function createWeekdayChart(data) {
        const ctx = document.getElementById('weekdayChart').getContext('2d');
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                datasets: [{
                    label: 'Applications Submitted',
                    data: data.map(day => day.submitted),
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Responses Received',
                    data: data.map(day => day.responses),
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Day of Week'
                        }
                    }
                }
            }
        });
    }

    function hideModal(modal) {
        modal.style.display = 'none';
    }
});
</script>