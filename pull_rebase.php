#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";
if (getCurrentBranch() === '8.9.x') {
  system('git pull');
  exit();
}

system("git checkout $current_head");
system('git pull');
system('git checkout -');
system("git rebase $current_head");
