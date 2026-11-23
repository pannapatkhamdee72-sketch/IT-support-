<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();
header('Cache-Control: no-store, private');
header('Pragma: no-cache');

const USER_ROLES = [
    'admin' => 'Admin',
    'operator' => 'Operator',
    'viewer' => 'Viewer',
];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function postString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

function validNewPassword(string $password): bool
{
    if (strlen($password) > 1024 || preg_match_all('/./us', $password, $matches) === false) {
        return false;
    }

    return count($matches[0]) >= 12;
}

function hashPassword(string $password): string
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash)) {
        throw new RuntimeException('Unable to secure the account password.');
    }

    return $hash;
}

function usersFilePath(): string
{
    $configuredDirectory = getenv('IT_SUPPORT_DATA_DIR');
    $directory = $configuredDirectory !== false && $configuredDirectory !== ''
        ? $configuredDirectory
        : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'ITsupportDashboard-private';

    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the private account storage directory.');
    }

    if (!is_writable($directory)) {
        throw new RuntimeException('The private account storage directory is not writable.');
    }

    return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'users.json';
}

function withUsersFileLock(int $lockType, callable $callback)
{
    $path = usersFilePath();
    $file = fopen($path, 'c+b');
    if ($file === false) {
        throw new RuntimeException('Unable to open private account storage.');
    }
    if (!chmod($path, 0600)) {
        fclose($file);
        throw new RuntimeException('Unable to restrict private account file permissions.');
    }

    try {
        if (!flock($file, $lockType)) {
            throw new RuntimeException('Unable to lock private account storage.');
        }

        rewind($file);
        $contents = stream_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException('Unable to read private account storage.');
        }

        if ($contents === '') {
            $users = [];
        } else {
            $users = json_decode($contents, true);
            if (!is_array($users) || json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Private account storage contains invalid JSON.');
            }
            foreach ($users as $user) {
                if (
                    !is_array($user)
                    || !is_string($user['id'] ?? null)
                    || !is_string($user['username'] ?? null)
                    || !is_string($user['password_hash'] ?? null)
                    || !is_string($user['role'] ?? null)
                    || !isset(USER_ROLES[$user['role']])
                    || !is_string($user['created_at'] ?? null)
                ) {
                    throw new RuntimeException('Private account storage contains an invalid account record.');
                }
            }
        }

        $result = $callback($users, $file);
        flock($file, LOCK_UN);
        return $result;
    } finally {
        fclose($file);
    }
}

function loadUsers(): array
{
    return withUsersFileLock(LOCK_SH, static function (array &$users): array {
        return $users;
    });
}

function changeUsers(callable $callback)
{
    return withUsersFileLock(LOCK_EX, static function (array &$users, $file) use ($callback) {
        $result = $callback($users);
        $encoded = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode account data.');
        }

        rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, $encoded . PHP_EOL) === false || !fflush($file)) {
            throw new RuntimeException('Unable to save private account storage.');
        }
        return $result;
    });
}

function accountById(string $id): ?array
{
    foreach (loadUsers() as $user) {
        if (is_array($user) && ($user['id'] ?? null) === $id) {
            return $user;
        }
    }

    return null;
}

function currentUser(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_string($id) || $id === '') {
        return null;
    }

    $user = accountById($id);
    if ($user === null) {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        return null;
    }

    return $user;
}

function requireLogin(): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function requireRole(array $roles): array
{
    $user = requireLogin();
    if (!in_array($user['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }

    return $user;
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validCsrfToken(): bool
{
    $submitted = $_POST['csrf_token'] ?? null;
    return is_string($submitted) && hash_equals(csrfToken(), $submitted);
}

function createAccountId(): string
{
    return bin2hex(random_bytes(16));
}

function flash(string $message): void
{
    $_SESSION['flash_message'] = $message;
}

function takeFlash(): string
{
    $message = $_SESSION['flash_message'] ?? '';
    unset($_SESSION['flash_message']);
    return is_string($message) ? $message : '';
}
