<?php

namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GitBisect extends GitBisectCommandBase
{

    protected static $defaultName = 'git:bisect';

  /**
   * @inheritDoc
   */
    protected function configure()
    {
        parent::configure();
        $this->setAliases(['bisect']);
        $this->setDescription('Call back for git bisect run');
    }

  /**
   * {@inheritdoc}
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $success = parent::execute($input, $output);
        if ($success !== static::SUCCESS) {
            return $success;
        }
        $return = null;
        system('git apply ' . $this->patch, $return);
        if ($return !== 0) {
          // According to git docs 125 means it can't be tested. Not sure what bisect
          // does in this case.
            return 125;
        }
        $this->composerInstall();
        if (!$this->runTest()) {
            system(' git apply -R ' . $this->patch);
            return 2;
        }
        system(' git apply -R ' . $this->patch);
        return static::SUCCESS;
    }
}
