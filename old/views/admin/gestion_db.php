<?php
// AscendForm - Gestion de la base de données (Admin)
session_start();

require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/logs/logger.php';

require_admin();

// Liste des bases disponibles
$availableDatabases = [
    'clients' => [
        'name' => 'Clients',
        'file' => __DIR__ . '/../../services/sql/clients.db',
        'description' => 'Base des utilisateurs et authentification'
    ],
    // Ajoutez d'autres bases ici au besoin
    'exercices' => [
        'name' => 'Exercices',
        'file' => __DIR__ . '/../../services/sql/exercices.db',
        'description' => 'Base des exercices et séances'
    ]
];

// Base sélectionnée
$selectedDb = $_GET['db'] ?? 'clients';
if (!isset($availableDatabases[$selectedDb])) {
    $selectedDb = 'clients';
}

$currentDb = $availableDatabases[$selectedDb];
$dbFile = $currentDb['file'];

$message = null;
$error = null;

// CSRF token for admin actions
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$adminCsrf = $_SESSION['admin_csrf'];

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logger = get_logger();
    $timerStart = $logger->startTimer();
    $action = $_POST['action'] ?? '';
    $targetDb = $_POST['target_db'] ?? $selectedDb;
    // CSRF check for mutating actions
    $csrf = $_POST['admin_csrf'] ?? '';
    $needsCsrf = in_array($action, ['edit_row','delete_row','backup','backup_all','export_sql','vacuum','add_exercice'], true);
    if ($needsCsrf && $csrf !== ($_SESSION['admin_csrf'] ?? '')) {
        $error = 'CSRF token invalide.';
    }
    $nowTs = time();
    // Rate limit (1/min) for backup & export
    if (in_array($action,['backup','backup_all','export_sql'])) {
        $key = in_array($action, ['backup','backup_all']) ? 'last_backup_ts' : 'last_export_ts';
        $last = $_SESSION[$key] ?? 0;
        if ($nowTs - (int)$last < 60) {
            $error = "Action trop fréquente, attendre 1 minute.";
            $logger->logAdvanced('WARN','rate_limit_violation',['action'=>$action,'seconds_since'=>($nowTs - (int)$last)],'db',$targetDb,'fail');
            // Skip performing action
        } else {
            $_SESSION[$key] = $nowTs;
        }
    }
    
    // Validation
    if (!isset($availableDatabases[$targetDb])) {
        $error = "Base de données invalide.";
    } else {
        $targetDbFile = $availableDatabases[$targetDb]['file'];
        
        try {
            // Connexion à la base cible
            $targetPdo = new PDO('sqlite:' . $targetDbFile, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            if ($action === 'edit_row') {
                $table = $_POST['table'] ?? '';
                $rowId = $_POST['row_id'] ?? '';
                $updates = $_POST['updates'] ?? [];
                
                if ($table && $rowId && !empty($updates)) {
                    $setClauses = [];
                    $params = [':id' => $rowId];
                    
                    foreach ($updates as $column => $value) {
                        $setClauses[] = "{$column} = :{$column}";
                        $params[":{$column}"] = $value;
                    }
                    
                    $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE id = :id";
                    $stmt = $targetPdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $message = "Ligne modifiée avec succès.";
                    $changesList = [];
                    foreach ($updates as $column => $value) {
                        $changesList[] = ['field' => $column, 'old' => null, 'new' => $value];
                    }
                    log_admin_activity('edit_row', "Admin modif row {$table} id={$rowId}", [
                        'db'=>$targetDb,
                        'table'=>$table,
                        'row_id'=>$rowId,
                        'changes'=>$changesList
                    ]);
                }
            } elseif ($action === 'delete_row') {
                $table = $_POST['table'] ?? '';
                $rowId = $_POST['row_id'] ?? '';
                
                if ($table && $rowId) {
                    $stmt = $targetPdo->prepare("DELETE FROM {$table} WHERE id = :id");
                    $stmt->execute([':id' => $rowId]);
                    $message = "Ligne supprimée avec succès.";
                    log_admin_activity('delete_row', "Admin suppr row {$table} id={$rowId}", [
                        'db'=>$targetDb,
                        'table'=>$table,
                        'row_id'=>$rowId
                    ]);
                }
            } elseif ($action === 'backup') {
                $backupDir = dirname($targetDbFile) . '/backups';
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                $backupFile = $backupDir . '/' . $targetDb . '_backup_' . date('Y-m-d_H-i-s') . '.db';
                
                $dur = null;
                if (copy($targetDbFile, $backupFile)) {
                    $message = "Backup créé avec succès: " . basename($backupFile);
                    log_admin_activity('backup', "Admin backup {$targetDb}", [
                        'db'=>$targetDb,
                        'file'=>basename($backupFile)
                    ]);
                } else {
                    $error = "Erreur lors de la création du backup.";
                    log_admin_activity('backup', "Admin backup failed {$targetDb}", [
                        'db'=>$targetDb,
                        'file'=>basename($backupFile),
                        'error'=>'copy failed'
                    ]);
                }
            } elseif ($action === 'backup_all' && !$error) {
                // Backup de toutes les bases de données
                $backupDir = __DIR__ . '/../../services/sql/backups';
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                $timestamp = date('Y-m-d_H-i-s');
                $successCount = 0;
                $failedDbs = [];
                
                foreach ($availableDatabases as $dbKey => $dbInfo) {
                    if (file_exists($dbInfo['file'])) {
                        $backupFile = $backupDir . '/' . $dbKey . '_backup_' . $timestamp . '.db';
                        if (copy($dbInfo['file'], $backupFile)) {
                            $successCount++;
                            log_admin_activity('backup', "Admin backup {$dbKey} (backup_all)", [
                                'db'=>$dbKey,
                                'file'=>basename($backupFile)
                            ]);
                        } else {
                            $failedDbs[] = $dbInfo['name'];
                        }
                    } else {
                        $failedDbs[] = $dbInfo['name'] . ' (fichier introuvable)';
                    }
                }
                
                if ($successCount > 0 && empty($failedDbs)) {
                    $message = "✅ Backup complet réussi : {$successCount} base(s) sauvegardée(s)";
                } elseif ($successCount > 0) {
                    $message = "⚠️ Backup partiel : {$successCount} réussi(s). Échecs : " . implode(', ', $failedDbs);
                } else {
                    $error = "❌ Échec du backup pour toutes les bases : " . implode(', ', $failedDbs);
                }
                
                // Redirection vers admin.php après backup complet
                if (!$error) {
                    header('Location: admin.php?backup_success=' . urlencode($message));
                    exit;
                }
            } elseif ($action === 'export_sql' && !$error) {
                // Export SQL des données
                $logger = get_logger(); $exportStart = $logger->startTimer();
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="' . $targetDb . '_export_' . date('Y-m-d_H-i-s') . '.sql"');
                $anonymize = isset($_POST['anonymize']) && $_POST['anonymize'] === '1';
                
                echo "-- AscendForm SQLite Export - {$availableDatabases[$targetDb]['name']}\n";
                echo "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
                
                // Schéma
                $tables = $targetPdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($tables as $table) {
                    $createStmt = $targetPdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
                    echo "{$createStmt};\n\n";
                    
                    // Données
                    $rows = $targetPdo->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $values = array_map(function($v) use ($targetPdo) {
                            return $v === null ? 'NULL' : $targetPdo->quote($v);
                        }, array_values($row));
                        // Anonymisation emails for privacy if requested
                        if ($anonymize && isset($row['email'])) {
                            foreach ($row as $colName=>$val) {
                                if (strtolower($colName)==='email') {
                                    // Replace in values array at same index
                                    $idx = array_search($val, $row, true);
                                }
                            }
                            // Simpler: rebuild with column awareness
                            $anonValues = [];
                            $i=0; foreach ($row as $colName=>$val) {
                                if (strtolower($colName)==='email') { $anonValues[] = $targetPdo->quote('ANONYMIZED'); }
                                else { $anonValues[] = $values[$i]; }
                                $i++;
                            }
                            $values = $anonValues;
                        }
                        echo "INSERT INTO {$table} VALUES (" . implode(', ', $values) . ");\n";
                    }
                    echo "\n";
                }
                log_admin_activity('export_sql', "Admin export SQL {$targetDb}", [
                    'db'=>$targetDb,
                    'tables'=>count($tables),
                    'anonymize'=>$anonymize
                ]);
                exit;
            } elseif ($action === 'add_exercice') {
                if ($targetDb !== 'exercices') {
                    throw new Exception('Action réservée à la base exercices');
                }
                $name = trim($_POST['name'] ?? '');
                $muscle = trim($_POST['target_muscle'] ?? '');
                $musclesCibles = trim($_POST['muscles_cibles'] ?? '');
                $photo = '';
                $video = trim($_POST['video_url'] ?? '');
                if ($name === '' || $muscle === '') {
                    throw new Exception('Nom et muscle ciblé sont requis');
                }
                // Upload photo
                if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../media/exercices/';
                    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                    $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (!in_array($ext, $allowed)) { throw new Exception('Format photo invalide (jpg, png, gif, webp)'); }
                    if ($_FILES['photo_file']['size'] > 5*1024*1024) { throw new Exception('Photo trop volumineuse (max 5MB)'); }
                    $filename = uniqid('ex_', true) . '.' . $ext;
                    $dest = $uploadDir . $filename;
                    if (!move_uploaded_file($_FILES['photo_file']['tmp_name'], $dest)) {
                        throw new Exception('Erreur téléchargement photo');
                    }
                    $photo = 'media/exercices/' . $filename;
                }
                $stmt = $targetPdo->prepare('INSERT INTO exercices (name, target_muscle, muscles_cibles, photo_path, video_url) VALUES (:n,:m,:mc,:p,:v)');
                $stmt->execute([':n'=>$name, ':m'=>$muscle, ':mc'=>$musclesCibles ?: null, ':p'=>$photo ?: null, ':v'=>$video ?: null]);
                $message = 'Exercice ajouté avec succès';
                log_admin_activity('add_exercice', 'Admin ajout exercice: '.$name, [
                    'db'=>$targetDb,
                    'name'=>$name,
                    'target_muscle'=>$muscle,
                    'photo'=>$photo,
                    'video'=>$video
                ]);
            } elseif ($action === 'vacuum') {
                $targetPdo->exec('VACUUM');
                $message = "Base de données {$availableDatabases[$targetDb]['name']} optimisée avec VACUUM.";
                log_admin_activity('vacuum', "Admin VACUUM {$targetDb}", ['db'=>$targetDb]);
            }
        } catch (Exception $e) {
            $error = "Erreur: " . $e->getMessage();
            if (isset($logger)) {
                $logger->logAdvanced('ERROR', 'db_action', ['action'=>$action,'error'=>$e->getMessage()], 'db', $targetDb, 'fail');
            }
        }
    }
}

