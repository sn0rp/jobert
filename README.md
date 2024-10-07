# <img src="public/icons/briefcase.svg" alt="Jobert logo" class="logo-icon"> Jobert - Job Application Management Tool
Jobert is a web-based application designed to help job seekers manage their job applications efficiently. It provides a user-friendly interface for tracking job applications, monitoring application statuses, and analyzing job search metrics.

<img src="jobertdemo.png" alt="Jobert demo" style="max-width: 600px; margin: 0 auto; display: block;">

## Features
- User authentication system
- Dashboard for managing job applications
- Add, update, and delete job applications
- Sort and filter job applications
- Pagination for better performance with large datasets
- Application metrics and visualizations
- Responsive design for cross-platform use

## Technology Stack
- Backend: PHP 7.4+
- Database: SQLite
- Frontend: HTML, CSS, JavaScript
- Server: Nginx
- SSL: Let's Encrypt
- Deployment: Ansible
- CI/CD: GitHub Actions

## Setup and Installation
Jobert is ready to be run locally using the built-in PHP server (`php -S localhost:8000 -t public`). Alternatively, setup on a server can be mostly automated using the included Ansible scripts which cover server setup, repository sync, SSL, and more.

## Deployment
Deployment is handled through GitHub Actions and Ansible. The workflow is defined in `github/workflows/deploy.yml`.

To deploy manually:

1. Ensure your SSH key is set up on the server
2. Run the Ansible playbook:
   ```
   ansible-playbook -i ansible/inventory.ini ansible/deploy.yml
   ```

## Database Migrations
Migrations are managed using a custom migration system. To create a new migration:

1. Create a new PHP file in the `migrations/` directory by running `php create_migration.php example_name "Example description"`.
2. Implement the `up()` and `down()` methods
3. Run the migration (handled automatically on the server):
   ```
   php migrate.php up
   ```

To revert migrations, use `php migrate.php down`.

## Security
- HTTPS is enforced for all connections
- CSRF protection is implemented for form submissions
- User passwords are hashed using secure methods

## Contributing
1. Fork the repository
2. Create a new branch for your feature
3. Make your changes and commit them
4. Push to your fork and submit a pull request

### Unimplemented Features
If you're looking for an easy open source contribution...
- AI-generated resume and cover letter for each application
    - There is a button commented out under the `Actions` column in the main application table. No functionality has been implemented for this because I have found human writing to be superior to both GPT-4o and Claude 3.5. To manage user data for this functionality, there is also a commented out `Settings` button which would sit above the table.
- Automated Job Search
    - I used to [scrape jobs using Selenium](https://github.com/sn0rp/jsas) to apply for hundreds of jobs at once. This is rather taxing and takes a while to run. A better approach might be to examine the network requests on your favorite job board and create some kind of automation around them. You could then implement a search functionality to add new jobs to the table with `Not Yet Applied` status.
- Email automation for status updates and interview scheduling
    - If you have a VPS without port 25 (SMTP) blocked, you might consider creating a restricted and automated email service on the backend. The text of incoming mail for a given user could be examined and used to update an application, or to schedule an interview using a calendar integration.
- More detailed metrics (e.g. Sankey diagram)
- "Auto apply" functionality for popular job boards and ATS
- Landing page (if you were to host the website for others)

---

Licensed under the GPL-3.0 License.