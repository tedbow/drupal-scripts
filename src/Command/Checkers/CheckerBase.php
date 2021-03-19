<?php


namespace TedbowDrupalScripts\Command\Checkers;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\Command\CommandBase;

abstract class CheckerBase extends CommandBase
{

    protected const REQUIRE_CLEAN_GIT = FALSE;

    /**
     * @var string|null
     */
    protected $diffPoint;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->addOption('diff', NULL, InputOption::VALUE_REQUIRED);
    }


    protected function execute(InputInterface $input, OutputInterface $output, ?string $diffPoint = NULL) {
        if (parent::execute($input, $output) === self::FAILURE) {
            return self::FAILURE;
        }
        $calledDirect = empty($diffPoint);
        if (!$diffPoint) {
            if (!$diffPoint = $input->getOption('diff')) {
                $diffPoint = $this->getDiffPoint();
            }

        }
        $this->diffPoint = $diffPoint;
        if (!$this->doCheck($input, $output)) {
            return self::FAILURE;
        }
        if ($calledDirect) {
            $this->style->note("Checker passed: " . $this->getName());
        }
        return self::SUCCESS;
    }

    /**
     * @return bool
     */
    abstract protected function doCheck(InputInterface $input, OutputInterface $output): bool;
}