#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";

/**
 * @param string $url
 *
 * @return false|string
 */
function getUrlContents(string $url) {
  $context = stream_context_create(
    [
      "http" => [
        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
      ]
    ]
  );
  $urlContents = file_get_contents($url);
  return $urlContents;
}

function getURLDecodedJson(string $url) {
    return json_decode(getUrlContents($url));
}

if (!isset($argv[1])) {
  echo "Please enter issue number\n";
  exit(1);
}

if (!is_numeric($argv[1])) {
  echo "Not valid issue number: $argv[1] \n";
  exit(1);
}
$issue = $argv[1];

if (isset($argv[2])) {
    $current_head = $argv[2];
}
print "✍️ Title: " . getNodeInfo($issue)->title . "\n";
$branches = shell_exec_split("git branch --l \*$issue\*");
/**
 * @param $issue
 */
function getIssueFiles($issue, $pattern): array {
  $node_info = getNodeInfo($issue);

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
function getNodeInfo($issue): object {
  $node_info = getURLDecodedJson("https://www.drupal.org/api-d7/node.json?nid=$issue");
  return $node_info->list[0];
}

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
