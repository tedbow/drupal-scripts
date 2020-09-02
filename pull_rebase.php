#! /usr/local/opt/php@7.3/bin/php
<?php
require_once "global.php";
exitIfNotClean(TRUE);
if (isset($global_options['no-rebase'])) {
    print "No rebase\n";
    return;
}

if (empty($global_options['h']) && $node_branch = getNodeBranch()) {
    $current_head = $node_branch;
}


if (getCurrentBranch() === $current_head) {
  system('git pull');
  exit();
}

system("git checkout $current_head");
system('git pull');
system('git checkout -');
system("git rebase $current_head");
