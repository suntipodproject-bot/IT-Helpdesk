<?php
// ======================================================
// auth.php — Session Authentication Helper
// ======================================================
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirect to login if not authenticated.
 * Call this at the top of every protected page.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        $elapsed = (time() - $_SESSION['last_activity']) / 60;
        if ($elapsed > SESSION_TIMEOUT) {
            logout();
        }
    }
    $_SESSION['last_activity'] = time();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser(): array {
    return [
        'id'        => $_SESSION['user_id']   ?? null,
        'username'  => $_SESSION['username']  ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role']      ?? 'staff',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function logout(): void {
    session_unset();
    session_destroy();
    header('Location: /login.php');
    exit;
}
