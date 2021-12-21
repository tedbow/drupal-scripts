<?php

namespace TedbowDrupalScripts\Command\Checkers;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\Command\SeleniumTrait;

/**
 * Run phpunit tests.
 */
class PHPUnitChecker extends CheckerBase
{
    use SeleniumTrait;

    protected const CONFIRM_XDEBUG = true;
    protected static $defaultName = "checker:phpunit";


    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setAliases(['phpunit']);
        $this->addOption('paths', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Paths to test');
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


        if ($input->hasOption('paths')) {
            $paths = $input->getOption('paths');
        }
        if (empty($paths)) {
            $paths = $this->getTestPathsForDiff();
        }
        $all_pass = true;
        $this->style->info("running test for: " . implode("\n", $paths));
        foreach ($paths as $path) {
            if (!$this->runTestForPath($path)) {
                $all_pass = false;
            }
        }

        $this->stopSelenium();
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
          strpos($output, 'Errors') !== false
          || strpos($output, 'FAILURES!') !== false
          || strpos($output, 'OK, but incomplete, skipped, or risky tests!') !== false
        );
    }

    /**
     * Get the paths to run for the current diff.
     *
     * Tests that will be run.
     *
     * 1. Unit test for any module that is changed.
     * 2. Any test that is directly changed.
     *
     * @todo Run any test where @coversDefaultClass class has changed and test
     *   is not in the existing list.
     *
     * @return string[]
     *   The test paths that will be run.
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
            if ((strpos($file, '/tests/src') !== false || strpos($file, 'core/tests/Drupal') !== false)
              && strpos($file, 'Test.php') !== false
              && strpos($file, '/Unit') === false) {
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
}
