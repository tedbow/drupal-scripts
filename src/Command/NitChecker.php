<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Yaml\Yaml;
use TedbowDrupalScripts\UtilsTrait;

class NitChecker extends CheckerBase
{
    use UtilsTrait;

    protected static $defaultName = "checker:nits";
    /**
     * @inheritDoc
     */
    protected function doCheck(): bool
    {
        $diff_command = "git diff " . $this->diffPoint;
        $diff_output = $this->shell_exec_split($diff_command);
        $current_file = '';
        $last_error_file = '';
        $error_patterns = $this->getErrorPatterns();
        $found_error = FALSE;
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
                        $found_error = TRUE;
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
    private function getErrorPatterns(): array
    {
        $error_patterns = static::parseYml('error_patterns');
        return $error_patterns;
    }
}