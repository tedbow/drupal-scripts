#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use TedbowDrupalScripts\Command\IssueBranch;
use TedbowDrupalScripts\Command\IssueInfo;

$app = new Application('Single Command Application with Symfony', 'v1.0');
$app->add(new IssueInfo());
$app->add(new IssueBranch());
$app->run();

