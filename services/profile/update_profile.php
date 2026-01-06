<?php
// Update profile info for the logged-in user
// Expects POST: fullName, email, height, weight (optional)

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth.php';
require_login();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/logs/logger.php';

$logger = get_logger();

$userId = (int)($_SESSION['client_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$fullName = trim($_POST['fullName'] ?? '');
$email = trim($_POST['email'] ?? '');
$height = trim($_POST['height'] ?? '');
$weight = trim($_POST['weight'] ?? '');

$changes = [];
$errors = [];

if ($fullName !== '') {
    // Split full name: first token as first_name, rest as last_name
    $parts = preg_split('/\s+/', $fullName);
    $first = $parts ? array_shift($parts) : '';
    $last = $parts ? trim(implode(' ', $parts)) : '';
    if ($first === '' || $last === '') {
        // If not enough parts, keep everything as first name, last name empty
        $last = $last; // already set
    }
    $changes['first_name'] = $first;
    $changes['last_name'] = $last;
}

if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email invalide.';
    } else {
        $changes['email'] = $email;
    }
}

if ($height !== '') {
    if (!is_numeric($height) || (float)$height < 0) {
        $errors[] = 'Taille invalide.';
    } else {
        $changes['height_cm'] = (float)$height;
    }
}

if ($weight !== '') {
    if (!is_numeric($weight) || (float)$weight < 0) {
        $errors[] = 'Poids invalide.';
    } else {
        $changes['weight_kg'] = (float)$weight;
    }
}

if ($errors) {
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

if (!$changes) {
    echo json_encode(['success' => true, 'updated' => [], 'message' => 'Aucune modification']);
    exit;
}

try {
    $pdo = db_get_pdo();

    // Fetch current values for before/after logging
    $currentStmt = $pdo->prepare('SELECT first_name, last_name, email, height_cm, weight_kg FROM clients WHERE id = :id LIMIT 1');
    $currentStmt->execute([':id' => $userId]);
    $currentVals = $currentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // If email is being changed, ensure uniqueness
    if (isset($changes['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute([':email' => $changes['email'], ':id' => $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Cet email est déjà utilisé.']);
            exit;
        }
    }

    $sets = [];
    $params = [':id' => $userId];
    foreach ($changes as $col => $val) {
        $sets[] = "$col = :$col";
        $params[":$col"] = $val;
    }
    $sql = 'UPDATE clients SET ' . implode(', ', $sets) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Update session cache for immediate UI reflection (navbar greeting, etc.)
    if (isset($changes['first_name']) || isset($changes['last_name'])) {
        $first = $changes['first_name'] ?? ($currentVals['first_name'] ?? ($_SESSION['first_name'] ?? ''));
        $last  = $changes['last_name']  ?? ($currentVals['last_name']  ?? ($_SESSION['last_name']  ?? ''));
        $_SESSION['first_name'] = $first;
        $_SESSION['last_name']  = $last;
        $_SESSION['user_name']  = trim($first . ' ' . $last);
    }
    if (isset($changes['email'])) {
        $_SESSION['user_email'] = $changes['email'];
    }

    // Build before/after change list
    $changeList = [];
    foreach ($changes as $field => $newVal) {
        $changeList[] = [
            'field' => $field,
            'old' => $currentVals[$field] ?? null,
            'new' => $newVal
        ];
    }
    $logger->logAdvanced('AUDIT', 'profile_update', [], 'user', $userId, 'success', null, $changeList);

    echo json_encode(['success' => true, 'updated' => array_keys($changes)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
