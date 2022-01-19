<?php

namespace TedbowDrupalScripts\Command\Checkers;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\UtilsTrait;

class NitChecker extends CheckerBase
{
    use UtilsTrait;

    protected static $defaultName = "checker:nits";

    protected static $requireAtRoot = false;

  /**
   * @inheritDoc
   */
    protected function configure()
    {
        parent::configure();
        $this->setDescription('Checks for common nits defined in error_patterns.yml');
        $this->addArgument('pattern', InputArgument::OPTIONAL, 'Limit to a single pattern to check');
    }


  /**
     * @inheritDoc
     */
    protected function doCheck(InputInterface $input, OutputInterface $output): bool
    {
        $diff_command = "git diff " . $this->diffPoint;
        $diff_output = $this->shellExecSplit($diff_command);
        $current_file = '';
        $last_error_file = '';
        $error_patterns = $this->getErrorPatterns($input);
        $found_error = false;
        $warnings = [];
        foreach ($diff_output as $diff_line) {
            if (strpos($diff_line, '+++ b/') === 0) {
                $current_file = str_replace('+++ b/', '', $diff_line);
            }
            if (strpos($diff_line, '+ ') === 0) {
                foreach ($error_patterns as $problem => $error_pattern) {
                    if (preg_match($error_pattern, $diff_line)) {
                        if ($last_error_file !== $current_file) {
                            $warnings[$current_file] = "File errors in $current_file";
                            $last_error_file = $current_file;
                        }
                        $warnings[$current_file] .= "\n⚠️ $problem: $diff_line";
                        $found_error = true;
                    }
                }
            }
        }
        if ($found_error) {
            foreach ($warnings as $warning) {
                $this->style->warning($warning);
            }
            return false;
        }
        return true;
    }

    /**
     * @return string[]
     */
    private function getErrorPatterns(InputInterface $input): array
    {
        $error_patterns = static::parseYml('error_patterns');
        if ($input->hasArgument('pattern') && $pattern = $input->getArgument('pattern')) {
          if (!isset($error_patterns[$pattern])) {
            throw new \RuntimeException("Unknown pattern: $pattern");
          }
          $error_patterns = [
            $pattern => $error_patterns[$pattern],
          ];

        }
        return $error_patterns;
    }
}
