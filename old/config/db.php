<?php
// AscendForm MySQL connection helper
// Utilise la base 'Clients' créée par le script SQL d'initialisation.
// Variables d'environnement pour surcharger : ASCENDFORM_DB_HOST, ASCENDFORM_DB_NAME, ASCENDFORM_DB_USER, ASCENDFORM_DB_PASS

declare(strict_types=1);

function db_get_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    // Base SQLite locale dans services/sql/clients.db
    $dbDir = __DIR__ . '/../services/sql';
    $dbFile = $dbDir . '/clients.db';
    
    // Créer le répertoire si absent
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    $dsn = 'sqlite:' . $dbFile;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    try {
        $pdo = new PDO($dsn, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } catch (PDOException $e) {
        throw new RuntimeException('Connexion BD échouée: '.$e->getMessage());
    }
    return $pdo;
}

/** Connexion à la base des exercices (SQLite services/sql/exercices.db). */
function db_get_exercices_pdo(): PDO {
    static $pdoEx = null;
    if ($pdoEx instanceof PDO) {
        return $pdoEx;
    }
    $dbDir = __DIR__ . '/../services/sql';
    $dbFile = $dbDir . '/exercices.db';

    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $dsn = 'sqlite:' . $dbFile;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdoEx = new PDO($dsn, null, null, $options);
        $pdoEx->exec('PRAGMA foreign_keys = ON');
    } catch (PDOException $e) {
        throw new RuntimeException('Connexion BD exercices échouée: '.$e->getMessage());
    }
    return $pdoEx;
}

/** Connexion à la base des messages (SQLite services/sql/messages.db). */
function db_get_messages_pdo(): PDO {
    static $pdoMsg = null;
    if ($pdoMsg instanceof PDO) {
        return $pdoMsg;
    }
    $dbDir = __DIR__ . '/../services/sql';
    $dbFile = $dbDir . '/messages.db';

    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $dsn = 'sqlite:' . $dbFile;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdoMsg = new PDO($dsn, null, null, $options);
        $pdoMsg->exec('PRAGMA foreign_keys = ON');
    } catch (PDOException $e) {
        throw new RuntimeException('Connexion BD messages échouée: '.$e->getMessage());
    }
    return $pdoMsg;
}

/** Vérifie rapidement l'accessibilité de la base (ex: au début d'une page). */
function db_ping(): bool {
    try {
        $pdo = db_get_pdo();
        $pdo->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
