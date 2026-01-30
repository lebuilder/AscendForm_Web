<?php
// AscendForm - Gestion des utilisateurs (Admin)
session_start();

require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../config/db.php';

require_admin();

$pdo = db_get_pdo();

// Actions CRUD
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'delete' && !empty($_POST['user_id'])) {
            $userId = (int)$_POST['user_id'];
            // Empêcher suppression de soi-même
            if ($userId === $_SESSION['client_id']) {
                $error = "Vous ne pouvez pas supprimer votre propre compte.";
            } else {
                $stmt = $pdo->prepare('DELETE FROM clients WHERE id = :id');
                $stmt->execute([':id' => $userId]);
                $message = "Utilisateur supprimé avec succès.";
            }
        } elseif ($action === 'toggle_admin' && !empty($_POST['user_id'])) {
            $userId = (int)$_POST['user_id'];
            $currentAdmin = (int)$_POST['current_admin'];
            $newAdmin = $currentAdmin ? 0 : 1;
            
            // Empêcher de se retirer soi-même les droits admin
            if ($userId === $_SESSION['client_id'] && $newAdmin === 0) {
                $error = "Vous ne pouvez pas retirer vos propres droits admin.";
            } else {
                $stmt = $pdo->prepare('UPDATE clients SET is_admin = :admin WHERE id = :id');
                $stmt->execute([':admin' => $newAdmin, ':id' => $userId]);
                $message = "Droits administrateur mis à jour.";
            }
        } elseif ($action === 'reset_password' && !empty($_POST['user_id'])) {
            $userId = (int)$_POST['user_id'];
            $newPassword = 'password123'; // Mot de passe par défaut
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare('UPDATE clients SET password_hash = :hash WHERE id = :id');
            $stmt->execute([':hash' => $hash, ':id' => $userId]);
            $message = "Mot de passe réinitialisé à: {$newPassword}";
        }
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Filtres
$search = $_GET['search'] ?? '';
$filterAdmin = $_GET['filter_admin'] ?? '';

// Récupération des utilisateurs
$sql = 'SELECT * FROM clients WHERE 1=1';
$params = [];

if ($search) {
    $sql .= ' AND (email LIKE :search OR first_name LIKE :search OR last_name LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($filterAdmin === 'admin') {
    $sql .= ' AND is_admin = 1';
} elseif ($filterAdmin === 'user') {
    $sql .= ' AND is_admin = 0';
}

$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - AscendForm Admin</title>
    <link rel="icon" type="image/png" href="../../media/logo_AscendForm.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/fond.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/gestion_utilisateurs.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-card mb-4 mx-auto" style="max-width: 1100px; margin-top: 2.5rem;">
            <div class="mb-4 text-center position-relative">
                <a href="admin.php" class="back-btn position-absolute top-0 end-0">← Retour</a>
                <h1 class="mb-0">👥 Gestion des utilisateurs</h1>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="filter-section">
                <form method="get" class="row g-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Recherche</label>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Email, prénom, nom..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="filter_admin" class="form-label">Type</label>
                        <select name="filter_admin" id="filter_admin" class="form-select">
                            <option value="">Tous</option>
                            <option value="admin" <?= $filterAdmin === 'admin' ? 'selected' : '' ?>>Administrateurs</option>
                            <option value="user" <?= $filterAdmin === 'user' ? 'selected' : '' ?>>Utilisateurs</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                    </div>
                </form>
            </div>
            
            <div class="mb-3">
                <strong><?= count($users) ?></strong> utilisateur(s) trouvé(s)
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Inscrit le</th>
                            <th>Dernière connexion</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Utilisateur</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <?php if ($user['last_login_at']): ?>
                                        <?= date('d/m/Y H:i', strtotime($user['last_login_at'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Jamais</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_admin">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="current_admin" value="<?= $user['is_admin'] ?>">
                                        <button type="submit" class="btn btn-warning btn-action" 
                                                <?= $user['id'] === $_SESSION['client_id'] && $user['is_admin'] ? 'disabled' : '' ?>>
                                            <?= $user['is_admin'] ? '⬇️ Rétrograder' : '⬆️ Promouvoir' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-info btn-action"
                                                onclick="return confirm('Réinitialiser le mot de passe à password123 ?')">
                                            🔑 Réinit. MDP
                                        </button>
                                    </form>
                                    
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-action"
                                                <?= $user['id'] === $_SESSION['client_id'] ? 'disabled' : '' ?>
                                                onclick="return confirm('Supprimer cet utilisateur ?')">
                                            🗑️ Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
