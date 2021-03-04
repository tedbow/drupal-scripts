#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use TedbowDrupalScripts\Command\Checkers\CSpellChecker;
use TedbowDrupalScripts\Command\Checkers\NitChecker;
use TedbowDrupalScripts\Command\Checkers\PhpcsChecker;
use TedbowDrupalScripts\Command\Checkers\PHPUnitChecker;
use TedbowDrupalScripts\Command\DiffStatus;
use TedbowDrupalScripts\Command\GitPush;
use TedbowDrupalScripts\Command\GitRmBranch;
use TedbowDrupalScripts\Command\IssueBranch;
use TedbowDrupalScripts\Command\IssueInfo;
use TedbowDrupalScripts\Command\NewCodeBase;
use TedbowDrupalScripts\Command\RunChecks;
use TedbowDrupalScripts\ScriptApplication;

$app = new ScriptApplication('tedbow Drupal scripts', 'v1.0');

$app->add(new IssueInfo());
$app->add(new IssueBranch());
$app->add(new RunChecks());
$app->add(new GitPush());
$app->add(new NewCodeBase());
$app->add(new DiffStatus());
$app->add(new GitRmBranch());

// Add the checker commands in the order they should run.
$app->add(new NitChecker());
$app->add(new PhpcsChecker());
$app->add(new CSpellChecker());
$app->add(new PHPUnitChecker());
$app->run();

