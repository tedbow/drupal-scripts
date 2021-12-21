<?php


namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunChecks extends CommandBase
{
    use RunTestsTrait;
    protected const REQUIRE_CLEAN_GIT = false;
    protected const CONFIRM_XDEBUG = true;

    protected static $defaultName = 'run:checks';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->addTestRunOptions();
        $this->setDescription("Runs all checkers");
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (parent::execute($input, $output) === self::FAILURE) {
            return self::FAILURE;
        }
        if ($skipTests = $input->getOption('skip')) {
            $skipTests = explode(',', $skipTests);
            $this->style->warning('skipping tests=' . implode(', ', $skipTests));
        }
        $diffPoint = $this->getDiffPoint();
        if (empty($diffPoint)) {
            $this->style->error('Cannot determine diff point');
        }
        $checkers = $this->getApplication()->all('checker');
        /** @var \TedbowDrupalScripts\Command\Checkers\CheckerBase $checker */
        foreach ($checkers as $checker) {
            if (!$checker->isDefaultRun()) {
                continue;
            }
            $checkerName = $checker->getName();
            $shortName = explode(':', $checkerName)[1];
            if ($skipTests && in_array($shortName, $skipTests)) {
                $this->style->warning("Skipping " . $checkerName);
                continue;
            }
            $this->style->info('Running ' . $checkerName);
            if ($checker->execute($input, $output, $diffPoint) === self::FAILURE) {
                $this->style->error("Failed: " . $checkerName);
                if ($this->style->choice('Ignore Failure?', ['no' => 'no', 'yes' => 'yes'], 'no') === 'no') {
                    return self::FAILURE;
                }
                $this->style->warning("️Ignored " . $checkerName);
            } else {
                $this->style->info("Passed " . $checkerName);
            }
        }
        return self::SUCCESS;
    }
}
