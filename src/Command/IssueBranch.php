<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class IssueBranch extends CommandBase
{
    protected static $defaultName = 'issue:branch';

    protected function configure()
    {
        $this->addArgument('issue_number', InputArgument::REQUIRED, 'The issue number');
        $this->addArgument('head', InputArgument::OPTIONAL, 'Which base to rebase against');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }
        $issueNumber = $input->getArgument('issue_number');
        $current_head = $input->getArgument('head');
        if ( !$current_head ) {
            $current_head = $this->getNodeBranch($issueNumber);
        }



        $output->writeln("✍️ Title: " . $this->getEntityInfo($issueNumber)->title);
        $branches = $this->shell_exec_split("git branch --l \*$issueNumber\*");
        $branches = array_map(function ($branch) {
            return trim(str_replace('* ', '', $branch));
        }, $branches);
        $current_branch = $this->getCurrentBranch();

        if ($branches) {
            if (array_search($current_branch, $branches) !== FALSE) {
                $output->writeln("🚨 Currently on $current_branch");
            }

            $branch = $this->style->choice('which branch to checkout?', $branches);
            $this->style->info('You have just selected: ' . $branch);
            shell_exec("git checkout $branch");
            if ($this->getMergeBase()) {

                $this->style->info("Probably Merge request. No rebase");
                return self::SUCCESS;
            }
            else {
                if ($this->style->confirm("rebase against $current_head?", false)) {
                    system("git checkout $current_head");
                    system("git pull");
                    system("git checkout -");
                    system("git rebase $current_head");
                    return self::SUCCESS;
                }
            }

        }
        else {
            $this->style->warning("🚨 No existing branch for issue!");
            if ($patches = $this->getIssueFiles($issueNumber, '/\.patch/')) {

                $list = [];
                foreach ($patches as $patch) {
                    $list[] = $patch->name;
                }
                $choice = $this->style->choice("Create a new branch from patch against $current_head using patch?", $list);
                system("new-branch.sh {$patches[$choice]->url} $current_head");
                return self::SUCCESS;
            }
            else {
                $this->style->warning("😱 No patches!");
                return self::FAILURE;
            }


        }
    }


}