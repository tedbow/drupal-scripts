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



function getCurrentBranch() {
  return trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
}






function getURLDecodedJson(string $url) {
  return json_decode(file_get_contents($url));
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
        $choice = readline("(r)run phpcbf to fix? or (i)ignore? or exit️");
        switch ($choice) {
            case 'y':
                if (readline("run phpcbf to fix?️") === 'y') {
                    foreach ($phpcs_error_files as $phpcs_error_file) {
                        system("./vendor/bin/phpcbf $phpcs_error_file --standard=core/phpcs.xml.dist");
                    }
                }
                print "☹️☹️☹️☹️☹️ PHPCS Failed ☹️☹️☹️☹️☹️\n";
                exit(1);
            case 'i':
                print "💁🏼‍♂️Ignoring phpcs!\n";
                return;
            default:
                exit(1);

        }

    }
    else{
        print "🎉🎉🎉🎉🎉 PHPCS Pass 🎉🎉🎉🎉🎉\n";
    }
}

/**
 * @param $current_head
 */
function checkForCommonErrors($current_head): void
{
    if (ini_get('xdebug.default_enable')) {
        print "\n️☹️☹️☹️☹️ Xdebug is on, tests will take longer! ☹️☹️☹️☹️\n";
    }
    $diff_command = "git diff $current_head";
    $diff_output = shell_exec_split($diff_command);
    $current_file = '';
    $last_error_file = '';
    $error_patterns = [
      '🤦🏼‍♂️Debug left in' => '/Users\/ted\.bowman/',
        'Return hint needs Space' => '/function.*\):[^ ].* {/',
        'CamelCase argument' => '/function.*\(.*\$[^ ]*([A-Z])/',
        'Camelcase var' => '/^\s*\$[a-z]*([A-Z])/',
        'nonCamelCase prop' => '/(protected|public|private) \$[a-z]*_/',
      'camel case without scope' => '/[^(protected|public|private)] \$[a-z]*([A-Z])/',
      'no return type' => '/(protected|public|private) function .*(?<!__construct)\(.*\)[^\:]/',
        'id not cap' => '/ [iI]d([^a-z])/',
         'ids not cap' => '/ [iI]ds([^a-z])/',
        'yml space' => '/\[ /',
        'THROW' => '/' . preg_quote('@throws \Behat\Mink\Exception') . '/',
      'self assert' => '/' . preg_quote('self::assert') . '/',
      'return generic array' => '/' . preg_quote('* @return array') . '/',
        'return NULL cap' => '/@return .*\|NULL/',
        'constructor doc' => '/\* [A-z]* constructor\./',
      'verb tense' => '/\* (Get|Set|Create|Construct|Test that) /',
        'inheritdoc' => '/(inheritDoc|\* \@inheritdoc)/',
      'data provider is 2 words' => '/\* Dataprovider for/i',
        'the nonsense' => '/(^| |\.|,)(t|T)he (the|this|these|a|of|an)($| |\.)/',
        // add more nonsense here
        'nonsense' => '/(to as)/',
      'is_null call, use === NULL' => '/is_null\(/',
      '==, Always use === ' => '/ == /',
    ];
    $found_error = FALSE;
    foreach ($diff_output as $diff_line) {
        if (strpos($diff_line, '+++ b/') === 0) {
            $current_file = str_replace('+++ b/', '', $diff_line);
        }
        if (strpos($diff_line, '+ ') === 0) {
            foreach ($error_patterns as $problem => $error_pattern) {
                if (preg_match($error_pattern, $diff_line)) {
                    if ($last_error_file !== $current_file) {
                        print "\n☣️ File errors in $current_file\n";
                        $last_error_file = $current_file;
                    }
                    print "⚠️ $problem: $diff_line\n";
                    $found_error = TRUE;
                }
            }
        }
    }
    if ($found_error) {
        exit(1);
    }
    print "🎉🎉🎉 No common nits 🎉🎉🎉\n";
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
    $gitDiffFiles = [];
    foreach (getDiffFiles($branch) as $getDiffFile) {
        $getDiffFile = str_replace('core/', '', $getDiffFile);
        $gitDiffFiles[] = $getDiffFile;
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
    $result_code = NULL;$output = NULL;
    exec("yarn run cspell " . implode(' ', $gitDiffFiles), $output, $result_code);
    if ($result_code !== 0) {
        print "☹️☹️☹️☹️☹️ Cspell Failed ☹️☹️☹️☹️☹️\n";

        print "\n🔥" . implode("\n🔥", array_slice($output, 2, -1)) . "\n";
        chdir('..');
        exit(1);
    }
    print "🎉🎉🎉🎉🎉 CSpell Passed 🎉🎉🎉🎉🎉\n";
    chdir('..');

}

function getFirstCalledFile() {
    return pathinfo($_SERVER["SCRIPT_FILENAME"])['basename'];
}

