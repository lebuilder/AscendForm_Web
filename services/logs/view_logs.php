<?php
// AscendForm - Page de consultation des logs
session_start();

require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/logger.php';

require_login();

$logger = get_logger();
$logs = $logger->getRecentLogs(200);

// Filtres
$filterAction = $_GET['action'] ?? '';
$filterEmail = $_GET['email'] ?? '';

if ($filterAction || $filterEmail) {
    $logs = array_filter($logs, function($log) use ($filterAction, $filterEmail) {
        $matchAction = !$filterAction || $log['action'] === $filterAction;
        $matchEmail = !$filterEmail || stripos($log['email'], $filterEmail) !== false;
        return $matchAction && $matchEmail;
    });
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs d'activité - AscendForm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/fond.css">
    <style>
        body {
            color: white;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .logs-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .logs-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .filter-section {
            background: rgba(255, 255, 255, 0.03);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .log-entry {
            background: rgba(255, 255, 255, 0.03);
            border-left: 3px solid #4CAF50;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .log-entry:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(5px);
        }
        
        .log-entry.error {
            border-left-color: #f44336;
        }
        
        .log-entry.register {
            border-left-color: #2196F3;
        }
        
        .log-entry.logout {
            border-left-color: #FF9800;
        }
        
        .log-timestamp {
            color: #aaa;
            font-size: 0.85rem;
            margin-right: 1rem;
        }
        
        .log-action {
            font-weight: bold;
            text-transform: uppercase;
            margin-right: 1rem;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .log-email {
            color: #64B5F6;
        }
        
        .log-ip {
            color: #888;
            font-family: monospace;
            font-size: 0.85rem;
        }
        
        .log-data {
            margin-top: 0.5rem;
            padding-left: 1rem;
            color: #999;
            font-size: 0.8rem;
        }
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #4CAF50;
            color: white;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../inc/navbar.php'; ?>
    
    <div class="logs-container">
        <div class="logs-card">
            <h1 class="mb-4">📊 Logs d'activité</h1>
            
            <div class="filter-section">
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="action" class="form-label">Type d'action</label>
                        <select name="action" id="action" class="form-select">
                            <option value="">Toutes les actions</option>
                            <option value="login" <?= $filterAction === 'login' ? 'selected' : '' ?>>Login</option>
                            <option value="register" <?= $filterAction === 'register' ? 'selected' : '' ?>>Inscription</option>
                            <option value="logout" <?= $filterAction === 'logout' ? 'selected' : '' ?>>Déconnexion</option>
                            <option value="profile_update" <?= $filterAction === 'profile_update' ? 'selected' : '' ?>>Mise à jour profil</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">Email</label>
                        <input type="text" name="email" id="email" class="form-control" placeholder="Filtrer par email" value="<?= htmlspecialchars($filterEmail) ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="view_logs.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>
            
            <div class="mb-3">
                <strong><?= count($logs) ?></strong> événement(s) trouvé(s)
            </div>
            
            <div class="logs-list">
                <?php if (empty($logs)): ?>
                    <div class="alert alert-info">Aucun log disponible.</div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $actionClass = '';
                        if (isset($log['data']['success']) && !$log['data']['success']) {
                            $actionClass = 'error';
                        } elseif ($log['action'] === 'register') {
                            $actionClass = 'register';
                        } elseif ($log['action'] === 'logout') {
                            $actionClass = 'logout';
                        }
                        ?>
                        <div class="log-entry <?= $actionClass ?>">
                            <div>
                                <span class="log-timestamp"><?= htmlspecialchars($log['timestamp']) ?></span>
                                <span class="log-action"><?= htmlspecialchars($log['action']) ?></span>
                                <span class="log-email"><?= htmlspecialchars($log['email']) ?></span>
                                <span class="log-ip">[<?= htmlspecialchars($log['ip']) ?>]</span>
                            </div>
                            <?php if (!empty($log['data'])): ?>
                                <div class="log-data">
                                    <?php if (isset($log['data']['success'])): ?>
                                        Statut: <?= $log['data']['success'] ? '✅ Succès' : '❌ Échec' ?>
                                    <?php endif; ?>
                                    <?php if (isset($log['data']['error'])): ?>
                                        | Erreur: <?= htmlspecialchars($log['data']['error']) ?>
                                    <?php endif; ?>
                                    <?php if (isset($log['data']['changes'])): ?>
                                        | Champs modifiés: <?= htmlspecialchars(implode(', ', $log['data']['changes'])) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../../inc/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