// Informations sur la base sélectionnée
$pdo = db_get_pdo();
$dbSize = file_exists($dbFile) ? filesize($dbFile) : 0;
$dbSizeFormatted = $dbSize > 1048576 ? round($dbSize / 1048576, 2) . ' MB' : round($dbSize / 1024, 2) . ' KB';

// Liste des tables
$tables = [];
$tableStats = [];
if (file_exists($dbFile)) {
    try {
        $dbPdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $tables = $dbPdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $count = (int)$dbPdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            $tableStats[$table] = $count;
        }
    } catch (Exception $e) {
        $error = "Erreur lecture base: " . $e->getMessage();
    }
}

// Données de la table sélectionnée pour édition
$selectedTable = $_GET['table'] ?? '';
$tableData = [];
$tableColumns = [];

if ($selectedTable && isset($tableStats[$selectedTable]) && file_exists($dbFile)) {
    try {
        $dbPdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Récupérer les colonnes
        $columnsInfo = $dbPdo->query("PRAGMA table_info({$selectedTable})")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columnsInfo as $col) {
            $tableColumns[] = $col['name'];
        }
        
        // Récupérer les données (limit 100 pour performance)
        $tableData = $dbPdo->query("SELECT * FROM {$selectedTable} LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        // Log consultation table (admin)
        log_admin_activity('consult_table', "Admin consultation table {$selectedTable}", [
            'db'=>$selectedDb,
            'table'=>$selectedTable,
            'rows'=>count($tableData)
        ]);
    } catch (Exception $e) {
        $error = "Erreur lecture table: " . $e->getMessage();
    }
}

