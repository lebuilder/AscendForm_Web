<?php
// Backup admin logs endpoint (admin-only)
session_start();
require_once __DIR__ . '/../../inc/auth.php';
require_admin();

$logFile = __DIR__ . '/admin_activity.log';
$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) { @mkdir($backupDir, 0777, true); }
// Purge >30j
foreach (glob($backupDir.'/*.admin.jsonl.gz') as $bf) {
    if (filemtime($bf) < (time() - 30*24*3600)) { @unlink($bf); }
}

if (!file_exists($logFile)) {
    header('Location: ../../views/admin/dashboard/views_logs.php?admin_backup=missing');
    exit;
}

$latest = null; $files = glob($backupDir.'/*.admin.jsonl.gz');
if ($files) { rsort($files); $latest = $files[0]; }
if ($latest && (time() - filemtime($latest) < 300)) {
    header('Location: ../../views/admin/dashboard/views_logs.php?admin_backup=rate');
    exit;
}

$ts = date('Ymd-His');
$out = $backupDir . "/admin-{$ts}.admin.jsonl.gz";
$in = fopen($logFile, 'rb');
$gz = gzopen($out, 'wb9');
if ($in && $gz) {
    while (!feof($in)) { gzwrite($gz, fread($in, 8192)); }
    fclose($in); gzclose($gz);
    if (isset($_GET['download']) || isset($_POST['download'])) {
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="'.basename($out).'"');
        header('Content-Length: '.filesize($out));
        readfile($out);
        exit;
    }
    header('Location: ../../views/admin/dashboard/views_logs.php?admin_backup=ok&file='.urlencode(basename($out)));
    exit;
}
header('Location: ../../views/admin/dashboard/views_logs.php?admin_backup=error');
