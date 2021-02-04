#!/usr/bin/env php
<?php
require_once 'global.php';
require_once 'diff_phpcs.php';
require_once 'rundiff_tests.php';
if ($mergeBase = getMergeBase()) {
    checkForDebug($mergeBase);
    runPhpcs($mergeBase);
    runCSpell($mergeBase);
    runDiffTests($mergeBase);
    print "🙏🏻All good!!\n";
    // Set a flag file for pre-push
    touch('.pre-push');
    array_shift($argv);
    $args_string  = implode(' ', $argv);
    system("git push $args_string");
}
else {
    throw new Exception("no mergebase");
}

