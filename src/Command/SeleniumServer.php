<?php


namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\Command\CommandBase;

class SeleniumServer extends CommandBase implements SignalableCommandInterface
{
    use SeleniumTrait;
    protected static $defaultName = "sel-server";

    protected const REQUIRE_CLEAN_GIT = false;
    // Commands that will take a lot longer if xdebug is enabled should confirm.
    protected const CONFIRM_XDEBUG = false;

    protected static $requireAtRoot = false;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setAliases(['sel']);
        $this->setDescription('Starts the SeleniumServer for JS tests');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->startSelenium();
        while (1) {
        }
        return parent::execute(
            $input,
            $output
        ); // TODO: Change the autogenerated stub
    }


    public function getSubscribedSignals(): array
    {
        // return here any of the constants defined by PCNTL extension
        // https://www.php.net/manual/en/pcntl.constants.php

        return [SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal): void
    {
        if (SIGINT === $signal) {
            $this->stopSelenium();
            $this->style->info('int');
            exit();
        }

        // ...
    }
}
