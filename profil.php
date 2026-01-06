<?php
    require_once __DIR__.'/inc/auth.php';
    require_login();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Charger infos utilisateur depuis la base
    require_once __DIR__ . '/config/db.php';
    $pdo = db_get_pdo();
    $user = null;
    if (!empty($_SESSION['client_id'])) {
        $stmt = $pdo->prepare('SELECT first_name, last_name, email, height_cm, weight_kg FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$_SESSION['client_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $fullNamePhp = $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : '';
    $emailPhp = $user['email'] ?? '';
    $heightPhp = isset($user['height_cm']) ? (string)$user['height_cm'] : '';
    $weightPhp = isset($user['weight_kg']) ? (string)$user['weight_kg'] : '';

    // Inclusion des fichiers UI
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
        <title>Mon Profil — AscendForm</title>
        <!-- Icônes du site : .ico préféré + fallback PNG -->
        <link rel="icon" type="image/x-icon" href="media/logo_AscendForm.ico">
        <link rel="icon" type="image/png" href="media/logo_AscendForm.png">
        <link rel="shortcut icon" href="media/logo_AscendForm.ico">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/profil.css" />
        <link rel="stylesheet" href="css/navbar.css" />
        <link rel="stylesheet" href="css/fond.css" />
    </head>
    <body class="profile-page">
        <!--nav bar-->
        <?php navbar(); ?>

        <main class="container py-4">
            <div class="row g-4">
                <div class="col-12">
                    <h1 class="h3 mb-0">Mon profil</h1>
                    <p class="small mb-3">Gérez vos informations personnelles</p>
                </div>

                <!-- Colonne gauche: Avatar + résumé -->
                <section class="col-12 col-md-4">
                    <div class="card shadow-sm profile-card">
                        <div class="card-body text-center">
                            <div class="avatar-wrapper mb-3">
                                <img id="profileAvatar" class="profile-avatar" src="media/logo_AscendForm.png" alt="Avatar">
                            </div>
                            <div class="d-grid gap-2">
                                <label class="btn btn-outline-light btn-sm" for="avatarInput">Changer l'avatar</label>
                                <input id="avatarInput" type="file" accept="image/*" class="d-none">
                            </div>
                            <hr class="border-secondary my-4">
                            <ul class="list-unstyled text-start small mb-0">
                                <li class="mb-1"><span >Nom:</span> <span id="summaryName"><?php echo htmlspecialchars($fullNamePhp ?: '—', ENT_QUOTES, 'UTF-8'); ?></span></li>
                                <li class="mb-1"><span >Email:</span> <span id="summaryEmail"><?php echo htmlspecialchars($emailPhp ?: '—', ENT_QUOTES, 'UTF-8'); ?></span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="d-grid mt-3">
                        <a class="btn btn-outline-light" href="login.php?logout=1">Se déconnecter</a>
                    </div>
                </section>

                <!-- Colonne droite: Formulaires -->
                <section class="col-12 col-md-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h2 class="h5 section-title">Informations personnelles</h2>
                            <form id="profileForm" class="mt-3">
                                <div class="row g-3">
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label">Nom complet</label>
                                        <input type="text" id="fullName" class="form-control" placeholder="Ex: Alex Martin" value="<?php echo htmlspecialchars($fullNamePhp, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" id="email" class="form-control" placeholder="vous@exemple.com" value="<?php echo htmlspecialchars($emailPhp, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <label class="form-label">Taille (cm)</label>
                                        <input type="number" id="height" class="form-control" min="0" step="1" placeholder="175" value="<?php echo htmlspecialchars($heightPhp, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <label class="form-label">Poids (kg)</label>
                                        <input type="number" id="weight" class="form-control" min="0" step="0.1" placeholder="70.5" value="<?php echo htmlspecialchars($weightPhp, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label">Objectif</label>
                                        <input type="text" id="goal" class="form-control" placeholder="Prise de masse, Perte de poids...">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                    <button type="button" id="resetProfile" class="btn btn-outline-light">Réinitialiser</button>
                                </div>
                                <div id="profileSaved" class="small text-success mt-2 d-none">Profil enregistré ✔</div>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h5 section-title">Sécurité</h2>
                            <form id="passwordForm" class="mt-3">
                                <div class="row g-3">
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label">Mot de passe actuel</label>
                                        <input type="password" id="currentPwd" class="form-control" autocomplete="current-password">
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label">Nouveau mot de passe</label>
                                        <input type="password" id="newPwd" class="form-control" autocomplete="new-password">
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="form-label">Confirmer</label>
                                        <input type="password" id="confirmPwd" class="form-control" autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-outline-light">Mettre à jour</button>
                                </div>
                                <div id="pwdMsg" class="small mt-2 d-none"></div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </main>
        <!--Footer-->
        <?php footer(); ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

        <script>
        (function(){
            // Clés de stockage local pour le profil
            const KEY = 'ascendform.profile';
            function loadProfile(){
                try{ return JSON.parse(localStorage.getItem(KEY) || '{}'); }catch(e){ return {}; }
            }
            function saveProfile(data){
                localStorage.setItem(KEY, JSON.stringify(data));
            }
            function $(id){ return document.getElementById(id); }

            const fullName = $('fullName');
            const email = $('email');
            const height = $('height');
            const weight = $('weight');
            const goal = $('goal');
            const summaryName = $('summaryName');
            const summaryEmail = $('summaryEmail');
            const profileSaved = $('profileSaved');
            const avatar = $('profileAvatar');
            const avatarInput = $('avatarInput');

            // Pré-remplissage éventuel depuis session PHP (si présent)
            const phpName = <?php echo json_encode($fullNamePhp); ?>;
            const phpEmail = <?php echo json_encode($emailPhp); ?>;
            const phpHeight = <?php echo json_encode($heightPhp); ?>;
            const phpWeight = <?php echo json_encode($weightPhp); ?>;

            // Charger et afficher le profil
            // DB values should take precedence over any localStorage leftovers
            const data = Object.assign({}, loadProfile(), { name: phpName, email: phpEmail, height: phpHeight, weight: phpWeight });
            if(data.name) fullName.value = data.name;
            if(data.email) email.value = data.email;
            if(data.height) height.value = data.height;
            if(data.weight) weight.value = data.weight;
            if(data.goal) goal.value = data.goal;
            if(data.avatar) avatar.src = data.avatar;
            summaryName.textContent = data.name || '—';
            summaryEmail.textContent = data.email || '—';

            // Enregistrement du profil (localStorage côté front)
            $('profileForm').addEventListener('submit', async function(e){
                e.preventDefault();
                const payload = new FormData();
                payload.append('fullName', fullName.value.trim());
                payload.append('email', email.value.trim());
                payload.append('height', height.value.trim());
                payload.append('weight', weight.value.trim());
                try {
                    const res = await fetch('services/profile/update_profile.php', { method: 'POST', body: payload });
                    const dataResp = await res.json();
                    if (dataResp.success) {
                        const store = loadProfile();
                        store.name = fullName.value.trim();
                        store.email = email.value.trim();
                        store.height = height.value.trim();
                        store.weight = weight.value.trim();
                        store.avatar = avatar.src;
                        saveProfile(store);
                        summaryName.textContent = store.name || '—';
                        summaryEmail.textContent = store.email || '—';
                        profileSaved.classList.remove('d-none');
                        profileSaved.classList.toggle('text-success', true);
                        profileSaved.textContent = 'Profil enregistré ✔';
                        setTimeout(()=> profileSaved.classList.add('d-none'), 2000);
                    } else {
                        profileSaved.classList.remove('d-none');
                        profileSaved.classList.toggle('text-success', false);
                        profileSaved.classList.add('text-danger');
                        profileSaved.textContent = dataResp.error || 'Erreur lors de l\'enregistrement';
                    }
                } catch (err) {
                    profileSaved.classList.remove('d-none');
                    profileSaved.classList.toggle('text-success', false);
                    profileSaved.classList.add('text-danger');
                    profileSaved.textContent = 'Erreur réseau';
                }
            });

            $('resetProfile').addEventListener('click', function(){
                localStorage.removeItem(KEY);
                fullName.value = '';
                email.value = '';
                height.value = '';
                weight.value = '';
                goal.value = '';
                avatar.src = 'media/logo_AscendForm.png';
                summaryName.textContent = '—';
                summaryEmail.textContent = '—';
            });

            // Avatar upload: send to server, update DB, session, and navbar live
            avatarInput.addEventListener('change', async function(){
                const file = this.files && this.files[0];
                if(!file) return;
                const fd = new FormData();
                fd.append('avatar', file);
                try {
                    const res = await fetch('services/profile/upload_avatar.php', { method: 'POST', body: fd });
                    const json = await res.json();
                    if (json && json.success && json.avatar) {
                        avatar.src = json.avatar;
                        const p = loadProfile();
                        p.avatar = json.avatar;
                        saveProfile(p);
                        // Also update navbar avatar if present
                        const slot = document.getElementById('navbarAvatarSlot');
                        if (slot) {
                            slot.innerHTML = '<img src="' + json.avatar.replace(/"/g,'&quot;') + '" alt="Avatar" width="40" height="40" style="border-radius:50%; object-fit:cover; box-shadow: 0 2px 8px rgba(111, 211, 255, 0.4);">';
                        }
                    } else {
                        alert(json && json.error ? json.error : 'Erreur lors du téléversement de l\'avatar');
                    }
                } catch (e) {
                    alert('Erreur réseau lors du téléversement de l\'avatar');
                }
            });

            // Formulaire mot de passe (démonstration côté front)
            const pwdForm = $('passwordForm');
            const pwdMsg = $('pwdMsg');
            pwdForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const cur = $('currentPwd').value.trim();
                const n = $('newPwd').value.trim();
                const c = $('confirmPwd').value.trim();
                const payload = new FormData();
                payload.append('currentPwd', cur);
                payload.append('newPwd', n);
                payload.append('confirmPwd', c);
                try {
                    const res = await fetch('services/profile/change_password.php', { method: 'POST', body: payload });
                    const json = await res.json();
                    if (json.success) {
                        pwdMsg.textContent = 'Mot de passe mis à jour ✔';
                        pwdMsg.className = 'small mt-2 text-success';
                        pwdForm.reset();
                    } else {
                        pwdMsg.textContent = json.error || 'Erreur lors de la mise à jour du mot de passe.';
                        pwdMsg.className = 'small mt-2 text-danger';
                    }
                    pwdMsg.classList.remove('d-none');
                } catch (err) {
                    pwdMsg.textContent = 'Erreur réseau';
                    pwdMsg.className = 'small mt-2 text-danger';
                    pwdMsg.classList.remove('d-none');
                }
            });
        })();
        </script>
    </body>
</html>