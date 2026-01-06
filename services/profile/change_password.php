<?php
// Change password for logged-in user
// Expects POST: currentPwd, newPwd, confirmPwd

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../controllers/auth.controllers.php';
require_once __DIR__ . '/../../services/logs/logger.php';

$logger = get_logger();

$userId = (int)($_SESSION['client_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$current = (string)($_POST['currentPwd'] ?? '');
$new = (string)($_POST['newPwd'] ?? '');
$confirm = (string)($_POST['confirmPwd'] ?? '');

if ($new === '' || strlen($new) < 8) {
    echo json_encode(['success' => false, 'error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.']);
    exit;
}
if ($new !== $confirm) {
    echo json_encode(['success' => false, 'error' => 'La confirmation ne correspond pas.']);
    exit;
}

try {
    $pdo = db_get_pdo();
    $stmt = $pdo->prepare('SELECT password_hash FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable.']);
        exit;
    }

    [$ok] = auth_verify_password($current, $row['password_hash']);
    if (!$ok) {
        echo json_encode(['success' => false, 'error' => 'Mot de passe actuel incorrect.']);
        exit;
    }

    $newHash = auth_hash_password($new);
    $up = $pdo->prepare('UPDATE clients SET password_hash = :h, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $up->execute([':h' => $newHash, ':id' => $userId]);

    $logger->logPasswordChange($userId, true);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    $logger->logPasswordChange($userId, false);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
