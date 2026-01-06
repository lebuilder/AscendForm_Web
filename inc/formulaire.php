<?php

function formulaire_Saisie_Seance(){
?>
    <!-- Formulaire de saisie des séances (gère plusieurs exercices et séries) -->
                <section id="tracker" class="col-12 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Ajouter une séance</h5>
                            <form id="workoutForm">
                                <div class="mb-2" id="dateContainer">
                                    <label class="form-label">Date</label>
                                    <input type="date" id="date" class="form-control" />
                                </div>

                                <!-- Conteneur dynamique : chaque exercice ajouté crée une 'card' avec ses séries -->
                                <div id="exercisesContainer"></div>
                                <div id="exerciseButtonsContainer" class="mb-3">
                                    <div class="d-flex gap-2">
                                        <button id="addExerciseBtn" type="button" class="btn btn-sm btn-outline-light">Ajouter un exercice</button>
                                        <button id="clearExercisesBtn" type="button" class="btn btn-sm btn-outline-light">Réinitialiser</button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <input type="text" id="notes" class="form-control" placeholder="Remarques courtes" />
                                </div>
                                <div class="d-grid">
                                    <button class="btn btn-primary" type="submit">Ajouter la séance</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

<?php
}

function formulaire_Historique_Seance(){
    ?>
        <section id="progress" class="col-12 col-lg-6">
            <!-- Résumé rapide (nombre de séances) -->
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="card-title">Résumé rapide</h5>
                    <p id="summary" class="card-text">Aucune séance pour l'instant.</p>
                </div>
            </div>

            <!-- Historique des séances -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0">Historique</h5>
                    </div>
                            <ul id="workoutList" class="list-group list-group-flush"></ul>
                </div>
            </div>
        </section>
    <?php
}


function formulaire_login(){
    ?>
    <section id="login" class="col-12 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="media/logo_AscendForm.png" alt="AscendForm Logo" class="login-logo">
                    </div>
                    <h5 class="card-title">Connexion</h5>
                    <form id="LoginForm" method="post" action="<?php echo 'login.php'.(isset($_GET['redirect']) ? ('?redirect='.urlencode($_GET['redirect'])) : ''); ?>" novalidate>
                        <?php // Champ CSRF login ?>
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_login'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-2" id="loginContainer">
                            <label class="form-label">Email</label>
                            <input type="email" id="login" name="email" class="form-control" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" id="password" name="password" class="form-control" required />
                        </div>

                        <div class="d-grid">
                            <button class="btn btn-primary" type="submit">Se connecter</button>
                        </div>
                        <?php if (isset($GLOBALS['login_error']) && !empty($GLOBALS['login_error'])): ?>
                        <div class="mt-3 alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($GLOBALS['login_error'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </section>
    <?php
}


function formulaire_register(){
    ?>
    <section id="register" class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Créer un compte</h5>
                <form method="post" action="register.php<?php echo isset($_GET['redirect']) ? ('?redirect='.urlencode($_GET['redirect'])) : '';?>" novalidate>
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_register'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Nom</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" name="password" class="form-control" minlength="8" required>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary" type="submit">S'inscrire</button>
                        <a href="login.php<?php echo isset($_GET['redirect']) ? ('?redirect='.urlencode($_GET['redirect'])) : '';?>" class="btn btn-outline-light">Déjà un compte ? Se connecter</a>
                    </div>
                    <?php if (isset($GLOBALS['register_error']) && !empty($GLOBALS['register_error'])): ?>
                    <div class="mt-3 alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($GLOBALS['register_error'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>
    <?php
}



