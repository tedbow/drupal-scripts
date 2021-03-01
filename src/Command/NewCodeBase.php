<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NewCodeBase extends CommandBase
{

    protected static $defaultName = 'newcode';

    protected const REQUIRE_CLEAN_GIT = false;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }
        system("rm -rf vendor && composer install");
        if ($this->isGitStatusClean()) {
            system('composer require drush/drush');
            system('git reset --h');
        }
        else {
            $this->style->warning('drush not installed because git is not clean.');
        }
        return self::SUCCESS;
    }

}