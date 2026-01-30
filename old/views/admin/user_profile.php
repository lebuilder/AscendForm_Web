<?php
// AscendForm - Page profil utilisateur (Admin)
session_start();
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/logs/logger.php';
require_admin();

$pdo = db_get_pdo();
$logger = get_logger();

// CSRF token
if (empty($_SESSION['csrf_user_profile'])) {
    $_SESSION['csrf_user_profile'] = bin2hex(random_bytes(32));
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    echo 'Utilisateur invalide';
    exit;
}

// Load user
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(404);
    echo 'Utilisateur introuvable';
    exit;
}

$message = null; $error = null;

// Handle POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validToken = isset($_POST['_csrf']) && hash_equals($_SESSION['csrf_user_profile'], (string)$_POST['_csrf']);
    if (!$validToken) {
        $error = 'CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? 'update';
        try {
            if ($action === 'update') {
                $email = trim($_POST['email'] ?? '');
                $first = trim($_POST['first_name'] ?? '');
                $last = trim($_POST['last_name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $birthdate = trim($_POST['birthdate'] ?? '');
                $height = $_POST['height_cm'] !== '' ? (int)$_POST['height_cm'] : null;
                $weight = $_POST['weight_kg'] !== '' ? (int)$_POST['weight_kg'] : null;
                $isAdmin = isset($_POST['is_admin']) ? 1 : 0;

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email invalide');
                }
                // Email unique
                $check = $pdo->prepare('SELECT id FROM clients WHERE email = :email AND id != :id');
                $check->execute([':email'=>$email, ':id'=>$userId]);
                if ($check->fetch()) { throw new Exception('Email déjà utilisé'); }

                $before = $user;
                $stmtUp = $pdo->prepare('UPDATE clients SET email = :email, first_name = :first, last_name = :last, phone = :phone, birthdate = :birthdate, height_cm = :h, weight_kg = :w, is_admin = :admin, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $stmtUp->execute([
                    ':email'=>$email, ':first'=>$first, ':last'=>$last, ':phone'=>$phone ?: null, ':birthdate'=>$birthdate ?: null,
                    ':h'=>$height, ':w'=>$weight, ':admin'=>$isAdmin, ':id'=>$userId
                ]);
                $afterStmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id');
                $afterStmt->execute([':id'=>$userId]);
                $user = $afterStmt->fetch(PDO::FETCH_ASSOC);
                $logger->logAdvanced('admin_user_update', 'Mise à jour profil admin', ['user_id'=>$userId,'before'=>$before,'after'=>$user]);
                $message = 'Profil mis à jour.';
            } elseif ($action === 'reset_password') {
                $newPass = bin2hex(random_bytes(4)); // 8 hex chars
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE clients SET password_hash = :h, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute([':h'=>$hash, ':id'=>$userId]);
                $logger->logAdvanced('admin_user_password_reset', 'Réinitialisation mot de passe', ['user_id'=>$userId]);
                $message = 'Nouveau mot de passe: '.$newPass;
            } elseif ($action === 'unlock_account') {
                $pdo->prepare('UPDATE clients SET locked_until = NULL WHERE id = :id')->execute([':id'=>$userId]);
                $logger->logAdvanced('admin_user_unlock', 'Déblocage compte', ['user_id'=>$userId]);
                $message = 'Compte débloqué.';
            }
        } catch (Throwable $e) {
            $error = 'Erreur: '.$e->getMessage();
        }
    }
}

// Recent logs for user
$logs = $logger->getRecentLogs(200);
$userLogs = [];
foreach ($logs as $l) {
    if (isset($l['user_id']) && (int)$l['user_id'] === $userId) {
        $userLogs[] = $l;
    } elseif (($l['data']['email'] ?? null) === $user['email']) {
        $userLogs[] = $l;
    }
    if (count($userLogs) >= 30) break;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Profil utilisateur #<?= htmlspecialchars($userId) ?> - Admin</title>
<link rel="icon" type="image/png" href="../../media/logo_AscendForm.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../../css/fond.css">
<link rel="stylesheet" href="css/admin.css">
<link rel="stylesheet" href="css/user_profile.css">
</head>
<body>
<div class="admin-container" style="padding-top:2.5rem; max-width:1200px;">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h1 class="mb-0">👤 Profil utilisateur</h1>
        <a href="admin.php" class="btn btn-outline-light btn-sm">← Retour Admin</a>
    </div>
    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="dashboard-section">
                <div class="section-title">✏️ Éditer le profil</div>
                <form method="post" class="row g-3">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_user_profile'] ?? '') ?>">
                    <input type="hidden" name="action" value="update">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Prénom</label>
                        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nom</label>
                        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date naissance</label>
                        <input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($user['birthdate'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Taille (cm)</label>
                        <input type="number" name="height_cm" class="form-control" value="<?= htmlspecialchars($user['height_cm'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Poids (kg)</label>
                        <input type="number" name="weight_kg" class="form-control" value="<?= htmlspecialchars($user['weight_kg'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_admin" id="is_admin" <?= !empty($user['is_admin']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_admin">Administrateur</label>
                        </div>
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-primary" type="submit">💾 Enregistrer</button>
                    </div>
                </form>
            </div>
            <div class="dashboard-section">
                <div class="section-title">🔐 Sécurité</div>
                <form method="post" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_user_profile'] ?? '') ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <button class="btn btn-warning" onclick="return confirm('Réinitialiser le mot de passe ?')">🔄 Réinitialiser MDP</button>
                </form>
                <form method="post" class="d-flex gap-2 flex-wrap mt-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_user_profile'] ?? '') ?>">
                    <input type="hidden" name="action" value="unlock_account">
                    <button class="btn btn-secondary" <?= empty($user['locked_until']) ? 'disabled' : '' ?>>🔓 Débloquer compte</button>
                </form>
                <?php if (!empty($user['locked_until'])): ?>
                    <p class="mt-2" style="font-size:.75rem;">Verrouillé jusqu'à <?= htmlspecialchars($user['locked_until']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-5">
            <div class="dashboard-section">
                <div class="section-title">📜 Dernières actions</div>
                <?php if (empty($userLogs)): ?>
                    <p class="text-muted" style="font-size:.75rem;">Aucune action récente</p>
                <?php else: ?>
                    <div style="max-height:420px; overflow-y:auto;" class="small">
                        <?php foreach ($userLogs as $log): ?>
                            <div class="log-item <?= ($log['level'] ?? '') === 'SECURITY' ? 'security' : '' ?>">
                                <span class="action"><?= htmlspecialchars($log['action'] ?? '') ?></span>
                                <?= htmlspecialchars($log['timestamp'] ?? '') ?>
                                <?php if (isset($log['data']['email'])): ?> - <?= htmlspecialchars($log['data']['email']) ?><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="dashboard-section">
                <div class="section-title">ℹ️ Métadonnées</div>
                <p style="font-size:.75rem;" class="mb-1">Créé le: <strong><?= htmlspecialchars($user['created_at']) ?></strong></p>
                <p style="font-size:.75rem;" class="mb-1">Dernière connexion: <strong><?= htmlspecialchars($user['last_login_at'] ?? 'Jamais') ?></strong></p>
                <p style="font-size:.75rem;" class="mb-0">MAJ: <strong><?= htmlspecialchars($user['updated_at'] ?? '') ?></strong></p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
