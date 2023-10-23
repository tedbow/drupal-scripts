<?php

namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GitFindPass extends GitBisectCommandBase {

  protected static $defaultName = 'git:pass';

  /**
   * @inheritDoc
   */
  protected function configure()
  {
    parent::configure();
    $this->setAliases(['pass']);
    $this->setDescription('Find last commit that passes a test.');
    $this->addOption('skip-cnt', null, InputOption::VALUE_OPTIONAL, 'How many commits should be skipped between checks', 50);
    $this->addOption('max', null, InputOption::VALUE_OPTIONAL, 'The maximum number of trys', 20);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $max_checks = $input->getOption('max');
    $skip_cnt = $input->getOption('skip-cnt');

    $parent = parent::execute($input,$output);
    if ($parent !== Command::SUCCESS) {
      return $parent;
    }
    while ($check_cnt < $max_checks) {
      $this->style->info("ℹ️check $check_cnt of $max_checks");
      $this->ensureGitClean($output);
      $cmd_output = [];
      if (!exec("git rev-parse --short HEAD~$skip_cnt", $cmd_output)) {
        print_r($output);
        exit(1);
      }
      $hash = array_pop($cmd_output);
      system("git checkout $hash");
      $this->ensureGitClean($output);
      $this->applyPatch($this->patch);
      $this->composerInstall();
      if ($this->runTest()) {
        $this->style->success('Found passing commit');
        system('git log --format=%B -n 1');
        return Command::SUCCESS;
      }
      $this->applyPatch($this->patch, TRUE);
      $check_cnt++;
    }
    $this->style->error("Could not find passing commit");
    return Command::FAILURE;
  }


}
