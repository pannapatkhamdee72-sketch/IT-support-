<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

if (currentUser() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
$submittedUsername = postString('username');
$isFirstAdmin = count(loadUsers()) === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrfToken()) {
        http_response_code(400);
        $error = 'This request could not be verified. Reload the page and try again.';
    } else {
        $username = trim($submittedUsername);
        $password = postString('password');

        if ($isFirstAdmin) {
            $confirmation = postString('password_confirmation');

            if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username)) {
                $error = 'Choose a username of 3–32 letters, numbers, dots, underscores, or hyphens.';
            } elseif (!validNewPassword($password)) {
                $error = 'Use a password with at least 12 characters and no more than 1024 bytes.';
            } elseif (!hash_equals($password, $confirmation)) {
                $error = 'The password confirmation does not match.';
            } else {
                $created = changeUsers(static function (array &$users) use ($username, $password): ?array {
                    if (count($users) !== 0) {
                        return null;
                    }

                    $user = [
                        'id' => createAccountId(),
                        'username' => $username,
                        'password_hash' => hashPassword($password),
                        'role' => 'admin',
                        'created_at' => date(DATE_ATOM),
                    ];
                    $users[] = $user;
                    return $user;
                });

                if ($created === null) {
                    $isFirstAdmin = false;
                    $error = 'An administrator was already created. Sign in with that account.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $created['id'];
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    header('Location: index.php');
                    exit;
                }
            }
        } else {
            $normalizedUsername = strtolower($username);
            $foundUser = null;
            foreach (loadUsers() as $user) {
                if (is_array($user) && strtolower((string) ($user['username'] ?? '')) === $normalizedUsername) {
                    $foundUser = $user;
                    break;
                }
            }

            if ($foundUser !== null && isset($foundUser['password_hash']) && password_verify($password, $foundUser['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $foundUser['id'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: index.php');
                exit;
            }

            $error = 'Incorrect username or password.';
        }
    }
}

$token = csrfToken();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f7fb">
    <title><?= $isFirstAdmin ? 'Create admin account' : 'Sign in' ?> | IT Support</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="php.css">
  </head>
  <body>
    <main class="auth-shell">
      <a class="brand auth-brand" href="login.php">
        <span class="brand-mark" aria-hidden="true">IT</span>
        <span class="brand-name">IT Support <span>PHP Demo</span></span>
      </a>
      <section class="auth-card" aria-labelledby="auth-title">
        <p class="eyebrow"><?= $isFirstAdmin ? 'INITIAL SETUP' : 'SECURE SIGN IN' ?></p>
        <h1 id="auth-title"><?= $isFirstAdmin ? 'Create admin account' : 'Welcome back' ?></h1>
        <p class="auth-description"><?= $isFirstAdmin ? 'Set up the first administrator. No default password is installed.' : 'Sign in to view the computer update dashboard.' ?></p>

        <?php if ($error !== ''): ?>
        <div class="auth-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="auth-form">
          <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" autocomplete="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]{3,32}" value="<?= e($submittedUsername) ?>" required autofocus>

          <label for="password"><?= $isFirstAdmin ? 'Create password' : 'Password' ?></label>
          <input id="password" name="password" type="password" maxlength="1024" autocomplete="<?= $isFirstAdmin ? 'new-password' : 'current-password' ?>" <?= $isFirstAdmin ? 'minlength="12"' : '' ?> required>

          <?php if ($isFirstAdmin): ?>
          <label for="password_confirmation">Confirm password</label>
          <input id="password_confirmation" name="password_confirmation" type="password" maxlength="1024" autocomplete="new-password" minlength="12" required>
          <p class="field-hint">Use 12–1024 characters. Usernames may contain letters, numbers, dots, underscores, and hyphens.</p>
          <?php endif; ?>

          <button class="button button-primary auth-submit" type="submit"><?= $isFirstAdmin ? 'Create administrator' : 'Sign in' ?></button>
        </form>
        <p class="auth-footnote">Account credentials are stored on the server as password hashes.</p>
      </section>
    </main>
  </body>
</html>
