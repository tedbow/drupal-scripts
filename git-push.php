#!/usr/bin/env php
<?php
require_once 'global.php';
if (!isGitStatusClean()) {
    print "🔥 Not clean";
    exit();
}
require_once 'pre-push-checks.php';
touch('.pre-push');
array_shift($argv);
// filter our own args
$filtered_arg = array_filter($argv, function ($arg) { return !in_array($arg, ['--no-tests', '--no-rebase']);});
$args_string  = implode(' ', $filtered_arg);

system("git push $args_string");

