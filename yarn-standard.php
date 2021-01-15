#! /usr/local/opt/php@7.3/bin/php
<?php
require_once "global.php";
ensureRoot();
exitIfNotClean(TRUE);
chdir('core');
system('rm -rf node_modules');
system('yarn install');
system('yarn build:js');
exitIfNotClean(TRUE);
$errors = system('yarn run lint:core-js-passing');
if (strpos($errors, 'Done in') !== 0) {
  print "\🔥Lint errors";
  chdir('..');
  exit();
}
system('yarn prettier');
exitIfNotClean(TRUE);