function formulaire_contact(){
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../services/logs/logger.php';
    
    $success = false;
    $error = null;
    
    // Traiter l'envoi du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'], $_POST['subject'])) {
        try {
            $msgPdo = db_get_messages_pdo();
            
            $userId = $_SESSION['client_id'] ?? 0;
            $userEmail = $_SESSION['user_email'] ?? ($_POST['email'] ?? 'non_connecte@test.com');
            $userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
            if (empty($userName)) {
                $userName = 'Utilisateur';
            }
            
            $subject = trim($_POST['subject']);
            $message = trim($_POST['message']);
            
            if (empty($subject) || empty($message)) {
                throw new Exception('Le sujet et le message sont requis');
            }
            
            $stmt = $msgPdo->prepare('INSERT INTO messages (user_id, user_email, user_name, subject, user_message, status) VALUES (:uid, :email, :name, :subj, :msg, :status)');
            $stmt->execute([
                ':uid' => $userId,
                ':email' => $userEmail,
                ':name' => $userName,
                ':subj' => $subject,
                ':msg' => $message,
                ':status' => 'pending'
            ]);
            
            log_admin_activity('contact_message', "Nouveau message contact de {$userEmail}", [
                'user_id' => $userId,
                'subject' => $subject
            ]);
            
            // Redirection PRG (Post-Redirect-Get) pour éviter la resoumission
            header('Location: contact.php?success=1');
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // Afficher le message de succès après redirection
    if (isset($_GET['success']) && $_GET['success'] == '1') {
        $success = true;
        // Nettoyer l'URL pour éviter la resoumission au refresh
        echo '<script>if (window.history.replaceState) { window.history.replaceState(null, null, window.location.pathname); }</script>';
    }
    
    // Charger les messages de l'utilisateur connecté
    $messages = [];
    if (!empty($_SESSION['client_id'])) {
        try {
            $msgPdo = db_get_messages_pdo();
            $stmt = $msgPdo->prepare('SELECT * FROM messages WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50');
            $stmt->execute([':uid' => $_SESSION['client_id']]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Erreur chargement messages';
        }
    }
    ?>
    <div style="width: 100%; padding: 2rem;">
        <div class="row g-4">
            <!-- Colonne gauche: Formulaire -->
            <div class="col-lg-4">
                <div class="contact-card" style="padding: 3rem 2.5rem;">
                    <div class="text-center mb-4">
                        <img src="media/logo_AscendForm.png" alt="AscendForm Logo" class="contact-logo" style="width: 100px; height: 100px;">
                    </div>
                    <h2 class="contact-title" style="font-size: 1.8rem; margin-bottom: 1.5rem;">Contactez-nous</h2>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" id="successAlert" style="margin-bottom: 1.5rem;">
                            ✅ Votre message a été envoyé avec succès!
                        </div>
                        <script>
                            setTimeout(function() {
                                var alert = document.getElementById('successAlert');
                                if (alert) {
                                    alert.style.transition = 'opacity 0.5s ease';
                                    alert.style.opacity = '0';
                                    setTimeout(function() { alert.remove(); }, 500);
                                }
                            }, 5000);
                        </script>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="post" class="contact-form">
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 0.95rem; font-weight: 500;">📋 Sujet</label>
                            <input type="text" name="subject" class="form-control" style="padding: 0.7rem 1rem;" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 0.95rem; font-weight: 500;">✉️ Message</label>
                            <textarea name="message" class="form-control" rows="7" style="padding: 0.7rem 1rem; resize: none;" required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" style="padding: 0.8rem; font-size: 1rem; font-weight: 600;">
                                🚀 Envoyer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Colonne droite: Historique -->
            <div class="col-lg-8">
                <?php if (!empty($messages)): ?>
                    <div class="contact-card" style="padding: 3rem 2.5rem; min-height: 600px;">
                        <div class="d-flex align-items-center mb-4 pb-3" style="border-bottom: 2px solid rgba(111, 211, 255, 0.2);">
                            <div class="me-3" style="font-size: 2.5rem;">💬</div>
                            <div>
                                <h3 class="mb-1" style="color: #6fd3ff; font-weight: 600; font-size: 1.6rem;">Historique des conversations</h3>
                                <p class="mb-0 text-muted" style="font-size: 0.9rem;">Retrouvez tous vos échanges avec notre équipe</p>
                            </div>
                        </div>
                        <div id="messageHistory" style="max-height: 650px; overflow-y: auto; padding-right: 10px;">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-thread mb-3" style="background: linear-gradient(135deg, rgba(15, 32, 57, 0.5) 0%, rgba(10, 25, 48, 0.7) 100%); border: 1px solid rgba(111, 211, 255, 0.15); border-radius: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: all 0.3s ease; overflow: hidden;">
                            <!-- En-tête du message -->
                            <div class="p-3" style="background: rgba(111, 211, 255, 0.05); border-bottom: 1px solid rgba(111, 211, 255, 0.1);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div style="flex: 1;">
                                        <h6 class="mb-1" style="color:#6fd3ff; font-weight: 600; font-size: 1rem;">
                                            📧 <?= htmlspecialchars($msg['subject']) ?>
                                        </h6>
                                        <small class="text-muted" style="font-size: 0.8rem;">
                                            🕐 <?= htmlspecialchars($msg['created_at']) ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($msg['admin_reply'])): ?>
                                        <span class="badge" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 0.4rem 0.9rem; font-size: 0.8rem; border-radius: 20px; box-shadow: 0 2px 6px rgba(40, 167, 69, 0.3);">
                                            ✓ Répondu
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); padding: 0.4rem 0.9rem; font-size: 0.8rem; border-radius: 20px; box-shadow: 0 2px 6px rgba(255, 193, 7, 0.3);">
                                            ⏳ En attente
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Contenu du message -->
                            <div class="p-3">
                                <!-- Message utilisateur -->
                                <div class="user-message mb-2" style="background: linear-gradient(135deg, rgba(111, 211, 255, 0.1) 0%, rgba(111, 211, 255, 0.05) 100%); border-left: 3px solid #6fd3ff; padding: 1rem; border-radius: 10px;">
                                    <div class="d-flex align-items-center mb-2">
                                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 10px; box-shadow: 0 2px 6px rgba(0, 123, 255, 0.4);">
                                            <span style="font-size: 16px;">👤</span>
                                        </div>
                                        <span style="color: #6fd3ff; font-weight: 600; font-size: 0.9rem;">Vous</span>
                                    </div>
                                    <p class="mb-0" style="color: #e0e0e0; line-height: 1.7; font-size: 0.95rem; padding-left: 42px;"><?= nl2br(htmlspecialchars($msg['user_message'])) ?></p>
                                </div>
                                
                                <!-- Réponse admin -->
                                <?php if (!empty($msg['admin_reply'])): ?>
                                    <div class="admin-reply" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.12) 0%, rgba(40, 167, 69, 0.05) 100%); border-left: 3px solid #28a745; padding: 1rem; border-radius: 10px; margin-top: 1rem;">
                                        <div class="d-flex align-items-center mb-2">
                                            <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 10px; box-shadow: 0 2px 6px rgba(40, 167, 69, 0.4);">
                                                <span style="font-size: 16px;">👨‍💼</span>
                                            </div>
                                            <div>
                                                <span style="color: #20c997; font-weight: 600; font-size: 0.9rem;">Support AscendForm</span>
                                                <small class="d-block text-muted" style="font-size: 0.75rem;">Répondu le <?= htmlspecialchars($msg['replied_at'] ?? '') ?></small>
                                            </div>
                                        </div>
                                        <p class="mb-0" style="color: #c8e6c9; line-height: 1.7; font-size: 0.95rem; padding-left: 42px;"><?= nl2br(htmlspecialchars($msg['admin_reply'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="contact-card text-center" style="padding: 4rem 3rem; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(15, 32, 57, 0.3) 0%, rgba(10, 25, 48, 0.5) 100%); min-height: 400px;">
                        <div style="max-width: 500px;">
                            <div style="font-size: 5rem; margin-bottom: 2rem; opacity: 0.7; animation: pulse 2s ease-in-out infinite;">💬</div>
                            <h4 style="color: #6fd3ff; margin-bottom: 1.2rem; font-weight: 600; font-size: 1.5rem;">Aucune conversation</h4>
                            <p class="text-muted" style="font-size: 1.05rem; line-height: 1.7; margin-bottom: 1.5rem;">
                                Commencez une nouvelle conversation avec notre équipe.<br>
                                Nous sommes là pour vous aider!
                            </p>
                            <div class="d-flex justify-content-center gap-4 mt-4">
                                <div style="color: #6fd3ff; opacity: 0.8;">
                                    <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">⚡</div>
                                    <small>Réponse rapide</small>
                                </div>
                                <div style="color: #6fd3ff; opacity: 0.8;">
                                    <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🔒</div>
                                    <small>Sécurisé</small>
                                </div>
                                <div style="color: #6fd3ff; opacity: 0.8;">
                                    <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">24/7</div>
                                    <small>Support</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <style>
                        @keyframes pulse {
                            0%, 100% { transform: scale(1); opacity: 0.7; }
                            50% { transform: scale(1.05); opacity: 0.9; }
                        }
                    </style>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>


