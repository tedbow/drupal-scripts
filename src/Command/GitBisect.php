<?php

namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GitBisect extends CommandBase {

  protected static $defaultName = 'git:push';
  protected const CONFIRM_XDEBUG = false;

  /**
   * @inheritDoc
   */
  protected function configure()
  {
    parent::configure();
    $this->setAliases(['bisect']);
    $this->setDescription('Call back for git bisect run');
    $this->addArgument('patch', InputArgument::REQUIRED, 'Patch to apply.');
    $this->addArgument('test', InputArgument::REQUIRED, 'Test file to run.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $patch = realpath($input->getArgument('patch'));
    $test = realpath($input->getArgument('test'));
    if (!$patch || !$test) {
      $this->style->error("Must provide files");
      return 127;
    }
    $return = NULL;
    system("git apply $patch", $return);
    if ($return !== 0) {
      // According to git docs 125 means it can't be tested. Not sure what bisect
      // does in this case.
      return 125;
    }
    system('rm -rf vendor');
    system('composer install');

    system("vendor/bin/phpunit --configuration core/phpunit.xml $test", $return);
    system(" git apply -R $patch");
    if ($return !== 0) {
      return 2;
    }
    return static::SUCCESS;
  }

}
