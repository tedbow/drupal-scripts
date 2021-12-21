#!/usr/bin/env php
<?php
/**
 * For this to work you must make a link to this file from
 * .git/hooks/pre-push
 *
 * This will stop 'git push' from working directly unless
 * 1. The remote name is specifed in the  'remotes_ignore' setting.
 * 2. A '.pre-push' file is present.
 *
 * To push instead run:
 *
 * @code do.php git:push
 *
 * @see \TedbowDrupalScripts\Command\GitPush
 */

use TedbowDrupalScripts\Settings;

require_once __DIR__ . '/vendor/autoload.php';
$remote = $argv[1];
if (!$remote) {
    print "⚠️Could not determine remote. Use the git-push.php file.\n";
    exit(1);
}
// Allow pushing to some remotes for backup without checks.
$ignore_remotes = Settings::getSetting('remotes_ignore');
if (in_array($remote, $ignore_remotes)) {
    exit(0);
}

if (!file_exists('.pre-push')) {
    print "💁🏼‍♂️Use the git-push.php file.\n";
    exit(1);
}
unlink('.pre-push');
