<?php
function getSetting($key) {
  static $settings = [];
  if (empty($settings)) {
    $settings = parse_ini_file('settings.ini');
  }
  return $settings[$key];
}
$global_options = getopt(null, ["h:"]);

if (isset($global_options['h'])) {
  $current_head = $global_options['h'];
}
else {

  $current_head = getSetting('default_head_branch');
}

/**
 * @return int|string
 */
function getIssueNumberArg() {
  foreach ($GLOBALS['argv'] as $i => $arg) {
    if ($i === 0) {
      continue;
    }
    if (strpos($arg, '--') === 0) {
      continue;
    }
    if (!is_numeric($arg)) {
      echo "Not valid issue number: $arg \n";
      exit(1);
    }
    return $arg;
  }
  echo "Please enter issue number\n";
  exit(1);

}

/**
 * @return mixed
 */
function getBranchIssue(): string {
  $branch = getCurrentBranch();
  $issue = explode('-', $branch)[0];
  if (!is_numeric($issue) || (int) $issue < 2000) {
    print "probably not issue number $issue\n";
    return '';
  }
  return $issue;
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
  return trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
}

function exitIfNotClean($print_output = FALSE): void {
  if (!isGitStatusClean($print_output)) {
    exit(1);
  }
}


function getURLDecodedJson(string $url) {
  return json_decode(file_get_contents($url));
}

/**
 * @param $issue
 */
function getIssueFiles($issue, $pattern): array {
  $node_info = getEntityInfo($issue);
  if (empty($node_info->field_issue_files)) {
    return [];
  }
  else {
    $files = [];
    foreach ($node_info->field_issue_files as $file_info) {

      if ($file_info->display) {
        $file = getURLDecodedJson($file_info->file->uri . '.json');
        if (preg_match($pattern, $file->name)) {
          $files[] = $file;
        }
      }

    }
    return $files;
  }
}
/**
 * @param $issue
 *
 * @return mixed
 */
function getEntityInfo($issue, $type = 'node'): object {
  $url = "https://www.drupal.org/api-d7/$type/$issue.json";
  return getURLDecodedJson($url);
}

function getNodeBranch($issue = NULL) {
  if (empty($issue)) {
    $issue = getBranchIssue();
  }
  if (empty($issue)) {
    return '';
  }
  $version = getEntityInfo($issue)->field_issue_version;
  if (strpos($version, '-dev') !== FALSE ) {
    return str_replace('-dev', '', $version);
  }
  return '';

}

function getTimeFromTimeStamp($timestamp) {
  $dt = new DateTime("now", new DateTimeZone(getSetting('timezone'))); //first argument "must" be a string
  $dt->setTimestamp($timestamp); //adjust the object to correct timestamp
  //echo $dt->format('d.m.Y, H:i:s');
  return $dt->format('d.m.Y, H:i:s');
}

function getIssueStatus($status_code) {
  $statuses = [
    '1' => 'active',
    '2' => 'fixed',
    '3' => 'closed (duplicate)',
    '4' => 'postponed',
    '5' => 'closed (won\'t fix)',
'6' => 'closed (works as designed)',
'7' => 'closed (fixed)',
'8' => 'needs review',
'13' => 'needs work',
'14' => 'reviewed & tested by the community',
'15' => 'patch (to be ported)',
'16' => 'postponed (maintainer needs more info)',
'17' => 'closed (outdated)',
'18' => 'closed (cannot reproduce)',
  ];
  return $statuses[$status_code];
}
