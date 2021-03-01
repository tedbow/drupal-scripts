#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use TedbowDrupalScripts\Command\CSpellChecker;
use TedbowDrupalScripts\Command\GitPush;
use TedbowDrupalScripts\Command\IssueBranch;
use TedbowDrupalScripts\Command\IssueInfo;
use TedbowDrupalScripts\Command\NewCodeBase;
use TedbowDrupalScripts\Command\NitChecker;
use TedbowDrupalScripts\Command\PhpcsChecker;
use TedbowDrupalScripts\Command\PHPUnitChecker;
use TedbowDrupalScripts\Command\RunChecks;
use TedbowDrupalScripts\ScriptApplication;

$app = new ScriptApplication('tedbow Drupal scripts', 'v1.0');
$app->add(new IssueInfo());
$app->add(new IssueBranch());
$app->add(new RunChecks());
$app->add(new GitPush());
$app->add(new NewCodeBase());

// Add the checker commands in the order they should run.
$app->add(new NitChecker());
$app->add(new PhpcsChecker());
$app->add(new CSpellChecker());
$app->add(new PHPUnitChecker());
$app->run();

