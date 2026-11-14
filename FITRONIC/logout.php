<?php
declare(strict_types=1);

require_once __DIR__ . '/server/mysql.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$token = (string)($_COOKIE['fitronic_session'] ?? '');
if ($token !== '') {
    try {
        database()
            ->prepare('DELETE FROM sessions WHERE token_hash = ?')
            ->execute([hash('sha256', $token)]);
    } catch (Throwable $error) {
        error_log('Logout cleanup failed: ' . $error->getMessage());
    }
    setcookie('fitronic_session', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$_SESSION = [];
session_destroy();

header('Location: index1.html#hero');
exit;
