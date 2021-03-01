<?php


namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Input\InputOption;

/**
 * Common methods for running tests.
 */
trait RunTestsTrait
{

    protected function addTestRunOptions() {
        $this->addOption('skip', 's', InputOption::VALUE_REQUIRED, 'Tests to skip separated by commas.');
    }
}