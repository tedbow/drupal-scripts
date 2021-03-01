<?php


namespace TedbowDrupalScripts\Command;


class NitChecker extends CheckerBase
{

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
        foreach ($diff_output as $diff_line) {
            if (strpos($diff_line, '+++ b/') === 0) {
                $current_file = str_replace('+++ b/', '', $diff_line);
            }
            if (strpos($diff_line, '+ ') === 0) {
                foreach ($error_patterns as $problem => $error_pattern) {
                    if (preg_match($error_pattern, $diff_line)) {
                        if ($last_error_file !== $current_file) {
                            $this->style->warning("File errors in $current_file");
                            $last_error_file = $current_file;
                        }
                        $this->style->warning("⚠️ $problem: $diff_line");
                        $found_error = TRUE;
                    }
                }
            }
        }
        if ($found_error) {
            return false;
        }
        return true;
    }

    /**
     * @return string[]
     */
    private function getErrorPatterns(): array
    {
        $error_patterns = [
          '🤦🏼‍♂️Debug left in' => '/Users\/ted\.bowman/',
          'Return hint needs Space' => '/function.*\):[^ ].* {/',
          'CamelCase argument' => '/function.*\(.*\$[^ ]*([A-Z])/',
          'Camelcase var' => '/^\s*\$[a-z]*([A-Z])/',
          'nonCamelCase prop' => '/(protected|public|private) \$[a-z]*_/',
          'camel case without scope' => '/[^(protected|public|private)] \$[a-z]*([A-Z])/',
          'no return type' => '/(protected|public|private) function .*(?<!__construct)\(.*\)[^\:]/',
          'id not cap' => '/ [iI]d([^a-z])/',
          'ids not cap' => '/ [iI]ds([^a-z])/',
          'yml space' => '/\[ /',
          'THROW' => '/' . preg_quote('@throws \Behat\Mink\Exception') . '/',
          'self assert' => '/' . preg_quote('self::assert') . '/',
          'return generic array' => '/' . preg_quote('* @return array') . '/',
          'return NULL cap' => '/@return .*\|NULL/',
          'constructor doc' => '/\* [A-z]* constructor\./',
          'verb tense' => '/\* (Get|Set|Create|Construct|Test that) /',
          'inheritdoc' => '/(inheritDoc|\* \@inheritdoc)/',
          'data provider is 2 words' => '/\* Dataprovider for/i',
          'the nonsense' => '/(^| |\.|,)(t|T)he (the|this|these|a|of|an)($| |\.)/',
            // add more nonsense here
          'nonsense' => '/(to as)/',
          'is_null call, use === NULL' => '/is_null\(/',
          '==, Always use === ' => '/ == /',
        ];
        return $error_patterns;
    }
}