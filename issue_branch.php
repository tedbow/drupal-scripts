#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";
function getPageTitle(string $url) {
    try {
      $urlContents = getUrlContents($url);

      $dom = new DOMDocument();
      @$dom->loadHTML($urlContents);

      $title = $dom->getElementsByTagName('title');

      return $title->item(0)->nodeValue;
    }
    catch (Exception $exception) {
      return '';
    }


}

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
  $urlContents = file_get_contents($url, FALSE, $context);
  return $urlContents;
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
//print "Title: " . getPageTitle("http://www.drupal.org/node/$issue") . "\n";
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

  if (readline("rebase against $current_head?") === 'y') {
    system("git rebase $current_head");
  }
}
