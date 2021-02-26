<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PhpcsChecker extends CheckerBase
{
    protected static $defaultName = "checker:phpcs";

    /**
     * {@inheritdoc}
     */
    protected function doCheck(): bool
    {
        $exts = ['inc', 'install', 'module', 'php', 'profile', 'test', 'theme', 'yml'];
        $phpcs_out = [];
        $phpcs_error_files = [];
        foreach ($this->getDiffFiles($this->diffPoint) as $getDiffFile) {
            if (in_array(pathinfo($getDiffFile)['extension'], $exts)) {
                $output = $this->shell_exec_split("./vendor/bin/phpcs $getDiffFile --standard=core/phpcs.xml.dist");
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
                        system("./vendor/bin/phpcbf $phpcs_error_file --standard=core/phpcs.xml.dist");
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