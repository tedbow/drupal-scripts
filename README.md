# Tedbow Drupal Core Development Console Command

## Purpose
This Symfony console is intended run checks on Drupal core development to make developing easier.


## Instructions
1. `composer install`
2. add `do.php` to your PATH
3. To enforce checks before `git push` see `pre-push.php`.

## Current commands
* issue:info : Get basic issue info
* issue:branch : Creates branch based off a patch for an issue
* run:checks : Runs all checkers
* git:push : Runs checkers before pushing
* newcode : Composer reinstall and install drush. Leaves git clean.
* git:name-status : Shows diff against patch branch or since merge request started.
* git:rm_branch : Remove current branch
* xdebug : Checks xdebug status
* patch:create : Creates a patch and optionally an interdiff for an issue
* issue:followers : Show issue followers
* sel-server : Starts the SeleniumServer for JS tests
* issue:diffs : Applies interdiffs for a patch issue
* checker:nits : Checks for common nits defined in error_patterns.yml
* checker:phpcs : Runs PHPCS and optionally phpcbf
* checker:cspell : Runs cspell
* checker:phpunit : Runs phpunit test for changed tests. All unit tests for changed modules
* checker:core : Runs commit-code-check.sh
