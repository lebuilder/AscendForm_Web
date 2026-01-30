<?php
// Gestion login/logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/controllers/auth.controllers.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/logs/logger.php';

// Sécurisation cookies de session (si non déjà définis avant session_start)
if (!headers_sent()) {
    $params = session_get_cookie_params();
    // On ne peut pas modifier directement après démarrage; si besoin future refactor dans bootstrap.
}

// CSRF token pour le formulaire de connexion
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

$logger = get_logger();
$GLOBALS['login_error'] = null;

// Logout optionnel
if (isset($_GET['logout'])) {
    $logger->log('logout', ['email' => $_SESSION['user_email'] ?? 'unknown']);
    auth_logout();
    header('Location: login.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['csrf_login'], (string)$_POST['_csrf'])) {
        $error = $GLOBALS['login_error'] = "Requête invalide (CSRF).";
    } else {
        // Rate limiting + délai adaptatif
        $pdo = db_get_pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, ip TEXT, success INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        // Table blocage IP
        $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_ips (id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT UNIQUE, blocked_until DATETIME, reason TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        // Purge anciennes tentatives (>1 jour) + blocks expirés
        $pdo->exec("DELETE FROM login_attempts WHERE created_at < datetime('now','-1 day')");
        $pdo->exec("DELETE FROM blocked_ips WHERE blocked_until <= datetime('now')");
        // Ajouter colonne locked_until si manquante
        $cols = $pdo->query("PRAGMA table_info(clients)")->fetchAll(PDO::FETCH_ASSOC);
        $hasLocked = false;
        foreach ($cols as $c) {
            if ($c['name'] === 'locked_until') {
                $hasLocked = true;
                break;
            }
        }
        if (!$hasLocked) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN locked_until DATETIME NULL");
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $emailInput = isset($_POST['email']) ? trim($_POST['email']) : '';
        // Vérifier blocage IP avant toute autre vérification
        $ipBlockStmt = $pdo->prepare("SELECT blocked_until FROM blocked_ips WHERE ip = :ip LIMIT 1");
        $ipBlockStmt->execute([':ip' => $ip]);
        $blockedUntilIp = $ipBlockStmt->fetchColumn();
        if ($blockedUntilIp && strtotime($blockedUntilIp) > time()) {
            $error = $GLOBALS['login_error'] = "IP bloquée jusqu'à " . date('H:i', strtotime($blockedUntilIp));
            $logger->logAuth('login', $emailInput, false, 'ip_blocked_active');
        }
        $rlStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND success = 0 AND created_at > datetime('now','-5 minutes')");
        $rlStmt->execute([':ip' => $ip]);
        $failedRecent = (int)$rlStmt->fetchColumn();
        if ($failedRecent >= 5) {
            $logger->logAuth('login', $emailInput, false, 'rate_limit');
            $error = $GLOBALS['login_error'] = "Trop de tentatives. Réessayez dans quelques minutes.";
        } else {
            // Délai adaptatif (à partir de 3 échecs récents)
            if ($failedRecent > 2) {
                sleep(min(5, $failedRecent - 2));
            }
            // Suite processing plus bas
            // Vérifier si compte verrouillé
            if ($emailInput !== '') {
                $lockStmt = $pdo->prepare("SELECT locked_until FROM clients WHERE email = :email LIMIT 1");
                $lockStmt->execute([':email' => $emailInput]);
                $lockedUntil = $lockStmt->fetchColumn();
                if ($lockedUntil && strtotime($lockedUntil) > time()) {
                    $error = $GLOBALS['login_error'] = "Compte verrouillé jusqu'à " . date('H:i', strtotime($lockedUntil));
                    $logger->logAuth('login', $emailInput, false, 'account_locked');
                }
            }
        }
    }
}
// On continue seulement si pas d'erreur préalable (CSRF / rate limit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['password'] ?? '';

    // Valider le format de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $GLOBALS['login_error'] = "Format d'email invalide. Veuillez inclure un '@' dans l'adresse email.";
    } else {
        try {
            // $pdo déjà récupéré ci-dessus si rate limit check, sinon maintenant
            $pdo = $pdo ?? db_get_pdo();
            $client = auth_login($pdo, $email, $password);
            if ($client) {
                $logger->logAuth('login', $email, true, null);
                // Enregistrer tentative réussie
                $attemptOk = $pdo->prepare('INSERT INTO login_attempts (email, ip, success) VALUES (:email,:ip,1)');
                $attemptOk->execute([':email' => $email, ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                // Nettoyer éventuel verrou
                $pdo->prepare('UPDATE clients SET locked_until = NULL WHERE email = :email')->execute([':email' => $email]);
                session_regenerate_id(true);

                // Si admin, rediriger vers la page admin
                if (!empty($_SESSION['is_admin'])) {
                    header('Location: views/admin/admin.php');
                    exit;
                }

                // Sinon, rediriger normalement
                $target = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                header('Location: ' . $target);
                exit;
            } else {
                $logger->logAuth('login', $email, false, 'Identifiants incorrects');
                // Enregistrer tentative échouée
                $attemptFail = $pdo->prepare('INSERT INTO login_attempts (email, ip, success) VALUES (:email,:ip,0)');
                $attemptFail->execute([':email' => $email, ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                $error = $GLOBALS['login_error'] = "Identifiants incorrects.";
                // Compter échecs sur les 30 dernières minutes pour cet email
                if ($email !== '') {
                    $countFailStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = :email AND success = 0 AND created_at > datetime('now','-30 minutes')");
                    $countFailStmt->execute([':email' => $email]);
                    $failsEmail = (int)$countFailStmt->fetchColumn();
                    if ($failsEmail >= 6) {
                        // Verrouiller 15 minutes
                        $pdo->prepare("UPDATE clients SET locked_until = datetime('now','+15 minutes') WHERE email = :email")->execute([':email' => $email]);
                        $logger->logAuth('login', $email, false, 'account_lock');
                        $error = $GLOBALS['login_error'] = "Trop d'échecs. Compte verrouillé 15 minutes.";
                    }
                }
                // Blocage IP si trop d'échecs sur 10 minutes
                $ipFailStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND success = 0 AND created_at > datetime('now','-10 minutes')");
                $ipFailStmt->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                $failsIpRecent = (int)$ipFailStmt->fetchColumn();
                if ($failsIpRecent >= 15) {
                    $pdo->prepare("INSERT OR REPLACE INTO blocked_ips (ip, blocked_until, reason) VALUES (:ip, datetime('now','+30 minutes'), 'too_many_failures')")
                        ->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    $logger->logAuth('login', $email, false, 'ip_block');
                    $error = $GLOBALS['login_error'] = "Trop d'échecs (IP). IP bloquée 30 minutes.";
                }
            }
        } catch (Throwable $e) {
            $error = $GLOBALS['login_error'] = 'Erreur de connexion: ' . $e->getMessage();
        }
    }
}

// Inclusion des fichiers d'en-tête, formulaire, pied de page et barre de navigation
include 'inc/header.php';
include 'inc/formulaire.php';
include 'inc/footer.php';
include 'inc/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AscendForm</title>
    <!-- Icônes du site : .ico préféré + fallback PNG -->
    <link rel="icon" type="image/x-icon" href="media/logo_AscendForm.ico">
    <link rel="icon" type="image/png" href="media/logo_AscendForm.png">
    <link rel="shortcut icon" href="media/logo_AscendForm.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/fond.css" />
    <link rel="stylesheet" href="css/navbar.css" />
    <link rel="stylesheet" href="css/login.css" />
</head>

<body>
    <!--Formulaire de connexion -->
    <div class="container my-4 d-flex justify-content-center">
        <?php formulaire_login(); ?>
    </div>
    <div class="container text-center mb-4">
        <a class="btn btn-outline-light btn-sm" href="register.php<?php echo isset($_GET['redirect']) ? ('?redirect=' . urlencode($_GET['redirect'])) : ''; ?>">Créer un compte</a>
    </div>
    <!--Footer-->
    <?php footer(); ?>
</body>

</html>