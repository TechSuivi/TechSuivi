<?php
// /TechSuivi/web/src/api/ping.php

// Disable error display to prevent messing up JSON
ini_set('display_errors', 0);
error_reporting(0); // Suppress all errors for cleaner output
header('Content-Type: application/json');

// Check if request is POST or GET (support both for testing flex)
$ip = trim($_REQUEST['ip'] ?? '');

if (empty($ip)) {
    echo json_encode(['success' => false, 'message' => 'IP missing']);
    exit;
}

// Basic IP validation
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(['success' => false, 'message' => 'Invalid IP format']);
    exit;
}

// Prepare Diagnostics
$debugInfo = [];
$debugInfo['php_user'] = @exec('whoami');

// Check ping binary locations
$candidates = [
    '/usr/bin/ping',
    '/bin/ping', 
    'ping'
];
$pingBin = 'ping';
$foundBin = false;

foreach ($candidates as $c) {
    if (file_exists($c) && is_executable($c)) {
        $pingBin = $c;
        $foundBin = true;
        $debugInfo['found_bin'] = $c;
        break;
    }
}
if (!$foundBin) {
    // Try check via `which`
    $which = @exec('which ping');
    if ($which) {
        $pingBin = trim($which);
        $debugInfo['which_bin'] = $pingBin;
    }
}

// Execute ping
// -c 1 : 1 packet
// -W 2 : 2 seconds timeout
$cmd = "$pingBin -c 1 -W 2 " . escapeshellarg($ip) . " 2>&1";
$output = [];
$status = -1;

exec($cmd, $output, $status);

echo json_encode(['success' => ($status === 0)]);
exit;
?>
