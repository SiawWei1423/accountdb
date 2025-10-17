<?php
$logFile = __DIR__ . '/time_cost_errors.log';
header('Content-Type: text/plain; charset=utf-8');
if (!file_exists($logFile)) {
    echo "No log file found\n";
    exit;
}
$lines = array_reverse(array_filter(array_map('trim', file($logFile))));
$last = array_slice($lines, 0, 200);
foreach ($last as $line) {
    echo $line . "\n";
}
