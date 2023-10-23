<?php

namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GitBisectCommandBase extends CommandBase {

  protected const CONFIRM_XDEBUG = TRUE;

  protected string $patch;
  protected string $test;
  /**
   * @inheritDoc
   */
  protected function configure()
  {
    parent::configure();
    $this->addArgument('patch', InputArgument::REQUIRED, 'Patch to apply.');
    $this->addArgument('test', InputArgument::REQUIRED, 'Test file to run.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->patch = realpath($input->getArgument('patch'));
    $this->test = $input->getArgument('test');
    if (!$this->patch) {
      $this->style->error("Must provide files");
      return 127;
    }
    return static::SUCCESS;
  }

  /**
   * @param int $return
   *
   * @return int
   */
  protected function runTest(): bool {
    $return = NULL;
    $test = realpath($this->test);
    if (!$test) {
      throw new \Exception("test doesn't exist $test");
    }
    system('vendor/bin/phpunit --configuration core/phpunit.xml ' . $this->test,
      $return);
    return $return === 0;
  }


}
