<?php
// Simple auth guard used by all pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void {
    $isLogged = !empty($_SESSION['client_id']);
    $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    $publicPages = ['login.php','register.php'];
    
    // Si admin connecté et essaie d'accéder aux pages normales, rediriger vers admin
    if ($isLogged && !empty($_SESSION['is_admin']) && !strpos($_SERVER['REQUEST_URI'], '/admin/')) {
        if (!in_array($current, $publicPages, true)) {
            header('Location: views/admin/admin.php');
            exit;
        }
    }
    
    if (!$isLogged && !in_array($current, $publicPages, true)) {
        $redirect = isset($_SERVER['REQUEST_URI']) ? urlencode($_SERVER['REQUEST_URI']) : '';
        header('Location: login.php'.($redirect?'?redirect='.$redirect:''));
        exit;
    }
}

/**
 * Vérifie que l'utilisateur est admin (is_admin = 1)
 * Redirige vers index.php si non admin
 */
function require_admin(): void {
    require_login(); // D'abord vérifier connexion
    
    $isAdmin = !empty($_SESSION['is_admin']);
    if (!$isAdmin) {
        http_response_code(404);
        // Afficher la page 404 personnalisée si l'utilisateur n'est pas admin
        require __DIR__ . '/../404.php';
        exit;
    }
}
