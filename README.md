# IT Support Update Dashboard

A lightweight, static dashboard for viewing computer update counts from a server API. Open `index.html` in a browser, enter an absolute API URL or a same-origin path such as `/api/computers`, and choose **Refresh data**.

Choose **Load demo** to preview the dashboard with 12 sample computers (5 remaining, 3 in progress, and 4 completed). Demo data is labeled and does not call the server.

## API response format

The endpoint should return JSON as one of:

```json
[
  { "name": "PC-001", "status": "not_updated" },
  { "name": "PC-002", "status": "in_progress" },
  { "name": "PC-003", "status": "completed" }
]
```

or an object containing a `computers`, `data`, `devices`, or `results` array with the same records. The dashboard reads the computer name from `name`, `hostname`, `computerName`, `computer_name`, `deviceName`, `device_name`, or `id`, and the status from `status`, `updateStatus`, or `update_status`. It also accepts `isUpdated` / `is_updated` and `isUpdating` / `is_updating` boolean fields.

Statuses recognized as in progress include `in_progress`, `processing`, and `updating`; completed statuses include `completed`, `done`, `finished`, and `updated`. Other statuses are counted as **Remaining**. The response must allow cross-origin browser requests (CORS) when the dashboard is hosted on a different origin.

## PHP version demo

The separate [`php-version`](./php-version/) folder contains a server-rendered PHP comparison using the same sample computers. It now includes login/logout and admin-managed Admin, Operator, and Viewer roles; the admin can add/delete accounts and set roles. See its [README](./php-version/README.md) for setup and run instructions. The PHP demo stores sample status changes in the current session and does not update real devices.

To try the interactive PHP-like flow without installing PHP, open [`php-version/simulation.html`](./php-version/simulation.html). It simulates account setup, role access, and demo computer actions in browser storage; it is not PHP execution or real authentication.

Authentication applies only to the PHP demo. The static `index.html` dashboard is still public and has no login or access control.
