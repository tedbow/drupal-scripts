<?php


namespace TedbowDrupalScripts\Command;


class PHPUnitChecker extends CheckerBase
{

    protected static $defaultName = "checker:phpunit";
    /**
     * @inheritDoc
     */
    protected function doCheck(): bool
    {
        // Add option to base for this.
        /*global $global_options;
        if (isset($global_options['no-tests'])) {
            print "⚠️no tests\n";
            return TRUE;
        }*/
        $files = $this->getDiffFiles($this->diffPoint);

        // Only run unit for now
        $modules_to_run = [];
        $all_pass = TRUE;
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
                $output = shell_exec("vendor/bin/phpunit --configuration core $file");
                print $output;
                if (strpos($output, 'Errors') !== FALSE || strpos($output, 'FAILURES!') !== FALSE) {
                    $all_pass = FALSE;
                }
            }
        }

        if ($modules_to_run) {
            foreach ($modules_to_run as $module) {
                $unit_dir = "core/modules/$module/tests/src/Unit";
                if (file_exists($unit_dir)) {
                    $output = shell_exec("vendor/bin/phpunit --configuration core $unit_dir");
                    if ($module !== 'system') {
                        //$output .= shell_exec("vendor/bin/phpunit --configuration core core/modules/$module/tests/src/Kernel");
                    }
                    $this->style->write($output);
                    if (strpos($output, 'Errors:') !== FALSE) {
                        $all_pass = FALSE;
                    }
                }
            }
        }
        return $all_pass;
    }
}