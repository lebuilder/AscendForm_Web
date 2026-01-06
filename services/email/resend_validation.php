<?php
// AscendForm - Stub resend validation email endpoint
// POST: email, action

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../inc/auth.php';
require_admin(); // Only admins can replay email actions
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/logs/logger.php';

$logger = get_logger();
$email = trim($_POST['email'] ?? '');
$origAction = trim($_POST['action'] ?? '');
if ($email === '') {
    echo json_encode(['success'=>false,'error'=>'Email manquant']);
    exit;
}
try {
    $pdo = db_get_pdo();
    $stmt = $pdo->prepare('SELECT id, email, first_name, last_name FROM clients WHERE email = :email LIMIT 1');
    $stmt->execute([':email'=>$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$user){
        echo json_encode(['success'=>false,'error'=>'Utilisateur introuvable']);
        $logger->logAdvanced('WARN','resend_validation_email',['email'=>$email,'reason'=>'user_not_found'],'user',$email,'fail');
        exit;
    }
    // Simuler envoi (integration email réelle non incluse ici)
    // Normally: call email service / queue
    usleep(150000); // 150ms simulate
    $logger->logAdvanced('AUDIT','resend_validation_email',['email'=>$email,'source_action'=>$origAction],'user',$user['id'],'success',0.15);
    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    $logger->logAdvanced('ERROR','resend_validation_email',['email'=>$email,'error'=>$e->getMessage()],'user',$email,'fail');
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Erreur serveur']);
}
