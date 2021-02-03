#!/usr/bin/env php
<?php
if (!file_exists('.pre-push')) {
    print "💁🏼‍♂️Use the git-push.php file.\n";
    exit(1);
}
unlink('.pre-push');
