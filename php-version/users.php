<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
$currentUser = requireRole(['admin']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrfToken()) {
        http_response_code(400);
        $error = 'This request could not be verified. Reload the page and try again.';
    } else {
        $action = postString('action');
        $username = trim(postString('username'));
        $role = postString('role');

        if ($action === 'add') {
            $password = postString('password');

            if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username)) {
                $error = 'Choose a username of 3–32 letters, numbers, dots, underscores, or hyphens.';
            } elseif (!validNewPassword($password)) {
                $error = 'Use a password with at least 12 characters and no more than 1024 bytes.';
            } elseif (!isset(USER_ROLES[$role])) {
                $error = 'Choose a valid user role.';
            } else {
                $added = changeUsers(static function (array &$users) use ($username, $password, $role): bool {
                    foreach ($users as $user) {
                        if (strtolower((string) ($user['username'] ?? '')) === strtolower($username)) {
                            return false;
                        }
                    }

                    $users[] = [
                        'id' => createAccountId(),
                        'username' => $username,
                        'password_hash' => hashPassword($password),
                        'role' => $role,
                        'created_at' => date(DATE_ATOM),
                    ];
                    return true;
                });

                if ($added) {
                    flash('Account created for ' . $username . '.');
                    header('Location: users.php');
                    exit;
                }
                $error = 'That username is already in use.';
            }
        } elseif ($action === 'role') {
            $targetId = postString('user_id');
            if ($targetId === '' || !isset(USER_ROLES[$role])) {
                $error = 'Choose a valid account and role.';
            } elseif ($targetId === $currentUser['id']) {
                $error = 'You cannot change your own role while signed in.';
            } else {
                $result = changeUsers(static function (array &$users) use ($targetId, $role): string {
                    $targetIndex = null;
                    $adminCount = 0;
                    foreach ($users as $index => $user) {
                        if (($user['role'] ?? '') === 'admin') {
                            $adminCount++;
                        }
                        if (($user['id'] ?? '') === $targetId) {
                            $targetIndex = $index;
                        }
                    }

                    if ($targetIndex === null) {
                        return 'missing';
                    }
                    if (($users[$targetIndex]['role'] ?? '') === 'admin' && $role !== 'admin' && $adminCount <= 1) {
                        return 'last_admin';
                    }

                    $users[$targetIndex]['role'] = $role;
                    return 'updated';
                });

                if ($result === 'updated') {
                    flash('User role updated.');
                    header('Location: users.php');
                    exit;
                }
                $error = $result === 'last_admin'
                    ? 'The last administrator must remain an admin.'
                    : 'The selected account no longer exists.';
            }
        } elseif ($action === 'delete') {
            $targetId = postString('user_id');
            if ($targetId === '') {
                $error = 'Choose a valid account.';
            } elseif ($targetId === $currentUser['id']) {
                $error = 'You cannot delete the account you are currently using.';
            } else {
                $result = changeUsers(static function (array &$users) use ($targetId): string {
                    $targetIndex = null;
                    $adminCount = 0;
                    foreach ($users as $index => $user) {
                        if (($user['role'] ?? '') === 'admin') {
                            $adminCount++;
                        }
                        if (($user['id'] ?? '') === $targetId) {
                            $targetIndex = $index;
                        }
                    }

                    if ($targetIndex === null) {
                        return 'missing';
                    }
                    if (($users[$targetIndex]['role'] ?? '') === 'admin' && $adminCount <= 1) {
                        return 'last_admin';
                    }

                    array_splice($users, $targetIndex, 1);
                    return 'deleted';
                });

                if ($result === 'deleted') {
                    flash('Account deleted.');
                    header('Location: users.php');
                    exit;
                }
                $error = $result === 'last_admin'
                    ? 'The last administrator account cannot be deleted.'
                    : 'The selected account no longer exists.';
            }
        } else {
            $error = 'The requested action is invalid.';
        }
    }
}

