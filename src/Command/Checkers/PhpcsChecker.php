<?php

namespace TedbowDrupalScripts\Command\Checkers;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Checker that using phpcs Drupal standar with optional phpcbf fixing.
 *
 * "checker:core" also runs phpcs but this uses the stricter "Drupal" standard.
 */
class PhpcsChecker extends CheckerBase
{
    protected static $requireAtRoot = false;
    protected static $defaultName = "checker:phpcs";

  /**
   * @inheritDoc
   */
    protected function configure()
    {
        parent::configure();
        $this->setDescription('Runs PHPCS and optionally phpcbf');
    }

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
                $output = $this->shellExecSplit("./vendor/bin/phpcs --runtime-set installed_paths vendor/drupal/coder/coder_sniffer $getDiffFile --standard=Drupal");
                if ($output) {
                    $phpcs_error_files[] = $getDiffFile;
                    $phpcs_out = array_merge($phpcs_out, $output);
                }
            }
        }

        if ($phpcs_out) {
            print implode("\n", $phpcs_out);
            $choice = $this->style->confirm('PHPcs Fail ☹️. Fix with phpcfb?');
            switch ($choice) {
                case 'f':
                    foreach ($phpcs_error_files as $phpcs_error_file) {
                        system("./vendor/bin/phpcbf --runtime-set installed_paths vendor/drupal/coder/coder_sniffer $phpcs_error_file --standard=Drupal");
                    }
                    $this->style->warning("Ran phpcbf commit changes manually");
                    exit(1);
            }
        }
        return FALSE;
    }
}
