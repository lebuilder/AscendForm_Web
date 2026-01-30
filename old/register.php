<?php
// Page d'inscription
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__.'/controllers/auth.controllers.php';
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/services/logs/logger.php';

$logger = get_logger();

$error = null;
$GLOBALS['register_error'] = null;
// CSRF token pour inscription
if (empty($_SESSION['csrf_register'])) {
    $_SESSION['csrf_register'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['csrf_register'], (string)$_POST['_csrf'])) {
        $error = $GLOBALS['register_error'] = "Requête invalide (CSRF).";
    } else {
        // Rate limiting registration
        $pdoRL = db_get_pdo();
        $pdoRL->exec("CREATE TABLE IF NOT EXISTS register_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, ip TEXT, success INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        // Purge anciennes tentatives (>1 jour)
        $pdoRL->exec("DELETE FROM register_attempts WHERE created_at < datetime('now','-1 day')");
        $ipReg = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $emailCheck = trim($_POST['email'] ?? '');
        $limitStmt = $pdoRL->prepare("SELECT COUNT(*) FROM register_attempts WHERE ip = :ip AND success = 0 AND created_at > datetime('now','-10 minutes')");
        $limitStmt->execute([':ip'=>$ipReg]);
        $failRecent = (int)$limitStmt->fetchColumn();
        if ($failRecent >= 5) {
            $logger->logAuth('register', $emailCheck, false, 'rate_limit');
            $error = $GLOBALS['register_error'] = "Trop de tentatives d'inscription. Attendez quelques minutes.";
        } else {
            // Délai adaptatif léger après 3 échecs (max 4s)
            if ($failRecent >= 3) { sleep(min(4, $failRecent - 2)); }
            // Suite validations ci-dessous
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validations simples
    if (!$first || !$last) {
        $error = $GLOBALS['register_error'] = "Prénom et nom sont requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $GLOBALS['register_error'] = "Format d'email invalide. Veuillez inclure un '@' dans l'adresse email.";
    } elseif (strlen($password) < 8) {
        $error = $GLOBALS['register_error'] = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        try {
            $pdo = $pdo ?? db_get_pdo();
            $id = auth_create_client($pdo, [
                'email' => $email,
                'password' => $password,
                'first_name' => $first,
                'last_name' => $last,
            ]);
            
            $logger->logAuth('register', $email, true, null);
            $okStmt = $pdo->prepare('INSERT INTO register_attempts (email, ip, success) VALUES (:email,:ip,1)');
            $okStmt->execute([':email'=>$email, ':ip'=>$_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
            // Auto-login
            $client = auth_login($pdo, $email, $password);
            if ($client) {
                header('Location: profil.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = $GLOBALS['register_error'] = "Cet email est déjà utilisé.";
                $logger->logAuth('register', $email, false, 'Email déjà utilisé');
                $failStmt = $pdoRL->prepare('INSERT INTO register_attempts (email, ip, success) VALUES (:email,:ip,0)');
                $failStmt->execute([':email'=>$email, ':ip'=>$_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            } else {
                $error = $GLOBALS['register_error'] = 'Erreur: '.$e->getMessage();
                $logger->logAuth('register', $email, false, 'Erreur PDO: ' . $e->getMessage());
                $failStmt = $pdoRL->prepare('INSERT INTO register_attempts (email, ip, success) VALUES (:email,:ip,0)');
                $failStmt->execute([':email'=>$email, ':ip'=>$_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            }
        } catch (Throwable $e) {
            $error = $GLOBALS['register_error'] = 'Erreur: '.$e->getMessage();
            if (isset($pdoRL)) {
                $failStmt = $pdoRL->prepare('INSERT INTO register_attempts (email, ip, success) VALUES (:email,:ip,0)');
                $failStmt->execute([':email'=>$email, ':ip'=>$_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            }
        }
    }
}

// Includes UI
include 'inc/header.php';
include 'inc/formulaire.php';
include 'inc/footer.php';
include 'inc/navbar.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscription - AscendForm</title>
  <link rel="icon" type="image/x-icon" href="media/logo_AscendForm.ico">
  <link rel="icon" type="image/png" href="media/logo_AscendForm.png">
  <link rel="shortcut icon" href="media/logo_AscendForm.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/fond.css" />
  <link rel="stylesheet" href="css/navbar.css" />
  <link rel="stylesheet" href="css/login.css" />
</head>
<body>
  <?php navbar(); ?>

  <div class="container my-4 d-flex justify-content-center">
      <?php formulaire_register(); ?>
  </div>

  <?php footer(); ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