$users = loadUsers();
$message = takeFlash();
$token = csrfToken();
$newUserRoles = [
    'viewer' => USER_ROLES['viewer'],
    'operator' => USER_ROLES['operator'],
    'admin' => USER_ROLES['admin'],
];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f7fb">
    <title>User management | IT Support</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="php.css">
  </head>
  <body>
    <main class="page-shell">
      <header class="topbar">
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">IT</span><span class="brand-name">IT Support <span>PHP Demo</span></span></a>
        <div class="account-actions">
          <span class="signed-in-as">Signed in as <strong><?= e($currentUser['username']) ?></strong></span>
          <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <button class="button button-secondary button-small" type="submit">Log out</button>
          </form>
        </div>
      </header>

      <section class="page-heading">
        <div>
          <p class="eyebrow">ADMINISTRATION</p>
          <h1>User management</h1>
          <p class="page-description">Create accounts, remove users, and set their access level.</p>
        </div>
        <a class="button button-secondary back-button" href="index.php">Back to dashboard</a>
      </section>

      <?php if ($message !== ''): ?><div class="php-notice" role="status"><?= e($message) ?></div><?php endif; ?>
      <?php if ($error !== ''): ?><div class="auth-error" role="alert"><?= e($error) ?></div><?php endif; ?>

      <section class="devices-section user-create-section" aria-labelledby="create-title">
        <div class="section-heading">
          <div><h2 id="create-title">Add user</h2><p>New users must change their password policy with your organization before production use.</p></div>
        </div>
        <form method="post" class="user-create-form">
          <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
          <input type="hidden" name="action" value="add">
          <label>Username<input name="username" type="text" minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]{3,32}" autocomplete="off" required></label>
          <label>Temporary password<input name="password" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required></label>
          <label>Role<select name="role" required>
            <?php foreach ($newUserRoles as $value => $label): ?>
            <option value="<?= e($value) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select></label>
          <button class="button button-primary create-user-button" type="submit">Add account</button>
          <p class="field-hint">Usernames use 3–32 letters, numbers, dots, underscores, or hyphens. Passwords require 12–1024 characters.</p>
        </form>
      </section>

      <section class="devices-section user-list-section" aria-labelledby="users-title">
        <div class="section-heading device-heading">
          <div><h2 id="users-title">Accounts and permissions</h2><p>Admin: manage accounts and demo statuses · Operator: change demo statuses · Viewer: read-only</p></div>
          <span class="device-count"><?= number_format(count($users)) ?> users</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th scope="col">Username</th><th scope="col">Role</th><th scope="col">Created</th><th scope="col">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($users as $user): ?>
              <tr>
                <td><span class="computer-name"><?= e((string) $user['username']) ?></span><?= $user['id'] === $currentUser['id'] ? ' <span class="you-label">You</span>' : '' ?></td>
                <td>
                  <?php if ($user['id'] !== $currentUser['id']): ?>
                  <form method="post" class="inline-role-form">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="role">
                    <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                    <select name="role" aria-label="Role for <?= e((string) $user['username']) ?>">
                      <?php foreach (USER_ROLES as $value => $label): ?>
                      <option value="<?= e($value) ?>" <?= $user['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="button button-small" type="submit">Save role</button>
                  </form>
                  <?php else: ?>
                  <span class="role-badge"><?= e(USER_ROLES[$user['role']] ?? 'Unknown') ?></span>
                  <?php endif; ?>
                </td>
                <td><?= e(date('M j, Y', strtotime((string) $user['created_at']))) ?></td>
                <td>
                  <?php if ($user['id'] !== $currentUser['id']): ?>
                  <form method="post" class="inline-delete-form" onsubmit="return confirm('Delete this account? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                    <button class="button button-small button-danger" type="submit">Delete</button>
                  </form>
                  <?php else: ?>
                  <span class="you-label">Current account protected</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <footer class="page-footer">Private account data is stored outside the web-served project folder.</footer>
    </main>
  </body>
</html>
