#! /usr/local/opt/php@7.3/bin/php
<?php
require_once "global.php";


if (getCurrentBranch() === $current_head) {
    print "Already on $current_head\n";
    exit();
}
runPhpcs($current_head);
