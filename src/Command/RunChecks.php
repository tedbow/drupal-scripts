<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunChecks extends CommandBase
{
    protected const REQUIRE_CLEAN_GIT = FALSE;

    protected static $defaultName = "run:checks";



    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (parent::execute($input, $output) === self::FAILURE) {
            return self::FAILURE;
        }
        //$this->phpcs->check($this->style);
        $diffPoint = $this->getDiffPoint();
        if (empty($diffPoint)) {
            $this->style->error('Cannot determine diff point');
        }
        $checkers = $this->getApplication()->all('checker');
        /** @var \TedbowDrupalScripts\Command\CheckerBase $checker */
        foreach ($checkers as $checker) {
            $this->style->info('Running ' . $checker->getName());

            if ($checker->execute($input, $output, $diffPoint) === self::FAILURE) {
                $this->style->error("Failed: " . $checker->getName());
                return self::FAILURE;
            }
            $this->style->info("Passed " . $checker->getName());
        }
        return self::SUCCESS;

    }


}