#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";
exitIfNotClean();
if (!isset($argv[1])) {
  echo "Please provide a url\n";
  exit(1);
}
$url = $argv[1];
system("wgit-apply.sh $url");
if (isGitStatusClean()) {
  print "🔥 Patch didn't apply.";
  exit();
}

system("git add .");
system("git commit -am \"applied $url\"");
