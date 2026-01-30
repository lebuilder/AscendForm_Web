<?php
// AscendForm - Page d'administration principale
session_start();

require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../config/db.php';

require_admin();

$pdo = db_get_pdo();

// Message de succès du backup
$backupSuccessMessage = isset($_GET['backup_success']) ? $_GET['backup_success'] : null;

// CSRF admin pour actions sensibles (déblocages)
if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}
$adminCsrf = $_SESSION['csrf_admin'];

// Traitement actions POST (débloquer IP / compte)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $validToken = isset($_POST['_csrf_admin']) && hash_equals($_SESSION['csrf_admin'], (string)$_POST['_csrf_admin']);
    if (!$validToken) {
        http_response_code(403);
        echo 'CSRF invalide';
        exit;
    }
    $action = $_POST['action'];
    require_once __DIR__ . '/../../services/logs/logger.php';
    $logger = get_logger();
    if ($action === 'unlock_ip' && !empty($_POST['ip'])) {
        $ip = $_POST['ip'];
        $stmt = $pdo->prepare('DELETE FROM blocked_ips WHERE ip = :ip');
        $stmt->execute([':ip'=>$ip]);
        // logAdvanced signature: (action, message, data, level?) selon implémentation actuelle -> adapter à (action, message, data)
        $logger->logAdvanced('ip_unlock', 'Déblocage IP manuel', ['ip'=>$ip, 'by_admin'=>$_SESSION['client_id'] ?? null, 'request_id'=>bin2hex(random_bytes(8))]);
        header('Location: admin.php');
        exit;
    }
    if ($action === 'unlock_account' && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        $stmt = $pdo->prepare('UPDATE clients SET locked_until = NULL WHERE id = :id');
        $stmt->execute([':id'=>$uid]);
        $logger->logAdvanced('account_unlock', 'Déblocage compte manuel', ['user_id'=>$uid, 'by_admin'=>$_SESSION['client_id'] ?? null, 'request_id'=>bin2hex(random_bytes(8))]);
        header('Location: admin.php');
        exit;
    }
}

// Statistiques rapides
// Base stats
$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$totalAdmins = (int)$pdo->query('SELECT COUNT(*) FROM clients WHERE is_admin = 1')->fetchColumn();
$recentUsers = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE created_at > datetime('now', '-7 days')")->fetchColumn();
$activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE last_login_at > datetime('now', '-30 days')")->fetchColumn();

// Extended activity metrics
$activeDay = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE last_login_at > datetime('now', '-1 day')")->fetchColumn();
$activeWeek = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE last_login_at > datetime('now', '-7 days')")->fetchColumn();
$activeMonth = $activeUsers; // already 30 days
$retentionRate = $totalUsers ? round(($activeMonth / max($recentUsers,1)) * 100, 1) : 0; // simple proxy: active last 30d vs new 7d

// Latest users
$latestUsersStmt = $pdo->query("SELECT id, email, first_name, last_name, created_at FROM clients ORDER BY created_at DESC LIMIT 5");
$latestUsers = $latestUsersStmt->fetchAll();

// Data quality
$usersNoLogin = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE last_login_at IS NULL OR last_login_at = ''")->fetchColumn();

// Signup trend (last 14 days)
$signupTrend = [];
$trendSignupStmt = $pdo->query("SELECT substr(created_at,1,10) d, COUNT(*) c FROM clients WHERE created_at >= date('now','-13 days') GROUP BY d ORDER BY d");
foreach ($trendSignupStmt->fetchAll() as $row) { $signupTrend[$row['d']] = (int)$row['c']; }

// Login trend (last 14 days)
$loginTrend = [];
$trendLoginStmt = $pdo->query("SELECT substr(last_login_at,1,10) d, COUNT(*) c FROM clients WHERE last_login_at >= date('now','-13 days') GROUP BY d ORDER BY d");
foreach ($trendLoginStmt->fetchAll() as $row) { $loginTrend[$row['d']] = (int)$row['c']; }

// Ensure all 14 days present
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    if (!isset($signupTrend[$d])) $signupTrend[$d] = 0;
    if (!isset($loginTrend[$d])) $loginTrend[$d] = 0;
}

