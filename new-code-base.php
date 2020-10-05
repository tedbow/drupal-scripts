#! /usr/local/opt/php@7.3/bin/php
<?php
require_once "global.php";
ensureRoot();
system("rm -rf vendor && composer install");
if (isGitStatusClean()) {
    system('composer require drush/drush');
    system('git reset --h');
}
else {
    print "drush not installed";
}