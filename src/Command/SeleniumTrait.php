<?php


namespace TedbowDrupalScripts\Command;

use Symfony\Component\Process\Process;

trait SeleniumTrait
{


    /**
     * @var \Symfony\Component\Process\Process
     */
    protected $seleniumServerProcess = null;

    /**
     * Start selenium-server for JavaScript tests if not started.
     */
    protected function startSelenium()
    {
        if ($this->seleniumServerProcess === null) {
            $this->seleniumServerProcess = new Process(['selenium-server', '-port', '4444']);
            $this->seleniumServerProcess->start();
            $this->style->info('started selenium');
        }
    }

    protected function stopSelenium(): void
    {
        if ($this->seleniumServerProcess) {
            $this->style->info('closing');
            $this->seleniumServerProcess->stop(1);
            $this->style->info('closed');
        }
    }
}
