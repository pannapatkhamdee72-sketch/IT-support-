<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validCsrfToken()) {
    http_response_code(400);
    exit('Invalid logout request.');
}

$_SESSION = [];
$parameters = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $parameters['path'],
    'domain' => $parameters['domain'],
    'secure' => $parameters['secure'],
    'httponly' => $parameters['httponly'],
    'samesite' => $parameters['samesite'] ?? 'Lax',
]);
session_destroy();

header('Location: login.php');
exit;
