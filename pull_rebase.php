#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";

if ($node_branch = getNodeBranch()) {
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
