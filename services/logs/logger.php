<?php
// AscendForm - Système de logs centralisé
declare(strict_types=1);

class Logger {
    private string $logDir;
    private string $logFile;
    private int $maxSizeBytes = 5_000_000; // rotation seuil ~5MB
    private ?string $lastHash = null;
    private string $hashFile;

    public function __construct() {
        $this->logDir = __DIR__;
        $this->logFile = $this->logDir . '/activity.log';
        $this->hashFile = $this->logDir . '/activity.hash';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        $this->loadLastHash();
        $this->initRequestId();
    }

    private function initRequestId(): void {
        if (!isset($_SESSION['_request_id'])) {
            $_SESSION['_request_id'] = bin2hex(random_bytes(8)) . '-' . substr((string)microtime(true), -6);
        }
    }

    private function loadLastHash(): void {
        if (file_exists($this->hashFile)) {
            $h = trim(@file_get_contents($this->hashFile));
            if ($h !== '') {
                $this->lastHash = $h;
            }
        } else {
            $this->lastHash = null;
        }
    }

    private function persistLastHash(string $hash): void {
        $this->lastHash = $hash;
        @file_put_contents($this->hashFile, $hash, LOCK_EX);
    }

    private function rotateIfNeeded(): void {
        if (file_exists($this->logFile) && filesize($this->logFile) >= $this->maxSizeBytes) {
            $stamp = date('Ymd_His');
            $rotated = $this->logDir . "/activity_{$stamp}.log";
            @rename($this->logFile, $rotated);
            // Compression gzip
            $data = @file_get_contents($rotated);
            if ($data !== false) {
                @file_put_contents($rotated . '.gz', gzencode($data, 6));
                @unlink($rotated);
            }
            // Après rotation, réinitialiser hash chain
            $this->persistLastHash('ROTATED-' . $stamp);
        }
    }

    /**
     * Log générique enrichi.
     * @param string $level INFO|WARN|ERROR|SECURITY|AUDIT
     * @param string $action Action sémantique (login, backup, export_sql, profile_update, etc.)
     * @param array $data Données contextuelles libres
     * @param string|null $targetType Type de cible (user, table, db, row, system)
     * @param string|int|null $targetId Identifiant cible
     * @param string|null $status success|fail|partial
     * @param float|null $durationMs Durée en millisecondes
     * @param array|null $changes Liste de changements avant/après [['field'=>..,'old'=>..,'new'=>..], ...]
     * @param string|null $requestId Correlation id, auto si null
     */
    public function logAdvanced(
        string $level,
        string $action,
        array $data = [],
        ?string $targetType = null,
        $targetId = null,
        ?string $status = null,
        ?float $durationMs = null,
        ?array $changes = null,
        ?string $requestId = null
    ): void {
        $this->rotateIfNeeded();

        $tsFloat = microtime(true);
        $tsDate = gmdate('Y-m-d\TH:i:s', (int)$tsFloat) . sprintf('.%03dZ', (int)(($tsFloat - (int)$tsFloat) * 1000));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        $sessionId = session_id() ?: null;
        $userId = $_SESSION['client_id'] ?? null;
        $userEmail = $_SESSION['user_email'] ?? null;
        $requestId = $requestId ?? ($_SESSION['_request_id'] ?? bin2hex(random_bytes(6)));

        $entry = [
            'timestamp' => $tsDate,
            'level' => $level,
            'action' => $action,
            'user_id' => $userId,
            'email' => $userEmail,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'session_id' => $sessionId,
            'referrer' => $referrer,
            'request_id' => $requestId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'status' => $status,
            'duration_ms' => $durationMs !== null ? round($durationMs, 2) : null,
            'changes' => $changes,
            'data' => $data ?: null,
            'prev_hash' => $this->lastHash,
        ];
        // Nettoyage des nulls pour réduire la taille
        $entry = array_filter($entry, fn($v) => $v !== null);

        // Si session admin active, journaliser uniquement dans admin_activity.log et ne pas écrire dans activity.log
        if (!empty($_SESSION['is_admin'])) {
            // Utiliser l'aide dédiée pour les logs admin et inclure l'entrée complète
            log_admin_activity($action, "ADMIN:$level", ['entry' => $entry]);
            return;
        }

        $hashSource = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $entryHash = hash('sha256', ($this->lastHash ?? '') . $hashSource);
        $entry['entry_hash'] = $entryHash;
        $logLine = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        $this->persistLastHash($entryHash);
    }

    // Compat: ancienne méthode simple => INFO
    public function log(string $action, array $data = []): void {
        $this->logAdvanced('INFO', $action, $data);
    }

    public function logAuth(string $action, string $email, bool $success, ?string $error = null): void {
        $this->logAdvanced($success ? 'INFO' : 'SECURITY', $action, [
            'email' => $email,
            'success' => $success,
            'error' => $error,
            'admin' => (!empty($_SESSION['is_admin']) ? true : false),
        ], 'user', $email, $success ? 'success' : 'fail');
    }

    public function logProfileUpdate(int $userId, array $changes): void {
        // Transform into changes list without old values (not available here)
        $ch = [];
        foreach ($changes as $field => $newVal) {
            $ch[] = ['field' => $field, 'old' => null, 'new' => $newVal];
        }
        $this->logAdvanced('AUDIT', 'profile_update', [], 'user', $userId, 'success', null, $ch);
    }

    public function logPasswordChange(int $userId, bool $success): void {
        $this->logAdvanced($success ? 'AUDIT' : 'SECURITY', 'password_change', [], 'user', $userId, $success ? 'success' : 'fail');
    }

    public function startTimer(): float { return microtime(true); }
    public function endTimer(float $start): float { return (microtime(true) - $start) * 1000.0; }

    public function getRecentLogs(int $limit = 100): array {
        if (!file_exists($this->logFile)) { return []; }
        $lines = @file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) { return []; }
        $lines = array_slice($lines, -$limit);
        $logs = [];
        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) { $logs[] = $decoded; }
        }
        return $logs;
    }

    public function getStats(): array {
        $logs = $this->getRecentLogs(1000);
        $byAction = [];
        $byLevel = [];
        $topIps = [];
        $errors = 0;
        foreach ($logs as $l) {
            $byAction[$l['action']] = ($byAction[$l['action']] ?? 0) + 1;
            $byLevel[$l['level']] = ($byLevel[$l['level']] ?? 0) + 1;
            $ip = $l['ip'] ?? 'unknown';
            $topIps[$ip] = ($topIps[$ip] ?? 0) + 1;
            if (($l['level'] ?? '') === 'ERROR') { $errors++; }
        }
        arsort($byAction); arsort($byLevel); arsort($topIps);
        return [
            'actions' => $byAction,
            'levels' => $byLevel,
            'top_ips' => array_slice($topIps, 0, 10),
            'error_count' => $errors,
        ];
    }
}

// Instance globale
function get_logger(): Logger {
    static $logger = null;
    if ($logger === null) {
        $logger = new Logger();
    }
    return $logger;
}
// Admin activity logger helper: writes to a separate admin_activity.log
function log_admin_activity(string $action, string $message = '', array $data = []): void {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'ADMIN',
        'action' => $action,
        'message' => $message,
        'user_id' => $_SESSION['client_id'] ?? null,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ];
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $file = __DIR__ . '/admin_activity.log';
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}
