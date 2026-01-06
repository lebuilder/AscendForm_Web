<?php
// Upload avatar for the logged-in user; updates DB and session
// Expects multipart/form-data with field: avatar

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/logs/logger.php';

$logger = get_logger();
$userId = (int)($_SESSION['client_id'] ?? 0);
$base = '/dashboard/AscendForm'; // public base path for building URLs
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

try {
    if (!isset($_FILES['avatar']) || !is_uploaded_file($_FILES['avatar']['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu']);
        exit;
    }

    $file = $_FILES['avatar'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Erreur d\'upload (' . $file['error'] . ')']);
        exit;
    }

    // Limit size (e.g., 4MB)
    $maxBytes = 4 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 4 Mo)']);
        exit;
    }

    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Format non supporté']);
        exit;
    }
    $ext = $allowed[$mime];

    // Ensure target directory exists
    $avatarsDir = realpath(__DIR__ . '/../../media');
    if ($avatarsDir === false) {
        // fallback to project root media path
        $avatarsDir = __DIR__ . '/../../media';
    }
    $avatarsDir = rtrim($avatarsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($avatarsDir)) {
        @mkdir($avatarsDir, 0775, true);
    }

    // Build unique filename
    $name = 'u' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $avatarsDir . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Impossible de sauvegarder le fichier']);
        exit;
    }

    // Build web path (relative to project root)
    $relativePath = 'media/avatars/' . $name;

    $pdo = db_get_pdo();

    // Retrieve current avatar to optionally cleanup
    $stmtCur = $pdo->prepare('SELECT avatar_path FROM clients WHERE id = :id');
    $stmtCur->execute([':id' => $userId]);
    $current = $stmtCur->fetch(PDO::FETCH_ASSOC) ?: [];
    $oldAvatar = $current['avatar_path'] ?? null;

    // Update DB
    $stmt = $pdo->prepare('UPDATE clients SET avatar_path = :p, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute([':p' => $relativePath, ':id' => $userId]);

    // Update session
    $_SESSION['avatar_path'] = $relativePath;

    // Try to remove old avatar if it was in our avatars folder
    if ($oldAvatar && preg_match('#^media/avatars/#', $oldAvatar)) {
        $oldPath = realpath(__DIR__ . '/../../' . $oldAvatar);
        if ($oldPath && strpos($oldPath, realpath(__DIR__ . '/../../media/avatars')) === 0 && is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $logger->logAdvanced('PROFILE', 'upload_avatar', [], 'user', $userId, 'success', null, [
        ['field' => 'avatar_path', 'old' => $oldAvatar, 'new' => $relativePath]
    ]);

    // Return a URL that works under project base (not server root)
    $publicUrl = rtrim($base, '/') . '/' . ltrim($relativePath, '/');
    echo json_encode(['success' => true, 'avatar' => $publicUrl, 'path' => $relativePath]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
