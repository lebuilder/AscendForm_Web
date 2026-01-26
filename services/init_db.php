<?php
// AscendForm - Initialisation SQLite locale
// Crée services/sql/clients.db avec la table clients et un utilisateur de démo
// Utilisable via navigateur ou CLI: php services/init_db.php

declare(strict_types=1);

// Sécurisation: accès admin uniquement (sauf en CLI)
if (php_sapi_name() !== 'cli') {
    session_start();
    require_once __DIR__ . '/../inc/auth.php';
    if (empty($_SESSION['is_admin'])) {
        http_response_code(404);
        require __DIR__ . '/../404.php';
        exit;
    }
}

function is_cli(): bool { return php_sapi_name() === 'cli'; }

function web_begin(): void {
    if (is_cli()) return;
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Initialisation des bases • AscendForm</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<style>
      body{background:#081831;color:#e6f6ff}
      .page-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
      .card-init{background:#0b1d3d;border:1px solid rgba(255,255,255,.08);border-radius:18px;box-shadow:0 8px 24px rgba(0,0,0,.35);max-width:900px;width:100%;}
      .card-init .title{font-weight:700;letter-spacing:.3px}
      .muted{color:#a9bdd6}
      .log-stream{max-height:60vh;overflow:auto;padding:8px 0}
      .log-line{padding:.5rem .75rem;border-left:3px solid #18345e;margin:.25rem 0;background:rgba(255,255,255,.03);border-radius:8px}
      .log-line.ok{border-left-color:#2ecc71;background:rgba(46,204,113,.08)}
      .log-line.warn{border-left-color:#f1c40f;background:rgba(241,196,15,.08)}
      .log-line.err{border-left-color:#e74c3c;background:rgba(231,76,60,.08)}
      .btn-gradient{background:linear-gradient(135deg,#3a86ff,#8338ec);border:none}
    </style></head><body>';
    echo '<div class="page-wrap"><div class="card-init p-4 p-md-5">';
    echo '<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="title h3 mb-0">🚀 Initialisation des bases</h1><a class="btn btn-sm btn-outline-light" href="'.$base.'/../index.php">Accueil</a></div>';
    echo '<div class="muted mb-3">Cette page crée/actualise les schémas SQLite nécessaires.</div>';
    echo '<div class="log-stream" id="logStream">';
}

function web_end(bool $ok = true): void {
    if (is_cli()) return;
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    echo '</div>'; // log-stream
    echo '<div class="mt-4 d-flex gap-2">';
    echo '<a href="'.$base.'/init_db.php" class="btn btn-gradient text-white">Re-exécuter</a>';
    echo '<a href="'.$base.'/../index.php" class="btn btn-secondary">Retour à l\'accueil</a>';
    echo '</div>';
    echo '</div></div>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
    echo '</body></html>';
}

function out(string $msg, string $kind = 'info'): void {
    if (is_cli()) {
        echo $msg."\n";
    } else {
        $cls = 'log-line';
        if ($kind === 'ok') $cls .= ' ok';
        elseif ($kind === 'warn') $cls .= ' warn';
        elseif ($kind === 'err') $cls .= ' err';
        echo '<div class="'.$cls.'">'.htmlspecialchars($msg, ENT_QUOTES, 'UTF-8').'</div>';
        @ob_flush(); @flush();
    }
}

try {
    web_begin();
    // 1) Connexion SQLite locale
    $dbDir = __DIR__ . '/sql';
    $dbFile = $dbDir . '/bdd.db';
    
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    $dsn = 'sqlite:' . $dbFile;
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);


    // 2) Création de la table compte (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE IF NOT EXISTS compte (
                id_compte INT AUTO_INCREMENT PRIMARY KEY,
                mail VARCHAR(255) NOT NULL UNIQUE,
                mdp VARCHAR(255) NOT NULL,
                ip VARCHAR(45),
                derniere_connection DEFAULT (datetime('now'))
            );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table compte créée");

    // 3) Création de la table client (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE IF NOT EXISTS client (
            id_client INT PRIMARY KEY,
            telephone VARCHAR(20),
            taille DOUBLE,
            poids DOUBLE,
            nom_client VARCHAR(100),
            prenom_client VARCHAR(100),
            date_anniv DATE,
            age INT,
            CONSTRAINT fk_client_compte
                FOREIGN KEY (id_client)
                REFERENCES compte(id_compte)
                ON DELETE CASCADE
        );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table client créée");

    // 4) Création de la table admin (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE IF NOT EXISTS admin (
            id_admin INT PRIMARY KEY,
            CONSTRAINT fk_admin_compte
                FOREIGN KEY (id_admin)
                REFERENCES compte(id_compte)
                ON DELETE CASCADE
        );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table admin créée");

    // 5) Création de la table message (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE message (
                id_message INT AUTO_INCREMENT PRIMARY KEY,
                sujet VARCHAR(255),
                status VARCHAR(50),
                repondu_le DATE,
                id_compte INT,
                id_reponse INT,
                CONSTRAINT fk_message_compte
                    FOREIGN KEY (id_compte)
                    REFERENCES compte(id_compte)
                    ON DELETE CASCADE,
                CONSTRAINT fk_message_reponse
                    FOREIGN KEY (id_reponse)
                    REFERENCES message(id_message)
            );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table message créée");

    // 6) Création de la table logs (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE logs (
                id_logs INT AUTO_INCREMENT PRIMARY KEY,
                cree_le DATE,
                mis_a_jour_le DATE,
                id_compte INT,
                CONSTRAINT fk_logs_compte
                    FOREIGN KEY (id_compte)
                    REFERENCES compte(id_compte)
                    ON DELETE CASCADE
            );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table logs créée");

    // 7) Création de la table ip_bloque (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE ip_bloque (
                id_ip_bloque INT AUTO_INCREMENT PRIMARY KEY,
                bloque_jusqu_a DATE,
                raison VARCHAR(255),
                id_logs INT,
                id_compte INT,
                CONSTRAINT fk_ip_logs
                    FOREIGN KEY (id_logs)
                    REFERENCES logs(id_logs)
                    ON DELETE CASCADE,
                CONSTRAINT fk_ip_compte
                    FOREIGN KEY (id_compte)
                    REFERENCES compte(id_compte)
                    ON DELETE CASCADE
            );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table ip_bloque créée");

    // 9) Création de la table exercice (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE exercice (
                id_exo INT AUTO_INCREMENT PRIMARY KEY,
                nom_exo VARCHAR(255),
                muscle_cible VARCHAR(255),
                url_video VARCHAR(255)
            );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table exercice créée");
    
    // 10) Création de la table client_exercice (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE client_exercice (
                id_client INT,
                id_exo INT,
                PRIMARY KEY (id_client, id_exo),
                CONSTRAINT fk_ce_client
                    FOREIGN KEY (id_client)
                    REFERENCES client(id_client)
                    ON DELETE CASCADE,
                CONSTRAINT fk_ce_exercice
                    FOREIGN KEY (id_exo)
                    REFERENCES exercice(id_exo)
                    ON DELETE CASCADE
            );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table client_exercice créée");

    // 11) Création de la table video (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE video (
                id_video INT AUTO_INCREMENT PRIMARY KEY,
                url_video VARCHAR(255),
                titre_video VARCHAR(255),
                description_video TEXT,
                id_exo INT UNIQUE,
                CONSTRAINT fk_video_exercice
                    FOREIGN KEY (id_exo)
                    REFERENCES exercice(id_exo)
                    ON DELETE CASCADE
            );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table vidéo créée");

    // 11) Création de la table video (syntaxe SQLite)
        $sqlCreate = <<<SQL
            CREATE TABLE photo (
                id_photo INT AUTO_INCREMENT PRIMARY KEY,
                chemin_photo VARCHAR(255),
                titre_photo VARCHAR(255),
                description_photo TEXT,
                id_exo INT UNIQUE,
                CONSTRAINT fk_photo_exercice
                    FOREIGN KEY (id_exo)
                    REFERENCES exercice(id_exo)
                    ON DELETE CASCADE
            );
        SQL;
    $pdo->exec($sqlCreate);
    out("Table photo créée");

    // 3) Trigger pour auto-update du champ updated_at
    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS clients_updated_at
        AFTER UPDATE ON clients
        FOR EACH ROW
        BEGIN
            UPDATE clients SET updated_at = datetime('now') WHERE id = NEW.id;
        END;
    ");
    out("Trigger client_updated_at créé");


    // 4) calcule l'age automatiquement
    $pdo->exec("
        CREATE TRIGGER calcul_age_before_insert
        BEFORE INSERT ON client
        FOR EACH ROW
        BEGIN
            SET NEW.age = TIMESTAMPDIFF(YEAR, NEW.date_anniv, CURDATE());
        END$$

        CREATE TRIGGER calcul_age_before_update
        BEFORE UPDATE ON client
        FOR EACH ROW
        BEGIN
            SET NEW.age = TIMESTAMPDIFF(YEAR, NEW.date_anniv, CURDATE());
        END$$
        ");
    out("Trigger calcul age auto créé");

    // 5) Seed utilisateur de démo (idempotent)
    $email = 'admin@ascendform.local';
    $hash = password_hash('admin123',PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO compte(id, mail, mdp) VALUES (:id, :email, :mdp)');
    $stmt_admin = $pdo->prepare('INSERT INTO admin(id_admin) VALUES (:id)');
    try {
        $stmt->execute([':id' => 1, ':email' => $email, ':mdp' => $hash]);
        $stmt_admin->execute([':id' => 1]);
        out("Utilisateur de démo inséré: {$email}");
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // duplicate key
            out("Utilisateur de démo déjà présent: {$email}");
        } else {
            throw $e;
        }
    }
    out("Compte admin créé");
    
    // 6) Seed utilisateur de démo (idempotent)
    $email = 'demo@local.test';
    $hash = password_hash('password',PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO compte(id, mail, mdp) VALUES (:id, :email, :mdp)');
    $stmt_client = $pdo->prepare('INSERT INTO client (id_client, telephone, taille, poids, nom_client, prenom_client, date_anniv) VALUES (:id, :tel, :taille, :poids, :nom, :prenom, :date_anniversaire)');
    try {
        $stmt->execute([':id' => 1, ':email' => $email, ':mdp' => $hash]);
        $stmt->execute([':id' => 1, ':tel' => '0621587426', ':taille' => 165, ':poids' => 65, ':nom' => 'Eude', ':prenom' => 'Jean', ':date_anniversaire' => '2025-01-07']);
        out("Utilisateur de démo inséré: {$email}");
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // duplicate key
            out("Utilisateur de démo déjà présent: {$email}");
        } else {
            throw $e;
        }
    }
    out("Compte client créé");


    out('Initialisation terminée ✔', 'ok');
    out('Vous pouvez vous connecter avec:');
    out('  Email: demo@local.test');
    out('  Password: password');
    out('  Admin: admin@ascendform.local / admin123');
    web_end(true);
} catch (Throwable $e) {
    out('Erreur: '.$e->getMessage(), 'err');
    http_response_code(500);
    web_end(false);
}
