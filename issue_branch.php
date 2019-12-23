#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";
exitIfNotClean();




$issue = getIssueNumberArg();
if (empty($issue)) {
    print "asdf";
}

print "✍️ Title: " . getEntityInfo($issue)->title . "\n";
$branches = shell_exec_split("git branch --l \*$issue\*");



if ($branches) {
    if (count($branches) === 1) {
        system("git checkout " . $branches[0]);
    }
    else {
      print_r($branches);
      $choice = (int) readline("which branch to checkout?");
      if (!isset($branches[$choice])) {
        echo "Not valid branch: $choice \n";
        exit(1);
      }
      shell_exec("git checkout {$branches[$choice]}");
    }

  if ($node_branch = getNodeBranch()) {
    $current_head = $node_branch;
  }
  if (readline("rebase against $current_head?") === 'y') {
      system("git checkout $current_head");
      system("git pull");
      system("git checkout -");
    system("git rebase $current_head");
  }
}
else {
    print "🚨 No existing branch for issue!\n";
  if ($patches = getIssueFiles($issue, '/\.patch/')) {


      print "Create a new branch from patch against $current_head?\n\n";
      $list = [];
    foreach ($patches as $patch) {
      $list[] = $patch->name;
      }
    print_r($list);
    $choice = (int) readline("patch?");
    system("new-branch.sh {$patches[$choice]->url} $current_head");
  }
  else {
      print "😱 No patches!";
  }


}
