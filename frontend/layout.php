<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobert - Job Search Automation</title>
    <link rel="stylesheet" href="/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php if (!in_array($content, ['login', 'register']) && Auth::isLoggedIn()): ?>
    <header>
        <div class="container">
            <div class="logo-container">
                <img src="/icons/briefcase.svg" alt="Jobert logo" class="logo-icon">
                <span class="logo-text">Jobert</span>
            </div>
            <!--<nav>
                <ul>
                    <li><a href="/">Dashboard</a></li>
                </ul>
            </nav>-->
            <div class="user-logout-container">
                <?php if (Auth::isLoggedIn() && !empty($username)): ?>
                    <span class="username"><?php echo htmlspecialchars($username); ?></span>
                    <span class="separator">&bull;</span>
                <?php endif; ?>
                <a href="/logout" class="logout-button">Logout</a>
            </div>
        </div>
    </header>
    <?php endif; ?>
    <main class="container">
        <?php include_template($content, ['nonce' => $nonce]); ?>
    </main>
</body>
</html>