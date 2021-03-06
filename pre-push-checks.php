#!/usr/bin/env php
<?php
require_once 'global.php';
require_once 'diff_phpcs.php';
require_once 'rundiff_tests.php';
if ($mergeBase = getMergeBase()) {
    checkForCommonErrors($mergeBase);
    runPhpcs($mergeBase);
    runCSpell($mergeBase);
    runDiffTests($mergeBase);
    print "🙏🏻All good!!\n";
}
else {
    throw new Exception("no mergebase");
}
