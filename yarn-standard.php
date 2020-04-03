#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";
exitIfNotClean(TRUE);
system("cd core");
system('rm -rf node_modules');
system('yarn install');
system('yarn build:js');
exitIfNotClean(TRUE);
$errors = system('yarn run lint:core-js-passing');
if (strpos($errors, 'Done in') !== 0) {
  print "\🔥Lint errors";
  exit();
}
system('yarn prettier');
exitIfNotClean(TRUE);




