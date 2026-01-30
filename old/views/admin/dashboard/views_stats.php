<?php
// AscendForm - Statistiques détaillées (Admin Dashboard)
session_start();

require_once __DIR__ . '/../../../inc/auth.php';
require_once __DIR__ . '/../../../config/db.php';

require_admin();

$pdo = db_get_pdo();

// Stats globales
$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$totalAdmins = (int)$pdo->query('SELECT COUNT(*) FROM clients WHERE is_admin = 1')->fetchColumn();

// Générer les 30 derniers jours avec compteurs à zéro
$registrationsByDay = [];
$loginsByDay = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $registrationsByDay[$date] = 0;
    $loginsByDay[$date] = 0;
}

// Inscriptions par jour (30 derniers jours)
$registrations = $pdo->query("
    SELECT DATE(created_at) as day, COUNT(*) as count
    FROM clients
    WHERE created_at > datetime('now', '-30 days')
    GROUP BY DATE(created_at)
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($registrations as $reg) {
    if (isset($registrationsByDay[$reg['day']])) {
        $registrationsByDay[$reg['day']] = (int)$reg['count'];
    }
}

// Connexions par jour (30 derniers jours via logs)
$logsFile = __DIR__ . '/../../../services/logs/activity.log';
if (file_exists($logsFile)) {
    $lines = file($logsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $log = json_decode($line, true);
        if ($log && $log['action'] === 'login' && isset($log['data']['success']) && $log['data']['success']) {
            $day = substr($log['timestamp'], 0, 10);
            if (isset($loginsByDay[$day])) {
                $loginsByDay[$day]++;
            }
        }
    }
}

// Utilisateurs jamais connectés
$neverLoggedIn = (int)$pdo->query('SELECT COUNT(*) FROM clients WHERE last_login_at IS NULL')->fetchColumn();

// Stats des logs
$totalLogs = 0;
if (file_exists($logsFile)) {
    $lines = file($logsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $totalLogs = count($lines);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - AscendForm Admin</title>
    <link rel="icon" type="image/png" href="../../../media/logo_AscendForm.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../../css/fond.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/views_stats.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="admin-container">
        <div class="admin-card mx-auto">
            <div class="mb-4 text-center position-relative">
                <a href="../admin.php" class="back-btn position-absolute top-0 end-0">← Retour</a>
                <h1 class="mb-0">📊 Statistiques</h1>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $totalUsers ?></div>
                        <div class="stat-label">Total Utilisateurs</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $totalAdmins ?></div>
                        <div class="stat-label">Administrateurs</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $neverLoggedIn ?></div>
                        <div class="stat-label">Jamais connectés</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= $totalLogs ?></div>
                        <div class="stat-label">Total Logs</div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <h4 class="mb-3">Inscriptions (30 derniers jours)</h4>
                    <div class="chart-container">
                        <canvas id="registrationsChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <h4 class="mb-3">Connexions (30 derniers jours)</h4>
                    <div class="chart-container">
                        <canvas id="loginsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Données PHP -> JS
        const registrationsByDay = <?= json_encode($registrationsByDay) ?>;
        const loginsByDay = <?= json_encode($loginsByDay) ?>;
    </script>
    <script src="../js/views_stats.js"></script>
    <script>
        // Initialiser les graphiques avec les données
        initCharts(registrationsByDay, loginsByDay);
    </script>
</body>
</html>
