<?php

namespace TedbowDrupalScripts\Command\Checkers;



use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class PHPUnitChecker extends CheckerBase
{

    protected const CONFIRM_XDEBUG = true;
    protected static $defaultName = "checker:phpunit";

    /**
     * @var \Symfony\Component\Process\Process
     */
    protected $seleniumServerProcess = null;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->addArgument('paths', InputArgument::IS_ARRAY, 'Paths to test',[]);
    }


    /**
     * @inheritDoc
     */
    protected function doCheck(InputInterface $input, OutputInterface $output): bool
    {
        $output_directory = getenv('BROWSERTEST_OUTPUT_DIRECTORY');
        putenv('BROWSERTEST_OUTPUT_DIRECTORY');
        // Add option to base for this.
        /*global $global_options;
        if (isset($global_options['no-tests'])) {
            print "⚠️no tests\n";
            return TRUE;
        }*/


        $paths = $input->getArgument('paths');
        if (empty($paths)) {
            $paths = $this->getTestPathsForDiff();
        }
        $all_pass = true;
        foreach ($paths as $path) {
            if (!$this->runTestForPath($path)) {
                $all_pass = FALSE;
            }
        }

        if ($this->seleniumServerProcess) {
            $this->style->info('closing');
            $this->seleniumServerProcess->stop(1);
            $this->style->info('closed');
        }
        putenv("BROWSERTEST_OUTPUT_DIRECTORY=$output_directory");
        return $all_pass;
    }

    /**
     * Run tests for a path.
     *
     * @param string $testPath
     *   Path to test file or directory.
     *
     * @return bool
     *   Whether there were any failed or skipped tests.
     */
    protected function runTestForPath(string $testPath): bool
    {
        if (strpos($testPath, 'FunctionalJavascript') !== false) {
            $this->startSelenium();
        }
        $output = shell_exec("vendor/bin/phpunit --configuration core $testPath");
        $this->style->write($output);
        return !(
          strpos($output, 'Errors') !== FALSE
          || strpos($output, 'FAILURES!') !== FALSE
          || strpos($output, 'OK, but incomplete, skipped, or risky tests!') !== false
        );
    }

    /**
     * Get the paths to run for the current diff.
     *
     * @return array
     */
    private function getTestPathsForDiff(): array
    {
        // Only run unit for now
        $modules_to_run = [];
        $paths = [];
        $files = $this->getDiffFiles($this->diffPoint);
        foreach ($files as $file) {
            if (strpos($file, 'core/modules/') === 0) {
                $parts = explode('/', $file);
                // Make a list modules to run all unit tests for any modules changed.
                $module = $parts[2];
                if (!in_array($module, $modules_to_run)) {
                    $modules_to_run[] = $module;
                }
            }
            if ((strpos($file, '/tests/src') !== FALSE || strpos($file, 'core/tests/Drupal') !== FALSE)
              && strpos($file, 'Test.php') !== FALSE
              && strpos($file, '/Unit') === FALSE) {
                // Run any non-unit tests that are different
                $paths[] = $file;
            }
        }

        if ($modules_to_run) {
            foreach ($modules_to_run as $module) {
                $unit_dir = "core/modules/$module/tests/src/Unit";
                if (file_exists($unit_dir)) {
                    $paths[] = $unit_dir;
                }
            }
        }
        return $paths;
    }

    /**
     * Start selenium-server for JavaScript tests if not started.
     */
    private function startSelenium()
    {
        if ($this->seleniumServerProcess === null) {
            $this->seleniumServerProcess = new Process(['selenium-server', '-port', '4444']);
            $this->seleniumServerProcess->start();
            $this->style->info('started selenium');
        }
    }
}