// Backups existants
$backupDir = __DIR__ . '/../../services/sql/backups';
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.db');
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
    usort($backups, fn($a, $b) => $b['date'] - $a['date']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la base - AscendForm Admin</title>
    <link rel="icon" type="image/png" href="../../media/logo_AscendForm.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/fond.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/gestion_db.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-card mb-4 mx-auto" style="max-width: 1100px; margin-top: 2.5rem;">
            <div class="mb-4 text-center position-relative">
                <a href="admin.php" class="back-btn position-absolute top-0 end-0">← Retour</a>
                <h1 class="mb-0">💾 Gestion de la base</h1>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="db-selector mb-4">
                <h4 class="mb-3">🗄️ Sélectionner une base de données</h4>
                <div class="row">
                    <?php foreach ($availableDatabases as $key => $db): ?>
                        <div class="col-md-6 mb-3">
                            <a href="?db=<?= $key ?>" class="db-option <?= $key === $selectedDb ? 'active' : '' ?>">
                                <h5 class="mb-2"><?= htmlspecialchars($db['name']) ?></h5>
                                <p class="mb-0 text-muted"><?= htmlspecialchars($db['description']) ?></p>
                                <?php if ($key === $selectedDb): ?>
                                    <span class="badge bg-success mt-2">✓ Sélectionnée</span>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="info-card">
                        <h5>📊 Informations - <?= htmlspecialchars($currentDb['name']) ?></h5>
                        <p class="mb-1"><strong>Fichier:</strong> <?= basename($dbFile) ?></p>
                        <p class="mb-1"><strong>Taille:</strong> <?= $dbSizeFormatted ?></p>
                        <p class="mb-0"><strong>Type:</strong> SQLite 3</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <h5>📋 Tables</h5>
                        <?php if (empty($tableStats)): ?>
                            <p class="text-muted mb-0">Aucune table</p>
                        <?php else: ?>
                            <?php foreach ($tableStats as $table => $count): ?>
                                <p class="mb-1"><strong><?= htmlspecialchars($table) ?>:</strong> <?= $count ?> ligne(s)</p>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <h4 class="mb-3">🔧 Actions sur <?= htmlspecialchars($currentDb['name']) ?></h4>
            <div class="row mb-4">
                <div class="col-md-4">
                    <form method="post">
                        <input type="hidden" name="action" value="backup">
                        <input type="hidden" name="target_db" value="<?= $selectedDb ?>">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf) ?>">
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            💾 Créer un backup
                        </button>
                    </form>
                </div>
                <div class="col-md-4">
                    <form method="post">
                        <input type="hidden" name="action" value="export_sql">
                        <input type="hidden" name="target_db" value="<?= $selectedDb ?>">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf) ?>">
                        <div class="form-check mb-1" style="font-size:.75rem;">
                            <input class="form-check-input" type="checkbox" value="1" id="anonymizeExport" name="anonymize">
                            <label class="form-check-label" for="anonymizeExport">Anonymiser emails</label>
                        </div>
                        <button type="submit" class="btn btn-info w-100 mb-2">
                            📤 Exporter SQL
                        </button>
                    </form>
                </div>
                <div class="col-md-4">
                    <form method="post" onsubmit="return confirm('Optimiser la base <?= htmlspecialchars($currentDb['name']) ?> (VACUUM) ?')">
                        <input type="hidden" name="action" value="vacuum">
                        <input type="hidden" name="target_db" value="<?= $selectedDb ?>">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf) ?>">
                        <button type="submit" class="btn btn-warning w-100 mb-2">
                            🧹 Optimiser (VACUUM)
                        </button>
                    </form>
                </div>
            </div>
            
            <h4 class="mb-3">📋 Consulter et modifier les données</h4>
            <div class="info-card mb-4">
                <form method="get" class="row g-3">
                    <input type="hidden" name="db" value="<?= $selectedDb ?>">
                    <div class="col-md-8">
                        <label for="table" class="form-label">Sélectionner une table</label>
                        <select name="table" id="table" class="form-select" style="background-color: #0b1d3d; color: #6fd3ff; border-color: #6fd3ff;" onchange="this.form.submit()">
                            <option value="">-- Choisir une table --</option>
                            <?php foreach (array_keys($tableStats) as $table): ?>
                                <option value="<?= htmlspecialchars($table) ?>" <?= $table === $selectedTable ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($table) ?> (<?= $tableStats[$table] ?> lignes)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <?php if ($selectedTable && !empty($tableData)): ?>
                <div class="table-responsive mb-4">
                    <table class="table table-hover table-sm" style="background-color: #0b1d3d; color: #6fd3ff;">
                        <thead style="background-color: #0a1930;">
                            <tr>
                                <?php foreach ($tableColumns as $col): ?>
                                    <?php if (strtolower($col) !== 'password_hash'): ?>
                                        <th><?= htmlspecialchars($col) ?></th>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableData as $row): ?>
                                <tr id="row-<?= $row['id'] ?? '' ?>" style="background-color: #0b1d3d; border-color: #6fd3ff;">
                                    <?php foreach ($tableColumns as $col): ?>
                                        <?php if (strtolower($col) !== 'password_hash'): ?>
                                            <td style="border-color: #1a3a5c;">
                                                <span class="view-mode"><?= htmlspecialchars($row[$col] ?? '') ?></span>
                                                <input type="text" class="form-control form-control-sm edit-mode d-none" 
                                                       style="background-color: #0a1930; color: #6fd3ff; border-color: #6fd3ff;"
                                                       data-column="<?= $col ?>" 
                                                       value="<?= htmlspecialchars($row[$col] ?? '') ?>">
                                            </td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <td style="border-color: #1a3a5c;">
                                        <button class="btn btn-sm btn-warning edit-btn" onclick="editRow(<?= $row['id'] ?? 0 ?>)">✏️</button>
                                        <button class="btn btn-sm btn-success save-btn d-none" onclick="saveRow(<?= $row['id'] ?? 0 ?>, '<?= $selectedTable ?>')">💾</button>
                                        <button class="btn btn-sm btn-secondary cancel-btn d-none" onclick="cancelEdit(<?= $row['id'] ?? 0 ?>)">✖️</button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette ligne ?')">
                                            <input type="hidden" name="action" value="delete_row">
                                            <input type="hidden" name="target_db" value="<?= $selectedDb ?>">
                                            <input type="hidden" name="table" value="<?= $selectedTable ?>">
                                            <input type="hidden" name="row_id" value="<?= $row['id'] ?? '' ?>">
                                            <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger delete-btn">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($selectedTable): ?>
                <div class="alert alert-info">Aucune donnée dans cette table.</div>
            <?php endif; ?>
            
            <h4 class="mb-3">📦 Backups existants</h4>
            <?php if (empty($backups)): ?>
                <div class="alert alert-info">Aucun backup trouvé.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nom du fichier</th>
                                <th>Taille</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><?= htmlspecialchars($backup['name']) ?></td>
                                    <td>
                                        <?= $backup['size'] > 1048576 ? round($backup['size'] / 1048576, 2) . ' MB' : round($backup['size'] / 1024, 2) . ' KB' ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i:s', $backup['date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($selectedDb === 'exercices'): ?>
                <hr class="my-4" />
                <h4 class="mb-3">➕ Ajouter un exercice</h4>
                <div class="info-card mb-4">
                    <form method="post" class="row g-3" enctype="multipart/form-data" onsubmit="return validateExForm(this)">
                        <input type="hidden" name="action" value="add_exercice">
                        <input type="hidden" name="target_db" value="exercices">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf) ?>">
                        <div class="col-md-6">
                            <label class="form-label">Nom de l'exercice *</label>
                            <input type="text" name="name" class="form-control" required style="background-color:#0a1930;color:#6fd3ff;border-color:#6fd3ff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Muscle ciblé *</label>
                            <input type="text" name="target_muscle" class="form-control" required style="background-color:#0a1930;color:#6fd3ff;border-color:#6fd3ff;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Muscles ciblés (détails)</label>
                            <input type="text" name="muscles_cibles" class="form-control" placeholder="ex: Pectoraux, Triceps, Deltoïdes" style="background-color:#0a1930;color:#6fd3ff;border-color:#6fd3ff;">
                            <small>Liste des muscles travaillés séparés par des virgules</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Photo (télécharger)</label>
                            <input type="file" name="photo_file" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control" style="background-color:#0a1930;color:#6fd3ff;border-color:#6fd3ff;" onchange="previewPhotoFile(this)">
                            <small>Max 5MB, formats: jpg, png, gif, webp</small>
                            <div class="mt-2" id="photoPreview"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">URL vidéo (optionnel)</label>
                            <input type="url" name="video_url" placeholder="ex: https://youtu.be/xxxxx" class="form-control" style="background-color:#0a1930;color:#6fd3ff;border-color:#6fd3ff;" oninput="previewVideo(this.value)">
                            <div class="mt-2" id="videoPreview"></div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success">Ajouter</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const selectedDb = '<?= $selectedDb ?>';
        function validateExForm(form){
            const name = form.name.value.trim();
            const muscle = form.target_muscle.value.trim();
            if(!name || !muscle){ alert('Nom et muscle sont requis'); return false; }
            const video = form.video_url ? form.video_url.value.trim() : '';
            if(video && !/^https?:\/\//i.test(video)){ alert('URL vidéo invalide'); return false; }
            const photo = form.photo_file && form.photo_file.files[0];
            if(photo && photo.size > 5*1024*1024){ alert('Photo trop volumineuse (max 5MB)'); return false; }
            return true;
        }
        function previewPhotoFile(input){
            const c = document.getElementById('photoPreview');
            if(!c) return;
            if(!input.files || !input.files[0]){ c.innerHTML=''; return; }
            const reader = new FileReader();
            reader.onload = function(e){
                c.innerHTML = `<img src="${e.target.result}" alt="preview" style="max-height:120px;border:1px solid #1a3a5c;border-radius:8px;">`;
            };
            reader.readAsDataURL(input.files[0]);
        }
        function previewVideo(url){
            const c = document.getElementById('videoPreview');
            if(!c) return;
            if(!url){ c.innerHTML=''; return; }
            let embed = '';
            const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/i);
            if(m){ embed = `<iframe width="320" height="180" src="https://www.youtube.com/embed/${m[1]}" frameborder="0" allowfullscreen></iframe>`; }
            else { embed = `<a href="${encodeURI(url)}" target="_blank" rel="noopener">Voir la vidéo</a>`; }
            c.innerHTML = embed;
        }
    </script>
    <script src="js/gestion_db.js"></script>
</body>
</html>
