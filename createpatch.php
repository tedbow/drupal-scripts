#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";
require_once "pull_rebase.php";
require_once "rundiff_tests.php";

exitIfNotClean();



$issue = getBranchIssue();
if (empty($issue)) {
  exit(1);
}
$diff_command = "git diff $current_head";

$diff_output = shell_exec($diff_command);
if (strpos($diff_output, '/Users/ted.bowman') !== FALSE) {
  print $diff_output;
  print "🙀🙀🙀🙀🙀🙀🙀 Did you leave a debug statement in?\n";
  exit(1);
}

if (runDiffTests($current_head)) {
  print "🎉🎉🎉🎉🎉 All Pass 🎉🎉🎉🎉🎉\n";
}
else {
  if (readline("☹☹☹☹☹☹ Tests failed, still make patch?️") !== 'y') {
    exit();
  }
}

$node_info = getEntityInfo($issue);
$comment_number = ((int) $node_info->comment_count) + 1;
$patch_name = "$issue-$comment_number.patch";
print "✂️ Creating patch $patch_name\n\n";
shell_exec("git diff $current_head > /Users/ted.bowman/Sites/www/$patch_name");

$log_lines = shell_exec_split('git log  --pretty=oneline --max-count=15');
array_shift($log_lines);
// Look if last commit is from actual core
if (strpos($log_lines[0], 'Issue #') !== FALSE) {
    print "⚠️No previous commits, no interdiff\n";
}
else {
  print "Which commit for interdiff?\n\n";
  print_r($log_lines);

  $line_number = readline("X to exit:");
  if ($line_number === 'x') {
    exit();
  }

  $from_comment = (int) readline("from comment #?");
  $line_number = (int) $line_number;
  $line = $log_lines[$line_number];
  $parts = explode(' ', $line);
  shell_exec("git diff {$parts[0]} > /Users/ted.bowman/Sites/www/interdiff-$from_comment-$comment_number.txt");
}






