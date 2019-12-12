<?php
$current_head = "8.9.x";
function isGitStatusClean($print_output = TRUE) {
  $status_output = shell_exec('git status');
  if (strpos($status_output, 'nothing to commit, working tree clean') === FALSE) {
    if ($print_output) {
      print $status_output;
    }
    return FALSE;
  }
  return TRUE;
}
function shell_exec_split($string) {
  $output = shell_exec($string);
  return preg_split('/\n+/', trim($output));
}

function getCurrentBranch() {
  return shell_exec('git rev-parse --abbrev-ref HEAD');
}
if (!isGitStatusClean()) {
  exit(1);
}


