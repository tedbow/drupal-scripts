#!/usr/bin/env php
<?php
$remote = $argv[1];
if (!$remote) {
    print "⚠️Could not determine remote. Use the git-push.php file.\n";
    exit(1);
}
// Allow pushing to my backup on github without checks.
if ($remote === 'github') {
    exit(0);
}

if (!file_exists('.pre-push')) {
    print "💁🏼‍♂️Use the git-push.php file.\n";
    exit(1);
}
unlink('.pre-push');
