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
    $dbFile = $dbDir . '/clients.db';
    
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    $dsn = 'sqlite:' . $dbFile;
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    $pdo->exec('PRAGMA foreign_keys = ON');
    out("Base SQLite créée/présente: {$dbFile}");

    // 2) Création de la table clients (syntaxe SQLite)
        $sqlCreate = <<<SQL
CREATE TABLE IF NOT EXISTS clients (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  phone TEXT,
  avatar_path TEXT,
  birthdate TEXT,
  height_cm REAL,
  weight_kg REAL,
  is_admin INTEGER NOT NULL DEFAULT 0,
  last_login_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL;
    $pdo->exec($sqlCreate);
    
    // Index pour performance
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_clients_last_first ON clients(last_name, first_name)');
    out("Table créée/présente: clients");

    // 3) Trigger pour auto-update du champ updated_at
    $pdo->exec("
        CREATE TRIGGER IF NOT EXISTS clients_updated_at
        AFTER UPDATE ON clients
        FOR EACH ROW
        BEGIN
            UPDATE clients SET updated_at = datetime('now') WHERE id = NEW.id;
        END;
    ");

    // 4) Migration: ajouter colonne is_admin si elle n'existe pas
    $columns = $pdo->query("PRAGMA table_info(clients)")->fetchAll(PDO::FETCH_ASSOC);
    $hasIsAdmin = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'is_admin') {
            $hasIsAdmin = true;
            break;
        }
    }
    if (!$hasIsAdmin) {
        $pdo->exec('ALTER TABLE clients ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
        out("Colonne is_admin ajoutée à la table clients");
    }

    // 5) Seed utilisateur de démo (idempotent)
    $email = 'demo@local.test';
    $hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // password
    $stmt = $pdo->prepare('INSERT INTO clients (email, password_hash, first_name, last_name, is_admin) VALUES (:email, :hash, :fn, :ln, 0)');
    try {
        $stmt->execute([':email' => $email, ':hash' => $hash, ':fn' => 'Demo', ':ln' => 'User']);
        out("Utilisateur de démo inséré: {$email}");
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // duplicate key
            out("Utilisateur de démo déjà présent: {$email}");
        } else {
            throw $e;
        }
    }
    
    // 6) Seed admin (admin@ascendform.local / admin123)
    $adminEmail = 'admin@ascendform.local';
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmtAdmin = $pdo->prepare('INSERT INTO clients (email, password_hash, first_name, last_name, is_admin) VALUES (:email, :hash, :fn, :ln, 1)');
    try {
        $stmtAdmin->execute([':email' => $adminEmail, ':hash' => $adminHash, ':fn' => 'Admin', ':ln' => 'AscendForm']);
        out("Utilisateur admin inséré: {$adminEmail}");
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            out("Utilisateur admin déjà présent: {$adminEmail}");
        } else {
            throw $e;
        }
    }

    // 7) Vérification
    $count = (int)$pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    out("Clients en base: {$count}");
    // 8) Initialiser une seconde base SQLite: exercices.db
    $exDbFile = $dbDir . '/exercices.db';
    $exPdo = new PDO('sqlite:' . $exDbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $exPdo->exec('PRAGMA foreign_keys = ON');
    out("Base SQLite créée/présente: {$exDbFile}");

    // 8b) Initialiser base seances.db pour l'enregistrement des séances d'entraînement
    $seanceDbFile = $dbDir . '/seances.db';
    $seancePdo = new PDO('sqlite:' . $seanceDbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $seancePdo->exec('PRAGMA foreign_keys = ON');
    out("Base SQLite créée/présente: {$seanceDbFile}");

    $sqlCreateSeance = <<<SQL
CREATE TABLE IF NOT EXISTS seances (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL,
  exercice_name TEXT NOT NULL,
  muscle_group TEXT,
  sets INTEGER NOT NULL DEFAULT 0,
  reps INTEGER NOT NULL DEFAULT 0,
  weight_kg REAL NOT NULL DEFAULT 0,
  notes TEXT,
  duration_minutes INTEGER,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL;
    $seancePdo->exec($sqlCreateSeance);
    $seancePdo->exec('CREATE INDEX IF NOT EXISTS idx_seances_user_id ON seances(user_id)');
    $seancePdo->exec('CREATE INDEX IF NOT EXISTS idx_seances_date ON seances(date)');
    $seancePdo->exec('CREATE INDEX IF NOT EXISTS idx_seances_muscle ON seances(muscle_group)');
    out("Table créée/présente: seances");

    $sqlCreateEx = <<<SQL
CREATE TABLE IF NOT EXISTS exercices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  target_muscle TEXT NOT NULL,
  photo_path TEXT,
  video_url TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL;
    $exPdo->exec($sqlCreateEx);
    $exPdo->exec('CREATE INDEX IF NOT EXISTS idx_exercices_name ON exercices(name)');
    $exPdo->exec('CREATE INDEX IF NOT EXISTS idx_exercices_muscle ON exercices(target_muscle)');

    $exPdo->exec("CREATE TRIGGER IF NOT EXISTS exercices_updated_at\n        AFTER UPDATE ON exercices\n        FOR EACH ROW\n        BEGIN\n            UPDATE exercices SET updated_at = datetime('now') WHERE id = NEW.id;\n        END;\n    ");
    out("Table créée/présente: exercices");

    // Migration: ajouter colonne muscles_cibles si elle n'existe pas
    $exColumns = $exPdo->query("PRAGMA table_info(exercices)")->fetchAll(PDO::FETCH_ASSOC);
    $hasMusclesCibles = false;
    foreach ($exColumns as $col) {
        if ($col['name'] === 'muscles_cibles') {
            $hasMusclesCibles = true;
            break;
        }
    }
    if (!$hasMusclesCibles) {
        $exPdo->exec('ALTER TABLE exercices ADD COLUMN muscles_cibles TEXT');
        out("Colonne muscles_cibles ajoutée à la table exercices");
    }

    // 9) Initialiser base messages.db pour le système de contact/chat
    $msgDbFile = $dbDir . '/messages.db';
    $msgPdo = new PDO('sqlite:' . $msgDbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $msgPdo->exec('PRAGMA foreign_keys = ON');
    out("Base SQLite créée/présente: {$msgDbFile}");

    $sqlCreateMsg = <<<SQL
CREATE TABLE IF NOT EXISTS messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  user_email TEXT NOT NULL,
  user_name TEXT,
  subject TEXT NOT NULL,
  user_message TEXT NOT NULL,
  admin_reply TEXT,
  status TEXT NOT NULL DEFAULT 'pending',
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  replied_at TEXT
);
SQL;
    $msgPdo->exec($sqlCreateMsg);
    $msgPdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_user_id ON messages(user_id)');
    $msgPdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_status ON messages(status)');
    out("Table créée/présente: messages");

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
