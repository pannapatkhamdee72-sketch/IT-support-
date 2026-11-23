# PHP dashboard demo

This folder is a separate, server-rendered PHP version of the computer update dashboard. It includes a login, logout, role-based access, and admin user management, using the same 12 sample computers as the main page (5 remaining, 3 in progress, 4 completed).

Requires PHP 7.3 or newer (PHP 8.2+ recommended).

## Run locally

From the project root in PowerShell, with PHP installed:

```powershell
php -S localhost:8000
```

Then open `http://localhost:8000/php-version/`. Do not open `index.php` as a `file://` URL: PHP needs a server to execute.

On the first visit, create an administrator account with a username and a password of 12–1024 characters. There is no built-in username or default password. Run the initial setup on localhost before exposing the app to any network; the first account-creation screen is only intended for that one-time local bootstrap. Sign in at `/php-version/login.php`.

For static visual previews without PHP, open `login-preview.html`, `dashboard-preview.html`, or `users-preview.html`. Preview pages do not authenticate users or process actions.

## Try the interactive simulation without PHP

Open `simulation.html` in a browser to try the setup, sign-in, dashboard actions, and user roles without installing PHP. The simulation saves sample data and password hashes in that browser's local storage. It is not PHP execution, real authentication, or suitable for real accounts. Clear this site's local storage to reset the simulation.

| Role | Permissions |
|---|---|
| Admin | View the dashboard, mark demo computers complete, reset demo data, add/delete users, and change other users' roles |
| Operator | View the dashboard and mark demo computers complete |
| Viewer | View the dashboard only |

Admins can manage accounts at **Manage users**. The account currently signed in cannot be demoted or deleted, and the last admin cannot be demoted or deleted.

Use **Mark completed** to submit a form. PHP updates that sample computer in the current server session, recalculates the totals, and returns a newly rendered page. **Reset demo data** restores the starting values. This only changes sample session data; it does not contact an API or update real computers.

Account data and password hashes are written to `ITsupportDashboard-private` beside the project folder (outside the project-root web server document root). Ensure the PHP process can write to that directory. You can set `IT_SUPPORT_DATA_DIR` to another private, writable directory outside your web root. Use HTTPS and secure server configuration before exposing a real deployment to a network; this is a local demo, not a complete production identity system.

The main dashboard uses browser JavaScript to fetch data and update the page without a full reload. This PHP demo renders the page on the server and submits actions back to the server, causing a full page reload. Both versions use the same CSS styles for an easier visual comparison. The Power BI-style panel is illustrative and is not an embedded Power BI report.
