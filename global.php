<?php
$global_options = getopt(null, ["h:"]);
if (isset($global_options['h'])) {
  $current_head = $global_options['h'];
}
else {
  $current_head = "8.9.x";
}

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
  $output = preg_split('/\n+/', trim($output));
  $output = array_map(function ($line) {
    return trim($line);
  }, $output);

  return array_filter($output);

}

function getCurrentBranch() {
  return shell_exec('git rev-parse --abbrev-ref HEAD');
}
if (!isGitStatusClean()) {
  exit(1);
}


