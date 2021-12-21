<?php

namespace TedbowDrupalScripts\Command\Checkers;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PhpcsChecker extends CheckerBase
{
    protected static $requireAtRoot = false;
    protected static $defaultName = "checker:phpcs";

  /**
   * {@inheritdoc}
   *
   * Do not run be because CoreCheck covers this.
   */
    protected $defaultRun = false;

    /**
     * {@inheritdoc}
     */
    protected function doCheck(InputInterface $input, OutputInterface $output): bool
    {
        $exts = ['inc', 'install', 'module', 'php', 'profile', 'test', 'theme', 'yml'];
        $phpcs_out = [];
        $phpcs_error_files = [];
        $drupal_root = $this->getDrupalRoot();
        $this->style->info("root = $drupal_root");
        foreach ($this->getDiffFiles($this->diffPoint) as $getDiffFile) {
            if (in_array(pathinfo($getDiffFile)['extension'], $exts)) {
                $output = $this->shellExecSplit("composer run phpcs $getDiffFile");
                if ($output) {
                    $phpcs_error_files[] = $getDiffFile;
                    $phpcs_out = array_merge($phpcs_out, $output);
                }
            }
        }

        if ($phpcs_out) {
            print implode("\n", $phpcs_out);
            $choice = $this->style->choice(
                'PHPcs Fail ☹️. what to do?',
                [
                    'f' => 'Run phpcbf to fix',
                    'i' => 'Ignore',
                    'x' => 'Exit',
                ]
            );
            switch ($choice) {
                case 'f':
                    foreach ($phpcs_error_files as $phpcs_error_file) {
                        system("composer run phpcbf $phpcs_error_file");
                    }
                    $this->style->error("☹️☹️☹️☹️☹️ PHPCS Failed ☹️☹️☹️☹️☹️");
                    return false;
                case 'i':
                    $this->style->warning("💁🏼‍♂️Ignoring phpcs!\n");
                    return true;
                default:
                    return false;
            }
        }
        return true;
    }
}
