#! /usr/local/opt/php@7.3/bin/php
<?php
require_once "global.php";
exitIfNotClean(TRUE);




$issue = getIssueNumberArg();
if (!isset($global_options['h'])) {
  $current_head = getNodeBranch($issue);
}



print "✍️ Title: " . getEntityInfo($issue)->title . "\n";
$branches = shell_exec_split("git branch --l \*$issue\*");
$branches = array_map(function ($branch) {
    return trim(str_replace('* ', '', $branch));
}, $branches);
$current_branch = getCurrentBranch();


if ($branches) {
  if (array_search($current_branch, $branches) !== FALSE) {
    print "🚨 Currently on $current_branch\n";
  }

      print_r($branches);
      $choice = (int) readline("which branch to checkout?");
      if (!isset($branches[$choice])) {
        echo "Not valid branch: $choice \n";
        exit(1);
      }
      shell_exec("git checkout {$branches[$choice]}");

  if ($node_branch = getNodeBranch()) {
    $current_head = $node_branch;
  }
  if (getMergeBase()) {
      print "\nℹ️  Probably Merge request. No rebase\n";
  }
  else {
      if (readline("rebase against $current_head?") === 'y') {
          system("git checkout $current_head");
          system("git pull");
          system("git checkout -");
          system("git rebase $current_head");
      }
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
