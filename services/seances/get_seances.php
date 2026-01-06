<?php
// Get workout sessions for the logged-in user
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth.php';
require_login();

$userId = (int)($_SESSION['client_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

try {
    $dbPath = __DIR__ . '/../sql/seances.db';
    if (!file_exists($dbPath)) {
        echo json_encode(['success' => true, 'seances' => []]);
        exit;
    }
    
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Get all sessions for this user, ordered by date desc
    $stmt = $pdo->prepare('SELECT * FROM seances WHERE user_id = :user_id ORDER BY date DESC, created_at DESC');
    $stmt->execute([':user_id' => $userId]);
    $seances = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'seances' => $seances]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
