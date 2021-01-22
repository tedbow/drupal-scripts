<?php
function ensureRoot() {
  foreach (['index.php', 'update.php', 'README.txt'] as $file) {
    if (!file_exists($file)) {
      print "File $file not found. Only run in drupal root.\n";
      exit(1);
    }
  }
}
function getSetting($key) {
  static $settings = [];
  if (empty($settings)) {
    $settings = parse_ini_file('settings.ini');
  }
  return $settings[$key];
}
$global_options = getopt(null, ["h:", "no-rebase", "no-tests"]);

if (isset($global_options['h'])) {
  $current_head = $global_options['h'];
}
elseif ($node_branch = getNodeBranch()) {
    $current_head = $node_branch;
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
 * Get the d.o issue for the current branch.
 *
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

/**
 * Run exec and split into lines.
 * @param $string
 *
 * @return string[]
 */
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

function getMergeBase():?string {
    $current_branch = getCurrentBranch();
    $issue_branch = getNodeBranch();
    if (!($current_branch && $issue_branch)) {
        throw new Exception("current branch or issue not found");
    }
    $commit = trim(shell_exec("git merge-base $issue_branch $current_branch"));
    return $commit ?? NULL;

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

/**
 * Gets the branch an issue is against.
 * @param null|string $issue
 *
 * @return string
 *   The branch the issue is against.
 */
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

function getDiffFiles($branch) {
    return shell_exec_split("git diff $branch --name-only");
}

function runPhpcs($diff) {
    ensureRoot();
    $exts = ['inc', 'install', 'module', 'php', 'profile', 'test', 'theme', 'yml'];
    $phpcs_out = [];
    $phpcs_error_files = [];
    foreach (getDiffFiles($diff) as $getDiffFile) {
        if (in_array(pathinfo($getDiffFile)['extension'], $exts)) {
            $output = shell_exec_split("./vendor/bin/phpcs $getDiffFile --standard=core/phpcs.xml.dist");
            if ($output) {
                $phpcs_error_files[] = $getDiffFile;
                $phpcs_out = array_merge($phpcs_out, $output);
            }


        }
    }

    if ($phpcs_out) {
        print implode("\n", $phpcs_out);
        if (readline("run phpcbf to fix?️") === 'y') {
            foreach ($phpcs_error_files as $phpcs_error_file) {
                system("./vendor/bin/phpcbf $phpcs_error_file --standard=core/phpcs.xml.dist");
            }
        }
        print "☹️☹️☹️☹️☹️ PHPCS Failed ☹️☹️☹️☹️☹️\n";
        exit(1);
    }
    else{
        print "🎉🎉🎉🎉🎉 PHPCS Pass 🎉🎉🎉🎉🎉\n";
    }
}

/**
 * @param $current_head
 */
function checkForDebug($current_head): void
{
    $diff_command = "git diff $current_head";

    $diff_output = shell_exec($diff_command);
    if (strpos($diff_output, '/Users/ted.bowman') !== false) {
        print $diff_output;
        print "🙀🙀🙀🙀🙀🙀🙀 Did you leave a debug statement in?\n";
        exit(1);
    }
}
function yarnInstall() {
    chdir('core');
    system('rm -rf node_modules');
    system('yarn install');
    chdir('..');
}
/**
 *
 */
function runCSpell($branch) {
    chdir('core');
    foreach (getDiffFiles($branch) as $getDiffFile) {
        $getDiffFile = str_replace('core/', '', $getDiffFile);
        $cmd = "yarn run cspell $getDiffFile";
        //print "💁🏼‍♂️: Running $cmd\n";
        $result_code = NULL;$output = NULL;
        print "cspell: $getDiffFile\n";
        exec("yarn run cspell $getDiffFile", $output, $result_code);
        if ($result_code !== 0) {
            print "☹️☹️☹️☹️☹️ Cspell Failed ☹️☹️☹️☹️☹️\n";
            print_r($output);
            chdir('..');
            exit(1);
        }
        /*$noErrors = false;
        print_r($lines);
        foreach ($lines as $line) {
            //print "fff: $line";
            if (strpos($line, 'Issues found: 0 in 0 files') !== false) {
                $noErrors = TRUE;
                break;
            }
        }
        if (!$noErrors) {
            print "☹️☹️☹️☹️☹️ Cspell Failed ☹️☹️☹️☹️☹️\n" . implode("\n", $lines);
            chdir('..');
            exit(1);
        }*/


    }
    print "🎉🎉🎉🎉🎉 CSpell Passed 🎉🎉🎉🎉🎉\n";
    chdir('..');

}

function getFirstCalledFile() {
    return pathinfo($_SERVER["SCRIPT_FILENAME"])['basename'];
}

