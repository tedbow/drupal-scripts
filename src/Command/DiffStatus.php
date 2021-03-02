<?php


namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to show diff file status since either MR was created or against patch
 * branch
 */
class DiffStatus extends CommandBase
{
    protected const REQUIRE_CLEAN_GIT = true;
    protected static $requireAtRoot = false;

    protected static $defaultName = 'git:name-status';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->addArgument('mode', InputArgument::OPTIONAL, 'full or name', 'name');
        $this->addArgument('file_pattern', InputArgument::OPTIONAL, 'The file/directory pattern to search for.', '');
    }




    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (parent::execute($input, $output) === self::FAILURE) {
            return self::FAILURE;
        }
        $diffPoint = $this->getDiffPoint();
        $mode = $input->getArgument('mode');
        if (!in_array($mode, ['full', 'name'])) {
            $this->style->error("First arg must be 'name' or 'full'");
        }
        $cmd = 'git diff '
          . ($mode === 'name' ? ' --name-status' : '')
          . " $diffPoint "
          . $input->getArgument('file_pattern');
        $status_output = shell_exec($cmd);
        $output->write($status_output);
        return self::SUCCESS;
    }


}