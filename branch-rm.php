#! /usr/local/opt/php@7.2/bin/php
<?php
/**
 * Remove the current branch
 */
require_once "global.php";
exitIfNotClean();

$branch = getNodeBranch();
$existing = getCurrentBranch();
$choice = readline("Delete $existing branch and switch to $branch? yes, or another branch, or n?");
if ($choice === 'n') {
  exit();
}
if ($choice !== 'yes') {
  $branch = $choice;
}

system("git checkout $branch");
    system(" git -c diff.mnemonicprefix=false -c core.quotepath=false branch -D $existing");
