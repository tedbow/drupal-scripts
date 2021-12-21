<?php

namespace TedbowDrupalScripts\Command\Checkers;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CSpellChecker extends CheckerBase
{

    protected static $defaultName = "checker:cspell";

  /**
   * {@inheritdoc}
   *
   * Do not run be because CoreCheck covers this.
   */
    protected $defaultRun = false;

  /**
   * @inheritDoc
   */
    protected function configure()
    {
        parent::configure();
        $this->setDescription('Runs cspell');
    }


  /**
     * @inheritDoc
     */
    protected function doCheck(InputInterface $input, OutputInterface $output): bool
    {
        chdir('core');
        $gitDiffFiles = [];
        foreach ($this->getDiffFiles($this->diffPoint) as $getDiffFile) {
            $getDiffFile = str_replace('core/', '', $getDiffFile);
            $gitDiffFiles[] = $getDiffFile;
        }
        $result_code = null;
        $output = null;
        exec("yarn run cspell " . implode(' ', $gitDiffFiles), $output, $result_code);
        if ($result_code !== 0) {
            $this->style->error("☹️☹️☹️☹️☹️ Cspell Failed ☹️☹️☹️☹️☹️");

            $this->style->error("🔥" . implode("\n🔥", array_slice($output, 2, -1)));
            chdir('..');
            return false;
        }
        chdir('..');
        return true;
    }
}
