<?php


namespace TedbowDrupalScripts\Command\Checkers;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\Command\CommandBase;

abstract class CheckerBase extends CommandBase
{

    protected const REQUIRE_CLEAN_GIT = false;

    /**
     * @var string|null
     */
    protected $diffPoint;

  /**
   * Whether to run in the default group.
   *
   * @var bool
   */
    protected $defaultRun = true;

  /**
   * Determines if the checker should be run in the default group.
   *
   * @return bool
   */
    public function isDefaultRun(): bool
    {
        return $this->defaultRun;
    }

  /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->addOption('diff', null, InputOption::VALUE_REQUIRED);
    }


    protected function execute(InputInterface $input, OutputInterface $output, ?string $diffPoint = null)
    {
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
            if ($calledDirect) {
                $this->style->error("Checker failed: " . $this->getName());
            }
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
