<?php
require_once __DIR__.'/inc/auth.php';
require_login();

// Get user stats
require_once __DIR__ . '/config/db.php';
$userId = (int)($_SESSION['client_id'] ?? 0);

$dbPath = __DIR__ . '/services/sql/seances.db';
$stats = [
    'totalSeances' => 0,
    'totalWeight' => 0,
    'exercicesUniques' => 0,
    'muscleGroups' => []
];

if (file_exists($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Total sessions (distinct dates)
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT date) as count FROM seances WHERE user_id = :uid');
    $stmt->execute([':uid' => $userId]);
    $stats['totalSeances'] = (int)$stmt->fetchColumn();
    
    // Total weight lifted (sum of sets * reps * weight)
    $stmt = $pdo->prepare('SELECT SUM(sets * reps * weight_kg) as total FROM seances WHERE user_id = :uid');
    $stmt->execute([':uid' => $userId]);
    $stats['totalWeight'] = round((float)$stmt->fetchColumn(), 2);
    
    // Unique exercises
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT exercice_name) as count FROM seances WHERE user_id = :uid');
    $stmt->execute([':uid' => $userId]);
    $stats['exercicesUniques'] = (int)$stmt->fetchColumn();
    
    // Progress by muscle group (for chart)
    $stmt = $pdo->prepare('SELECT muscle_group, date, SUM(sets * reps * weight_kg) as volume FROM seances WHERE user_id = :uid AND muscle_group IS NOT NULL AND muscle_group != "" GROUP BY muscle_group, date ORDER BY date ASC');
    $stmt->execute([':uid' => $userId]);
    $muscleData = $stmt->fetchAll();
    
    // Organize by muscle
    foreach ($muscleData as $row) {
        $muscle = $row['muscle_group'];
        if (!isset($stats['muscleGroups'][$muscle])) {
            $stats['muscleGroups'][$muscle] = [];
        }
        $stats['muscleGroups'][$muscle][] = [
            'date' => $row['date'],
            'volume' => (float)$row['volume']
        ];
    }
}

include 'inc/header.php';
include 'inc/footer.php';
include 'inc/navbar.php';
$chartIds = [];
foreach ($stats['muscleGroups'] as $muscle => $data) {
    $chartIds[$muscle] = md5($muscle);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques — AscendForm</title>
    <link rel="icon" type="image/x-icon" href="media/logo_AscendForm.ico">
    <link rel="icon" type="image/png" href="media/logo_AscendForm.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/fond.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php navbar(); ?>
    
    <main class="container my-5">
        <div class="text-center mb-4">
            <h1 class="mb-2" style="font-size: 2.5rem; font-weight: 700; background: linear-gradient(135deg, #6fd3ff 0%, #667eea 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">📊 Statistiques</h1>
            <p style="color: rgba(255,255,255,0.7);">Suivez votre progression et vos performances</p>
        </div>
        
        <!-- Key metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm" style="background: linear-gradient(135deg, rgba(111, 211, 255, 0.08) 0%, rgba(102, 126, 234, 0.08) 100%); border: 1px solid rgba(111, 211, 255, 0.25); border-radius: 16px; padding: 1.75rem; text-align: center;">
                    <div style="font-size: 3rem; font-weight: 700; color: #6fd3ff;"><?= $stats['totalSeances'] ?></div>
                    <div style="font-size: 0.95rem; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.5rem;">🏋️ Séances totales</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm" style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.08) 0%, rgba(255, 170, 0, 0.08) 100%); border: 1px solid rgba(255, 215, 0, 0.25); border-radius: 16px; padding: 1.75rem; text-align: center;">
                    <div style="font-size: 3rem; font-weight: 700; color: #ffd700;"><?= number_format($stats['totalWeight'], 0, ',', ' ') ?> kg</div>
                    <div style="font-size: 0.95rem; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.5rem;">💪 Poids total soulevé</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm" style="background: linear-gradient(135deg, rgba(102, 255, 178, 0.08) 0%, rgba(50, 200, 150, 0.08) 100%); border: 1px solid rgba(102, 255, 178, 0.25); border-radius: 16px; padding: 1.75rem; text-align: center;">
                    <div style="font-size: 3rem; font-weight: 700; color: #66ffb2;"><?= $stats['exercicesUniques'] ?></div>
                    <div style="font-size: 0.95rem; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.5rem;">🎯 Exercices uniques</div>
                </div>
            </div>
        </div>
        
        <!-- Charts section -->
        <?php if (!empty($stats['muscleGroups'])): ?>
        <div class="row g-4 mb-4">
            <?php foreach ($stats['muscleGroups'] as $muscle => $data): ?>
            <div class="col-md-6">
                <div class="card shadow-sm" style="background: rgba(11, 29, 61, 0.5); backdrop-filter: blur(12px); border: 1px solid rgba(111, 211, 255, 0.22); border-radius: 16px; padding: 1.8rem;">
                    <h5 class="mb-3" style="color: #6fd3ff; font-weight: 600;">📈 Évolution — <?= htmlspecialchars($muscle, ENT_QUOTES, 'UTF-8') ?></h5>
                    <canvas id="chart-<?= md5($muscle) ?>" style="max-height: 250px;"></canvas>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card shadow-sm text-center" style="background: rgba(11, 29, 61, 0.5); backdrop-filter: blur(12px); border: 1px solid rgba(111, 211, 255, 0.22); border-radius: 16px; padding: 3rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">📊</div>
            <h5 style="color: #6fd3ff; margin-bottom: 1rem;">Aucune donnée pour le moment</h5>
            <p style="color: rgba(255,255,255,0.6);">Enregistrez vos premières séances pour voir vos statistiques et graphiques d'évolution.</p>
            <a href="index.php" class="btn btn-primary mt-3" style="background: linear-gradient(135deg, #667eea 0%, #6fd3ff 100%); border: none; padding: 0.7rem 2rem; border-radius: 10px; font-weight: 600;">Ajouter une séance</a>
        </div>
        <?php endif; ?>
    </main>
    
    <?php footer(); ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const muscleData = <?= json_encode($stats['muscleGroups']) ?>;
        const muscleChartIds = <?= json_encode($chartIds) ?>;
        
        Object.keys(muscleData).forEach(muscle => {
            const data = muscleData[muscle];
            const labels = data.map(d => d.date);
            const volumes = data.map(d => d.volume);
            
            const idSuffix = muscleChartIds[muscle];
            const canvasEl = document.getElementById('chart-' + idSuffix);
            if (!canvasEl) return; // safety
            const ctx = canvasEl.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Volume (kg)',
                        data: volumes,
                        borderColor: '#6fd3ff',
                        backgroundColor: 'rgba(111,211,255,0.2)',
                        tension: 0.3,
                        fill: true,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { 
                            ticks: { color: '#ccc', maxRotation: 45, autoSkip: true },
                            grid: { display: false }
                        },
                        y: { 
                            ticks: { color: '#ccc', precision: 0 },
                            grid: { color: 'rgba(255,255,255,0.08)' }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
