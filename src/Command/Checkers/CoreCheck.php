<?php

namespace TedbowDrupalScripts\Command\Checkers;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\UtilsTrait;

class CoreCheck extends CheckerBase
{

    use UtilsTrait;

    protected static $defaultName = "checker:core";

    protected static $requireAtRoot = false;
  /**
   * @inheritDoc
   */
    protected function doCheck(
        InputInterface $input,
        OutputInterface $output
    ): bool {
        $root = $this->getDrupalRoot();
        $result = null;
        $output = [];
        exec("$root/core/scripts/dev/commit-code-check.sh --branch " . $this->diffPoint, $output, $result);
        if ($result !== 0) {
            print implode("\n", $output);
            $phpcsFailed = false;
            foreach ($output as $line) {
                $matches = [];
                if (preg_match('/FOUND .* ERROR.* AFFECTING .* LINE/', $line, $matches) === 1) {
                    $phpcsFailed = true;

                    break;
                }
            }
            if ($phpcsFailed) {
              // We can attempt to fix.
              // @todo Attempt to fix.
            }
        }

        return $result === 0;
    }
}
