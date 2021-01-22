#! /usr/local/opt/php@7.3/bin/php
<?php
require_once "global.php";


if (getCurrentBranch() === $current_head) {
    print "Already on $current_head\n";
    exit();
}
if (getFirstCalledFile() === 'diff_phpcs.php') {
    runPhpcs($current_head);
}