// System health: DB & backups
$sqlDir = __DIR__ . '/../../services/sql';
$availableDatabases = [
    'clients' => ['name' => 'Clients', 'file' => $sqlDir . '/clients.db'],
    'exercices' => ['name' => 'Exercices', 'file' => $sqlDir . '/exercices.db'],
    'messages' => ['name' => 'Messages', 'file' => $sqlDir . '/messages.db'],
    'seances' => ['name' => 'Séances', 'file' => $sqlDir . '/seances.db']
];

$dbStats = [];
$totalDbSize = 0;
foreach ($availableDatabases as $key => $db) {
    $size = file_exists($db['file']) ? filesize($db['file']) : 0;
    $totalDbSize += $size;
    $dbStats[$key] = [
        'name' => $db['name'],
        'size' => $size,
        'sizeHuman' => $size > 1048576 ? round($size/1048576,2).' MB' : round($size/1024,2).' KB'
    ];
}
$totalDbSizeHuman = $totalDbSize > 1048576 ? round($totalDbSize/1048576,2).' MB' : round($totalDbSize/1024,2).' KB';

$backupDir = __DIR__ . '/../../services/sql/backups';
$backupFiles = [];
if (is_dir($backupDir)) {
    foreach (glob($backupDir.'/*.db') as $bf) {
        $backupFiles[] = [ 'name'=>basename($bf), 'date'=>filemtime($bf), 'size'=>filesize($bf) ];
    }
    usort($backupFiles, fn($a,$b)=>$b['date'] <=> $a['date']);
}
$backupCount = count($backupFiles);
$lastBackup = $backupCount ? date('d/m/Y H:i', $backupFiles[0]['date']) : null;

// Logs & security/activity extraction
require_once __DIR__ . '/../../services/logs/logger.php';
$logger = get_logger();
$rawLogs = $logger->getRecentLogs(300);
$adminLogins = [];
$failedLogins = [];
$criticalActions = [];
$failedLastHour = 0;
$oneHourAgo = strtotime('-1 hour');
foreach ($rawLogs as $log) {
    $ts = strtotime($log['timestamp'] ?? '');
    if (($log['action'] ?? '') === 'login') {
        if (($log['data']['success'] ?? false) && isset($log['user_id'])) {
            // Determine if admin
            $isAdmin = false;
            if (isset($log['user_id'])) {
                $stmt = $pdo->prepare('SELECT is_admin FROM clients WHERE id = :id');
                $stmt->execute([':id'=>$log['user_id']]);
                $isAdmin = (int)$stmt->fetchColumn() === 1;
            }
            if ($isAdmin) { $adminLogins[] = $log; }
        } else {
            $failedLogins[] = $log;
            if ($ts >= $oneHourAgo) { $failedLastHour++; }
        }
    }
    if (in_array($log['action'] ?? '', ['profile_update','password_change','delete_user'])) {
        $criticalActions[] = $log;
    }
}
$adminLogins = array_slice($adminLogins,0,5);
$failedLogins = array_slice($failedLogins,0,5);
$criticalActions = array_slice($criticalActions,0,10);

