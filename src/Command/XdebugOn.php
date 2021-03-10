<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class XdebugOn extends CommandBase
{
    protected const REQUIRE_CLEAN_GIT = false;
    // Commands that will take a lot longer if xdebug is enabled should confirm.
    protected const CONFIRM_XDEBUG = false;

    protected static $requireAtRoot = FALSE;
    protected static $defaultName = 'xdebug';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setAliases(['x']);
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $this->style->info(
          ini_get('xdebug.default_enable') ?
            "Xdebug is on." :
            "Xdebug is NOT on."
        );
        return self::SUCCESS;
    }


}