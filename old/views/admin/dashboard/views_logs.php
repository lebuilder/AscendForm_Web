<?php
// AscendForm - Consultation des logs depuis admin dashboard
session_start();

require_once __DIR__ . '/../../../inc/auth.php';
require_once __DIR__ . '/../../../services/logs/logger.php';

require_admin();

$logger = get_logger();
// Charge les logs selon le niveau demandé: ADMIN => fichier admin_activity.log, sinon activity.log
$filterLevel = $_GET['level'] ?? '';
if ($filterLevel === 'ADMIN') {
    $adminLogFile = __DIR__ . '/../../../services/logs/admin_activity.log';
    $logs = [];
    if (file_exists($adminLogFile)) {
        $lines = @file($adminLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            // Prendre les 800 derniers pour cohérence
            $lines = array_slice($lines, -800);
            foreach (array_reverse($lines) as $line) {
                $dec = json_decode($line, true);
                if (is_array($dec)) { $logs[] = $dec; }
            }
        }
    }
} else {
    $logs = $logger->getRecentLogs(600); // fetch more for client-side filtering
}
$totalLogsCount = count($logs);

// Server pre-filter (optional simple GET)
$filterAction = $_GET['action'] ?? '';
$filterEmail = $_GET['email'] ?? '';

if ($filterAction || $filterEmail || $filterLevel) {
    $logs = array_filter($logs, function($log) use ($filterAction, $filterEmail, $filterLevel) {
        $lvl = $log['level'] ?? 'INFO';
        $matchAction = !$filterAction || ($log['action'] ?? '') === $filterAction;
        $matchEmail = !$filterEmail || (isset($log['email']) && stripos($log['email'], $filterEmail) !== false);
        $matchLevel = !$filterLevel || $lvl === $filterLevel;
        return $matchAction && $matchEmail && $matchLevel;
    });
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs d'activité - AscendForm Admin</title>
    <link rel="icon" type="image/png" href="../../../media/logo_AscendForm.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../css/fond.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/views_logs.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-card mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">📋 Logs d'activité</h1>
                <a href="../admin.php" class="back-btn">← Retour</a>
            </div>
            
            <div class="logs-toolbar">
                <div class="badge-count" id="countBadge"><strong><?= count($logs) ?></strong> / <?= $totalLogsCount ?> événements</div>
                <div class="filter-inline">
                    <select id="filterLevel">
                        <option value="">Niveau: Tous</option>
                        <option value="INFO">INFO</option>
                        <option value="WARN">WARN</option>
                        <option value="ERROR">ERROR</option>
                        <option value="SECURITY">SECURITY</option>
                        <option value="AUDIT">AUDIT</option>
                        <option value="ADMIN" <?= ($filterLevel==='ADMIN')?'selected':'' ?>>ADMIN</option>
                    </select>
                    <select id="filterAction">
                        <option value="">Action: Toutes</option>
                        <?php
                        // Build action list dynamically
                        $actions = array_unique(array_map(fn($l)=>$l['action'] ?? '', $logs));
                        sort($actions);
                        foreach ($actions as $ac) {
                            if ($ac === '') continue;
                            echo '<option value="'.htmlspecialchars($ac).'">'.htmlspecialchars($ac).'</option>';
                        }
                        ?>
                    </select>
                    <input type="text" id="filterEmail" placeholder="Email contient" value="<?= htmlspecialchars($filterEmail) ?>" />
                    <button type="button" class="btn btn-sm btn-primary" id="applyFilters">Appliquer</button>
                    <button type="button" class="btn btn-sm btn-secondary" id="resetFilters">Réinitialiser</button>
                    <button type="button" class="toggle-raw-btn" id="toggleRaw">Vue JSON</button>
                    <form method="post" action="../../../services/logs/backup_logs.php" class="d-inline ms-2">
                        <button type="submit" class="btn btn-sm btn-warning">💾 Backup logs</button>
                    </form>
                    <form method="post" action="../../../services/logs/backup_admin_logs.php" class="d-inline ms-2">
                        <button type="submit" class="btn btn-sm btn-danger">🛡️ Backup logs admin</button>
                    </form>
                </div>
            </div>
            
            <div class="logs-list" id="logsList" data-logs='<?= json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>'>
                <?php if (empty($logs)): ?>
                    <div class="alert alert-info">Aucun log disponible.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/views_logs.js"></script>
</body>
</html>
