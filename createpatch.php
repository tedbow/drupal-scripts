#! /usr/local/opt/php@7.3/bin/php
<?php
require_once "global.php";
require_once "pull_rebase.php";
require_once "rundiff_tests.php";

exitIfNotClean(TRUE);



$issue = getBranchIssue();
if (empty($issue)) {
  exit(1);
}


checkForDebug($current_head);
if (!isset($global_options['no-tests'])) {
  if (runDiffTests($current_head)) {
    print "🎉🎉🎉🎉🎉 All Pass 🎉🎉🎉🎉🎉\n";
  }
  else {
    if (readline("☹☹☹☹☹☹ Tests failed, still make patch?️") !== 'y') {
      exit();
    }
  }
}

// ******* PHPCS **********
runPhpcs($current_head);

// ******* END PHPCS **********


$node_info = getEntityInfo($issue);
$comment_number = ((int) $node_info->comment_count) + 1;
$patch_name = "$issue-$comment_number.patch";
print "✂️ Creating patch $patch_name\n\n";
// shell_exec("git diff $current_head -C35 > /Users/ted.bowman/sites/$patch_name");
shell_exec("git diff $current_head > /Users/ted.bowman/sites/$patch_name");

$display_lines = shell_exec_split('git log --pretty=format:"%s - %aI" --max-count=15');
$log_lines = shell_exec_split('git log --pretty=format:"%H" --max-count=15');
array_shift($log_lines);
array_shift($display_lines);
// Look if last commit is from actual core
if (strpos($display_lines[0], 'Issue #') !== FALSE) {
    print "⚠️No previous commits, no interdiff\n";
}
else {
  print "Which commit for interdiff?\n\n";
  print_r($display_lines);

  $line_number = readline("X to exit:");
  if ($line_number === 'x') {
    exit();
  }

  $from_comment = (int) readline("from comment #?");
  $line_number = (int) $line_number;
  $line = $log_lines[$line_number];
  $parts = explode(' ', $line);
  shell_exec("git diff {$parts[0]} > /Users/ted.bowman/sites/interdiff-$from_comment-$comment_number.txt");
}
