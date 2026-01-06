<?php
// Save workout session to seances.db
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth.php';
require_login();
require_once __DIR__ . '/../../services/logs/logger.php';

$logger = get_logger();
$userId = (int)($_SESSION['client_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['date']) || !isset($data['exercises'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
        exit;
    }
    
    $date = trim($data['date']);
    $exercises = $data['exercises'];
    $notes = trim($data['notes'] ?? '');
    
    if (empty($exercises)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun exercice fourni']);
        exit;
    }
    
    // Connect to seances.db
        $dbPath = __DIR__ . '/../sql/seances.db';
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS seances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            exercice_name TEXT NOT NULL,
            muscle_group TEXT,
            sets INTEGER NOT NULL DEFAULT 0,
            reps INTEGER NOT NULL DEFAULT 0,
            weight_kg REAL NOT NULL DEFAULT 0,
            notes TEXT,
            duration_minutes INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
    
    $stmt = $pdo->prepare('INSERT INTO seances (user_id, date, exercice_name, muscle_group, sets, reps, weight_kg, notes) VALUES (:user_id, :date, :exercice, :muscle, :sets, :reps, :weight, :notes)');
    
    $insertedCount = 0;
    // Try to derive muscle group from exercices.db by matching exercise name
    $exDbPath = __DIR__ . '/../sql/exercices.db';
    $exPdo = null;
    if (file_exists($exDbPath)) {
        $exPdo = new PDO('sqlite:' . $exDbPath);
        $exPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    foreach ($exercises as $ex) {
        $exerciceName = trim($ex['name'] ?? '');
        $muscleGroup = trim($ex['muscle'] ?? '');
        // Aggregate sets
        $setsArr = is_array($ex['sets'] ?? null) ? $ex['sets'] : [];
        $setCount = 0;
        $repsValues = [];
        $weightValues = [];
        foreach ($setsArr as $s) {
            $r = isset($s['reps']) ? (int)preg_replace('/[^0-9]/', '', (string)$s['reps']) : 0;
            $w = isset($s['weight']) ? (float)preg_replace('/[^0-9.]/', '', (string)$s['weight']) : 0.0;
            if ($r > 0 || $w > 0) {
                $setCount++;
                if ($r > 0) $repsValues[] = $r;
                if ($w > 0) $weightValues[] = $w;
            }
        }
        $avgReps = $repsValues ? (int)round(array_sum($repsValues) / max(count($repsValues), 1)) : 0;
        $avgWeight = $weightValues ? (float)round(array_sum($weightValues) / max(count($weightValues), 1), 2) : 0.0;
        
        if (empty($exerciceName)) continue;

        // Lookup muscle group from exercices.db if not provided
        if ($muscleGroup === '' && $exPdo) {
            $q = $exPdo->prepare('SELECT target_muscle, muscles_cibles FROM exercices WHERE LOWER(name) = LOWER(:n) LIMIT 1');
            $q->execute([':n' => $exerciceName]);
            if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                $muscleGroup = trim($row['target_muscle'] ?? '');
                if ($muscleGroup === '' && !empty($row['muscles_cibles'])) {
                    // take first muscle from list
                    $parts = preg_split('/\s*,\s*/', (string)$row['muscles_cibles']);
                    $muscleGroup = $parts[0] ?? '';
                }
            }
        }
        
        $stmt->execute([
            ':user_id' => $userId,
            ':date' => $date,
            ':exercice' => $exerciceName,
            ':muscle' => $muscleGroup,
            ':sets' => $setCount,
            ':reps' => $avgReps,
            ':weight' => $avgWeight,
            ':notes' => $notes
        ]);
        $insertedCount++;
    }
    
    $logger->logAdvanced('SEANCE', 'save_workout', ['exercises_count' => $insertedCount, 'date' => $date], 'user', $userId, 'success');
    
    echo json_encode([
        'success' => true,
        'inserted' => $insertedCount,
        'message' => 'Séance enregistrée avec succès'
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
