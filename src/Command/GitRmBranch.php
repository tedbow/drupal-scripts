<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to remove the current branch
 */
class GitRmBranch extends CommandBase
{
    protected static $requireAtRoot = false;

    protected static $defaultName = 'git:rm_branch';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }
        $branch = $this->getNodeBranch();
        $existing = $this->getCurrentBranch();
        if (!$branch || !$existing) {
            $this->style->error("Can't determine node or current branch.");
            return self::FAILURE;
        }
        // @todo Should I offer another branch?
        if ($this->style->confirm("Delete $existing branch and switch to $branch?", false)) {
            system("git checkout $branch");
            system(" git -c diff.mnemonicprefix=false -c core.quotepath=false branch -D $existing");

        }
        return self::SUCCESS;
    }


}