// Tentatives de connexion: stats IP et comptes verrouillés
$pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, ip TEXT, success INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("DELETE FROM login_attempts WHERE created_at < datetime('now','-7 day')"); // purge douce hebdo
// S'assurer de l'existence de la colonne locked_until (peut ne pas être créée si aucune tentative de login depuis ajout)
$cols = $pdo->query("PRAGMA table_info(clients)")->fetchAll(PDO::FETCH_ASSOC);
$hasLocked = false; foreach ($cols as $c) { if ($c['name'] === 'locked_until') { $hasLocked = true; break; } }
if (!$hasLocked) {
    try { $pdo->exec("ALTER TABLE clients ADD COLUMN locked_until DATETIME NULL"); } catch (Throwable $e) { /* ignore si erreur */ }
}
// Table des IP bloquées (pour affichage admin)
$pdo->exec("CREATE TABLE IF NOT EXISTS blocked_ips (id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT UNIQUE, blocked_until DATETIME, reason TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$topFailIpsStmt = $pdo->query("SELECT ip, COUNT(*) c FROM login_attempts WHERE success = 0 AND created_at > datetime('now','-1 day') GROUP BY ip ORDER BY c DESC LIMIT 5");
$topFailIps = $topFailIpsStmt ? $topFailIpsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
$lockedAccountsStmt = $pdo->query("SELECT id,email,locked_until FROM clients WHERE locked_until IS NOT NULL AND locked_until > datetime('now') ORDER BY locked_until DESC LIMIT 5");
$lockedAccounts = $lockedAccountsStmt ? $lockedAccountsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
$lockedCount = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE locked_until IS NOT NULL AND locked_until > datetime('now')")->fetchColumn();

// IP bloquées actuellement
$blockedIpsStmt = $pdo->query("SELECT ip, blocked_until, reason FROM blocked_ips WHERE blocked_until > datetime('now') ORDER BY blocked_until DESC LIMIT 10");
$blockedIps = $blockedIpsStmt ? $blockedIpsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Tendance des échecs (par heure sur 24h)
$failTrendStmt = $pdo->query("SELECT strftime('%Y-%m-%d %H:00:00', created_at) h, COUNT(*) c FROM login_attempts WHERE success = 0 AND created_at >= datetime('now','-24 hours') GROUP BY h ORDER BY h");
$failTrendRaw = $failTrendStmt ? $failTrendStmt->fetchAll(PDO::FETCH_ASSOC) : [];
$failTrendMap = [];
foreach ($failTrendRaw as $r) { $failTrendMap[$r['h']] = (int)$r['c']; }
$failHours = [];
$failCounts = [];
for ($i = 23; $i >= 0; $i--) {
    $h = date('Y-m-d H:00:00', strtotime("-{$i} hours"));
    $label = date('H\h', strtotime($h));
    $failHours[] = $label;
    $failCounts[] = $failTrendMap[$h] ?? 0;
}

// Alerte IP si une IP a dépassé 15 échecs sur 10 minutes (informations live) déjà géré au niveau login mais surface si block actif
foreach ($blockedIps as $bip) {
    $alerts[] = "IP bloquée: " . htmlspecialchars($bip['ip']) . " jusqu'à " . date('H:i', strtotime($bip['blocked_until']));
}

// Simple alerts
$alerts = [];
if ($failedLastHour >= 5) {
    $alerts[] = 'Nombre élevé de tentatives échouées dans l\'heure ('.$failedLastHour.')';
}
if ($usersNoLogin > ($totalUsers * 0.7) && $totalUsers > 10) {
    $alerts[] = 'Beaucoup d\'utilisateurs sans connexion historique ('.$usersNoLogin.')';
}

// JSON for charts
$signupLabels = array_keys($signupTrend);
$signupValues = array_values($signupTrend);
$loginLabels = array_keys($loginTrend);
$loginValues = array_values($loginTrend);

// Logs récents
$logsFile = __DIR__ . '/../../services/logs/activity.log';
$recentLogs = 0;
if (file_exists($logsFile)) {
    $lines = file($logsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $recentLogs = count($lines);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - AscendForm</title>
    <link rel="icon" type="image/png" href="../../media/logo_AscendForm.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/fond.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container" style="padding-top: 2rem;">
        <?php if ($backupSuccessMessage): ?>
        <div class="alert alert-success" style="background: linear-gradient(135deg, rgba(102,255,178,0.15) 0%, rgba(102,255,178,0.08) 100%); border: 1px solid rgba(102,255,178,0.4); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(102,255,178,0.2);">
            <strong style="color: #66ffb2;">✅ Succès !</strong>
            <span style="color: rgba(255,255,255,0.9); margin-left: 0.5rem;"><?= htmlspecialchars($backupSuccessMessage) ?></span>
        </div>
        <?php endif; ?>
        
        <div class="admin-header position-relative mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1" style="font-size: 2.2rem; font-weight: 700; background: linear-gradient(135deg, #6fd3ff 0%, #667eea 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">🛡️ Administration</h1>
                    <p class="mb-0" style="color: rgba(255,255,255,0.7); font-size: 0.95rem;">Bienvenue, <strong style="color: #6fd3ff;"><?= htmlspecialchars($_SESSION['user_name']) ?></strong></p>
                </div>
                <a href="../../logout.php" class="btn btn-danger" style="box-shadow: 0 4px 12px rgba(255,50,50,0.3);">
                    ⏻ Déconnexion
                </a>
            </div>
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, rgba(111, 211, 255, 0.08) 0%, rgba(102, 126, 234, 0.08) 100%); border: 1px solid rgba(111, 211, 255, 0.25);">
                    <div class="stat-number" style="color: #6fd3ff;"><?= $totalUsers ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.6);">👥 Total Utilisateurs</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, rgba(255, 111, 97, 0.08) 0%, rgba(255, 150, 100, 0.08) 100%); border: 1px solid rgba(255, 111, 97, 0.25);">
                    <div class="stat-number" style="color: #ff6f61;"><?= $totalAdmins ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.6);">🛡️ Administrateurs</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, rgba(102, 255, 178, 0.08) 0%, rgba(50, 200, 150, 0.08) 100%); border: 1px solid rgba(102, 255, 178, 0.25);">
                    <div class="stat-number" style="color: #66ffb2;"><?= $recentUsers ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.6);">🆕 Nouveaux (7j)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.08) 0%, rgba(255, 170, 0, 0.08) 100%); border: 1px solid rgba(255, 215, 0, 0.25);">
                    <div class="stat-number" style="color: #ffd700;"><?= $activeUsers ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.6);">🔥 Actifs (30j)</div>
                </div>
            </div>
        </div>

        <!-- Extended metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-12">
                <div class="dashboard-section">
                    <div class="section-title mb-3">📊 Métriques d'engagement</div>
                    <div class="row g-3 text-center">
                        <div class="col-md-3">
                            <div style="font-size: 2rem; font-weight: 700; color: #6fd3ff;"><?= $activeDay ?></div>
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px;">🔥 Actifs 24h</div>
                        </div>
                        <div class="col-md-3">
                            <div style="font-size: 2rem; font-weight: 700; color: #667eea;"><?= $activeWeek ?></div>
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px;">🚀 Actifs 7j</div>
                        </div>
                        <div class="col-md-3">
                            <div style="font-size: 2rem; font-weight: 700; color: #ffd700;"><?= $activeMonth ?></div>
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px;">📅 Actifs 30j</div>
                        </div>
                        <div class="col-md-3">
                            <div style="font-size: 2rem; font-weight: 700; color: #66ffb2;"><?= $retentionRate ?>%</div>
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px;">♻️ Rétention</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="dashboard-section" style="min-height: 280px;">
                    <div class="section-title mb-3">🆕 Derniers inscrits</div>
                    <ul class="latest-users-list p-0 m-0">
                        <?php foreach ($latestUsers as $u): ?>
                            <li style="padding: 0.6rem 0.8rem; border-radius: 10px; background: rgba(111,211,255,0.08); margin-bottom: 0.6rem; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease;" onmouseover="this.style.background='rgba(111,211,255,0.15)'; this.style.transform='translateX(4px)';" onmouseout="this.style.background='rgba(111,211,255,0.08)'; this.style.transform='translateX(0)';">
                                <span style="font-size: 0.9rem; color: #e6f6ff;"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></span>
                                <a href="user_profile.php?id=<?= (int)$u['id'] ?>" style="color: #6fd3ff; text-decoration: none; font-size: 0.85rem; font-weight: 600;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';">Profil →</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="dashboard-section">
                    <div class="section-title mb-3">🧪 Qualité données</div>
                    <div style="background: rgba(255,255,255,0.03); border-radius: 10px; padding: 1rem; border: 1px solid rgba(111,211,255,0.15);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.7rem;">
                            <span style="font-size: 0.88rem; color: rgba(255,255,255,0.7);">Sans connexion</span>
                            <strong style="font-size: 1.1rem; color: #ffd700;"><?= $usersNoLogin ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.88rem; color: rgba(255,255,255,0.7);">Doublons email</span>
                            <strong style="font-size: 1.1rem; color: #66ffb2;">0</strong>
                        </div>
                        <div style="font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 0.5rem; text-align: center;">(contrainte UNIQUE)</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-section" style="min-height: 240px;">
                    <div class="section-title mb-3">💻 Santé système</div>
                    <div style="background: rgba(255,255,255,0.03); border-radius: 10px; padding: 1rem; border: 1px solid rgba(111,211,255,0.15); margin-bottom: 1rem;">
                        <div style="font-size: 0.75rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Bases de données</div>
                        <?php foreach ($dbStats as $stat): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.3rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="font-size: 0.8rem; color: rgba(255,255,255,0.7);"><?= htmlspecialchars($stat['name']) ?></span>
                            <strong style="font-size: 0.85rem; color: #6fd3ff;"><?= $stat['sizeHuman'] ?></strong>
                        </div>
                        <?php endforeach; ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.7rem; padding-top: 0.5rem; border-top: 2px solid rgba(111,211,255,0.3);">
                            <span style="font-size: 0.88rem; color: rgba(255,255,255,0.9); font-weight: 600;">Total</span>
                            <strong style="font-size: 1rem; color: #6fd3ff;"><?= $totalDbSizeHuman ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.7rem;">
                            <span style="font-size: 0.88rem; color: rgba(255,255,255,0.7);">Backups</span>
                            <strong style="font-size: 1rem; color: #66ffb2;"><?= $backupCount ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                            <span style="font-size: 0.88rem; color: rgba(255,255,255,0.7);">Dernier backup</span>
                            <strong style="font-size: 0.82rem; color: #ffd700;"><?= $lastBackup ? $lastBackup : 'Aucun' ?></strong>
                        </div>
                    </div>
                    <form method="post" action="gestion_db.php" class="d-grid">
                        <input type="hidden" name="action" value="backup_all">
                        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf) ?>">
                        <button class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #6fd3ff 100%); border: none; padding: 0.6rem; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 12px rgba(111,211,255,0.3); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 18px rgba(111,211,255,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(111,211,255,0.3)';">💾 Backup rapide (toutes BDD)</button>
                    </form>
                </div>
                <div class="dashboard-section">
                    <div class="section-title mb-3">🚨 Alertes</div>
                    <?php if (empty($alerts)): ?>
                        <div style="background: rgba(102,255,178,0.08); border: 1px solid rgba(102,255,178,0.3); border-radius: 10px; padding: 1rem; text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">✓</div>
                            <p style="font-size: 0.85rem; color: #66ffb2; margin: 0;">Aucune alerte</p>
                        </div>
                    <?php else: foreach ($alerts as $a): ?>
                        <div style="background: rgba(255,80,80,0.1); border: 1px solid rgba(255,80,80,0.4); border-radius: 10px; padding: 0.9rem 1.1rem; margin-bottom: 0.7rem; display: flex; align-items: flex-start; gap: 0.7rem;">
                            <span style="font-size: 1.2rem;">⚠️</span>
                            <div>
                                <strong style="color: #ff6d6d; font-size: 0.9rem;">Alerte:</strong>
                                <span style="color: #ffb3b3; font-size: 0.88rem; display: block; margin-top: 0.2rem;"><?= htmlspecialchars($a) ?></span>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-section" style="min-height: 280px;">
                    <div class="section-title mb-3">🔐 Sécurité (admin)</div>
                    <div style="margin-bottom: 1.2rem;">
                        <div style="font-size: 0.8rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.6rem;">Derniers logins admin</div>
                        <?php foreach ($adminLogins as $l): ?>
                            <div style="font-size: 0.8rem; padding: 0.5rem 0.7rem; border-left: 3px solid #66ffb2; background: rgba(102,255,178,0.08); margin-bottom: 0.4rem; border-radius: 6px;">
                                <span style="background: #66ffb2; color: #0a1930; padding: 0.15rem 0.5rem; border-radius: 12px; font-weight: 700; font-size: 0.7rem; margin-right: 0.5rem;">login</span>
                                <span style="color: rgba(255,255,255,0.7);"><?= htmlspecialchars($l['timestamp']) ?> - ID <?= (int)$l['user_id'] ?></span>
                            </div>
                        <?php endforeach; if (empty($adminLogins)): ?>
                            <div style="font-size: 0.8rem; color: rgba(255,255,255,0.4); padding: 0.5rem; text-align: center; background: rgba(255,255,255,0.02); border-radius: 6px;">Aucun</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.6rem;">Échecs récents</div>
                        <?php foreach ($failedLogins as $l): ?>
                            <div style="font-size: 0.8rem; padding: 0.5rem 0.7rem; border-left: 3px solid #ff6d6d; background: rgba(255,50,50,0.08); margin-bottom: 0.4rem; border-radius: 6px;">
                                <span style="background: #ff6d6d; color: #0a1930; padding: 0.15rem 0.5rem; border-radius: 12px; font-weight: 700; font-size: 0.7rem; margin-right: 0.5rem;">fail</span>
                                <span style="color: rgba(255,255,255,0.7);"><?= htmlspecialchars($l['timestamp']) ?> - <?= htmlspecialchars($l['data']['email'] ?? '') ?></span>
                            </div>
                        <?php endforeach; if (empty($failedLogins)): ?>
                            <div style="font-size: 0.8rem; color: rgba(255,255,255,0.4); padding: 0.5rem; text-align: center; background: rgba(255,255,255,0.02); border-radius: 6px;">Aucun</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dashboard-section">
                    <div class="section-title mb-3">🛠️ Actions rapides</div>
                    <div class="d-grid gap-2">
                        <a href="gestion_utilisateurs.php?create=admin" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.7rem; border-radius: 10px; font-weight: 600; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(102,126,234,0.3); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 18px rgba(102,126,234,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(102,126,234,0.3)';">➕ Créer Admin</a>
                        <a href="gestion_db.php" class="btn" style="background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%); color: #0a1930; border: none; padding: 0.7rem; border-radius: 10px; font-weight: 600; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(255,215,0,0.3); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 18px rgba(255,215,0,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(255,215,0,0.3)';">🧹 VACUUM</a>
                        <a href="dashboard/views_logs.php" class="btn" style="background: linear-gradient(135deg, #6fd3ff 0%, #4a9fd8 100%); color: #0a1930; border: none; padding: 0.7rem; border-radius: 10px; font-weight: 600; text-decoration: none; text-align: center; box-shadow: 0 4px 12px rgba(111,211,255,0.3); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 18px rgba(111,211,255,0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(111,211,255,0.3)';">📋 Voir logs</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="dashboard-section">
                    <div class="section-title mb-3">🚫 IP - Échecs (24h)</div>
                    <?php if (empty($topFailIps)): ?>
                        <p style="font-size:.75rem;" class="text-muted mb-0">Aucun échec enregistré</p>
                    <?php else: ?>
                        <?php foreach ($topFailIps as $ipRow): ?>
                            <div class="log-item warn"><span class="action">fail_ip</span> <?= htmlspecialchars($ipRow['ip'] ?? 'n/a') ?> - <?= (int)$ipRow['c'] ?> échecs</div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="dashboard-section">
                    <div class="section-title mb-3">🔒 Comptes verrouillés</div>
                    <p style="font-size:.7rem;" class="mb-1">Actuellement: <strong><?= $lockedCount ?></strong></p>
                    <?php if (empty($lockedAccounts)): ?>
                        <p style="font-size:.75rem;" class="text-muted mb-0">Aucun compte verrouillé</p>
                    <?php else: ?>
                        <?php foreach ($lockedAccounts as $acc): ?>
                            <div class="log-item security d-flex justify-content-between align-items-center">
                                <div><span class="action">locked</span> <?= htmlspecialchars($acc['email']) ?> jusqu'à <?= htmlspecialchars(date('H:i', strtotime($acc['locked_until']))) ?></div>
                                <form method="post" class="d-inline ms-2">
                                    <input type="hidden" name="_csrf_admin" value="<?= htmlspecialchars($_SESSION['csrf_admin'] ?? '') ?>">
                                    <input type="hidden" name="action" value="unlock_account">
                                    <input type="hidden" name="user_id" value="<?= (int)$acc['id'] ?>">
                                    <button class="btn btn-sm btn-outline-light">Débloquer</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="mt-2">
                        <a href="gestion_utilisateurs.php" class="btn btn-sm btn-secondary">🔓 Gérer les comptes</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="dashboard-section">
                    <div class="section-title mb-3">📈 Inscriptions (14j)</div>
                    <div class="mini-chart-wrapper"><canvas id="signupChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="dashboard-section">
                    <div class="section-title mb-3">🔑 Connexions (14j)</div>
                    <div class="mini-chart-wrapper"><canvas id="loginChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="dashboard-section">
                    <div class="section-title mb-3">⚠️ Échecs login / heure (24h)</div>
                    <div class="mini-chart-wrapper"><canvas id="failChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="dashboard-section">
                    <div class="section-title mb-3">🧱 IPs bloquées</div>
                    <?php if (empty($blockedIps)): ?>
                        <p style="font-size:.75rem;" class="text-muted mb-0">Aucune IP bloquée</p>
                    <?php else: foreach ($blockedIps as $row): ?>
                        <div class="log-item security d-flex justify-content-between align-items-center">
                            <div><span class="action">ip_block</span> <?= htmlspecialchars($row['ip']) ?> jusqu'à <?= htmlspecialchars(date('H:i', strtotime($row['blocked_until']))) ?></div>
                            <form method="post" class="d-inline ms-2">
                                <input type="hidden" name="_csrf_admin" value="<?= htmlspecialchars($_SESSION['csrf_admin'] ?? '') ?>">
                                <input type="hidden" name="action" value="unlock_ip">
                                <input type="hidden" name="ip" value="<?= htmlspecialchars($row['ip']) ?>">
                                <button class="btn btn-sm btn-outline-light">Débloquer</button>
                            </form>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6">
                <a href="gestion_utilisateurs.php" class="admin-menu-card">
                    <span class="admin-menu-icon">👥</span>
                    <div class="admin-menu-title">Gestion des utilisateurs</div>
                    <div class="admin-menu-desc">Consulter, modifier, supprimer les comptes utilisateurs</div>
                </a>
            </div>
            
            <div class="col-md-6">
                <a href="gestion_db.php" class="admin-menu-card">
                    <span class="admin-menu-icon">💾</span>
                    <div class="admin-menu-title">Gestion de la base</div>
                    <div class="admin-menu-desc">Export, backup, maintenance de la base SQLite</div>
                </a>
            </div>
            
            <div class="col-md-6">
                <a href="dashboard/views_stats.php" class="admin-menu-card">
                    <span class="admin-menu-icon">📊</span>
                    <div class="admin-menu-title">Statistiques</div>
                    <div class="admin-menu-desc">Graphiques et analyses détaillées</div>
                </a>
            </div>
            
            <div class="col-md-6">
                <a href="dashboard/views_logs.php" class="admin-menu-card">
                    <span class="admin-menu-icon">📋</span>
                    <div class="admin-menu-title">Logs d'activité</div>
                    <div class="admin-menu-desc">Historique des connexions et actions (<?= $recentLogs ?> événements)</div>
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const signupData = {
            labels: <?= json_encode($signupLabels) ?>,
            datasets: [{
                data: <?= json_encode($signupValues) ?>,
                borderColor: '#6fd3ff',
                backgroundColor: 'rgba(111,211,255,0.25)',
                tension: .3,
                fill: true,
            }]
        };
        const loginData = {
            labels: <?= json_encode($loginLabels) ?>,
            datasets: [{
                data: <?= json_encode($loginValues) ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102,126,234,0.25)',
                tension: .3,
                fill: true,
            }]
        };
        function makeMiniChart(id, data) {
            const ctx = document.getElementById(id).getContext('2d');
            return new Chart(ctx, {
                type: 'line',
                data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color:'#ccc', maxRotation:0, autoSkip:true }, grid:{ display:false } },
                        y: { ticks: { color:'#ccc', precision:0 }, grid:{ color:'rgba(255,255,255,0.08)' } }
                    },
                    elements: { point: { radius: 2 } }
                }
            });
        }
        makeMiniChart('signupChart', signupData);
        makeMiniChart('loginChart', loginData);
        // Failed attempts chart
        const failData = {
            labels: <?= json_encode($failHours) ?>,
            datasets: [{
                data: <?= json_encode($failCounts) ?>,
                borderColor: '#ff6d6d',
                backgroundColor: 'rgba(255,109,109,0.25)',
                tension: .3,
                fill: true,
            }]
        };
        makeMiniChart('failChart', failData);
    </script>
</body>
</html>
