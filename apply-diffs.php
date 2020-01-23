#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";
exitIfNotClean();
$issue_num = getBranchIssue();

$diffs = getIssueFiles($issue_num, '/interdiff/');
$list = [];
foreach ($diffs as $diff) {
  $list[] = $diff->name;
}

print_r($list);
print "Last commit \n";
system('git log --pretty=format:%s --max-count=1');
print "\n";
$choice = (int) readline("apply diff starting from?");
foreach ($diffs as $i => $diff) {
    if ($i < $choice) {
        continue;
    }
    system("wgit-apply.sh " . $diff->url);
    if (isGitStatusClean()) {
        print "🔥 Didn't apply\n";
        exit(1);
    }
    system('git add .');
    system('git status');
    system("git commit -am '➕ {$diff->url}'");

}



