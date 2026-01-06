<?php
// Backup logs endpoint (admin-only)
session_start();
require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/logger.php';
require_admin();

$logger = get_logger();

// Paths
$logFile = __DIR__ . '/activity.log';
$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) { @mkdir($backupDir, 0777, true); }
// Purge backups > 30 jours
foreach (glob($backupDir.'/*.jsonl.gz') as $bf) {
    if (filemtime($bf) < (time() - 30*24*3600)) { @unlink($bf); }
}

// Rate limit backup: minimum 5 minutes between backups
$latest = null; $files = glob($backupDir.'/*.jsonl.gz');
if ($files) { rsort($files); $latest = $files[0]; }
if ($latest && (time() - filemtime($latest) < 300)) {
    // Too soon
    header('Location: ../../views/admin/dashboard/views_logs.php?backup=rate');
    exit;
}

if (!file_exists($logFile)) {
    header('Location: ../../views/admin/dashboard/views_logs.php?backup=missing');
    exit;
}

// Create gzip backup
$ts = date('Ymd-His');
$out = $backupDir . "/logs-{$ts}.jsonl.gz";
$in = fopen($logFile, 'rb');
$gz = gzopen($out, 'wb9');
if ($in && $gz) {
    while (!feof($in)) {
        $chunk = fread($in, 8192);
        gzwrite($gz, $chunk);
    }
    fclose($in);
    gzclose($gz);
    // Log audit
    $logger->logAdvanced('logs_backup', 'Backup des logs', ['file'=>basename($out), 'size'=>filesize($out)]);
    // Download directly if requested
    if (isset($_GET['download']) || isset($_POST['download'])) {
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="'.basename($out).'"');
        header('Content-Length: '.filesize($out));
        readfile($out);
        exit;
    }
    header('Location: ../../views/admin/dashboard/views_logs.php?backup=ok&file='.urlencode(basename($out)));
    exit;
}

header('Location: ../../views/admin/dashboard/views_logs.php?backup=error');
