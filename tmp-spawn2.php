<?php
use WPEasy\BricksStatic\CLI\Background;
use WPEasy\BricksStatic\Sync\Runner;
use WPEasy\BricksStatic\Sync\Job;
Job::clear();
Runner::start('check');
echo 'spawned=' . var_export(Background::spawn_run(), true) . "\n";
$done = false;
for ($i = 0; $i < 30; $i++) {
    sleep(3);
    $s = Runner::status();
    echo sprintf("  t+%02ds phase=%s pages=%d assets=%d\n", ($i+1)*3, $s['phase'] ?? 'idle', $s['counts']['pagesDone'] ?? 0, $s['counts']['assetsDone'] ?? 0);
    if (empty($s['running'])) { $done = true; break; }
}
echo $done ? "DONE\n" : "STALLED\n";
