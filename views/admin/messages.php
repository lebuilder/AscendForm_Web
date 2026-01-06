<?php
// AscendForm - Gestion des messages de contact (Admin)
session_start();

require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/logs/logger.php';

require_admin();

$message = null;
$error = null;

// CSRF token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$adminCsrf = $_SESSION['admin_csrf'];

// Traiter la réponse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['admin_csrf'] ?? '';
    if ($csrf !== $_SESSION['admin_csrf']) {
        $error = 'CSRF token invalide.';
    } else {
        try {
            $msgPdo = db_get_messages_pdo();
            $action = $_POST['action'];
            
            if ($action === 'reply') {
                $msgId = (int)$_POST['message_id'];
                $reply = trim($_POST['admin_reply'] ?? '');
                
                if (empty($reply)) {
                    throw new Exception('La réponse ne peut pas être vide');
                }
                
                $stmt = $msgPdo->prepare('UPDATE messages SET admin_reply = :reply, status = :status, replied_at = datetime("now") WHERE id = :id');
                $stmt->execute([
                    ':reply' => $reply,
                    ':status' => 'replied',
                    ':id' => $msgId
                ]);
                
                // Log admin activity
                $msgData = $msgPdo->query("SELECT user_email, subject FROM messages WHERE id = {$msgId}")->fetch(PDO::FETCH_ASSOC);
                log_admin_activity('contact_reply', "Admin répondu à message #{$msgId} de {$msgData['user_email']}", [
                    'message_id' => $msgId,
                    'subject' => $msgData['subject']
                ]);
                
                $message = 'Réponse envoyée avec succès.';
            } elseif ($action === 'mark_read') {
                $msgId = (int)$_POST['message_id'];
                $stmt = $msgPdo->prepare('UPDATE messages SET status = :status WHERE id = :id');
                $stmt->execute([':status' => 'read', ':id' => $msgId]);
                $message = 'Message marqué comme lu.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Charger les messages
$msgPdo = db_get_messages_pdo();
$clientPdo = db_get_pdo();

$filterStatus = $_GET['status'] ?? 'all';
$whereClause = '';
if ($filterStatus === 'pending') {
    $whereClause = "WHERE status = 'pending'";
} elseif ($filterStatus === 'replied') {
    $whereClause = "WHERE status = 'replied'";
}

$messages = $msgPdo->query("SELECT * FROM messages {$whereClause} ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

// Enrichir avec les données utilisateur
foreach ($messages as &$msg) {
    if ($msg['user_id']) {
        try {
            $userData = $clientPdo->query("SELECT first_name, last_name, email FROM clients WHERE id = {$msg['user_id']}")->fetch(PDO::FETCH_ASSOC);
            if ($userData) {
                $msg['user_full_name'] = trim($userData['first_name'] . ' ' . $userData['last_name']);
                $msg['user_profile_link'] = "user_profile.php?id={$msg['user_id']}";
            }
        } catch (Exception $e) {
            // Skip if user not found
        }
    }
}
unset($msg);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages de contact - AscendForm Admin</title>
    <link rel="icon" type="image/png" href="../../media/logo_AscendForm.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/fond.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .message-card {
            background: #0b1d3d;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .message-card.pending {
            border-left: 4px solid #f1c40f;
        }
        .message-card.replied {
            border-left: 4px solid #2ecc71;
        }
        .reply-form {
            background: #0a1930;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-card mb-4 mx-auto" style="max-width: 1200px; margin-top: 2.5rem;">
            <div class="mb-4 text-center position-relative">
                <a href="admin.php" class="back-btn position-absolute top-0 end-0">← Retour</a>
                <h1 class="mb-0">💬 Messages de contact</h1>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="mb-4">
                <a href="?status=all" class="btn btn-sm btn-outline-info <?= $filterStatus === 'all' ? 'active' : '' ?>">Tous</a>
                <a href="?status=pending" class="btn btn-sm btn-outline-warning <?= $filterStatus === 'pending' ? 'active' : '' ?>">En attente</a>
                <a href="?status=replied" class="btn btn-sm btn-outline-success <?= $filterStatus === 'replied' ? 'active' : '' ?>">Répondus</a>
            </div>
            
            <?php if (empty($messages)): ?>
                <div class="alert alert-info">Aucun message.</div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message-card <?= $msg['status'] ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1" style="color:#6fd3ff;"><?= htmlspecialchars($msg['subject']) ?></h5>
                                <div class="small">
                                    De: 
                                    <?php if (!empty($msg['user_profile_link'])): ?>
                                        <a href="<?= $msg['user_profile_link'] ?>" style="color:#6fd3ff;">
                                            <?= htmlspecialchars($msg['user_full_name'] ?? $msg['user_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($msg['user_name'] ?? $msg['user_email']) ?>
                                    <?php endif; ?>
                                    (<?= htmlspecialchars($msg['user_email']) ?>)
                                    • <?= htmlspecialchars($msg['created_at']) ?>
                                </div>
                            </div>
                            <span class="badge bg-<?= $msg['status'] === 'pending' ? 'warning' : 'success' ?>">
                                <?= $msg['status'] === 'pending' ? 'En attente' : 'Répondu' ?>
                            </span>
                        </div>
                        
                        <div class="mb-3 p-3 rounded" style="background:#1a3a5c;">
                            <strong>Message:</strong>
                            <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($msg['user_message'])) ?></p>
                        </div>
                        
                        <?php if (!empty($msg['admin_reply'])): ?>
                            <div class="p-3 rounded" style="background:#0a1930;">
                                <strong style="color:#2ecc71;">Votre réponse:</strong>
                                <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($msg['admin_reply'])) ?></p>
                                <small>Répondu le <?= htmlspecialchars($msg['replied_at']) ?></small>
                            </div>
                        <?php else: ?>
                            <div class="reply-form">
                                <form method="post">
                                    <input type="hidden" name="action" value="reply">
                                    <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                    <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($adminCsrf) ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Votre réponse</label>
                                        <textarea name="admin_reply" class="form-control" rows="4" required style="background-color:#0b1d3d;color:#6fd3ff;border-color:#6fd3ff;"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success">Envoyer la réponse</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 30s for new messages
        setTimeout(() => {
            if(document.hidden === false) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
