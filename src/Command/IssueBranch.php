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
        $this->addArgument('issue_number', InputArgument::OPTIONAL, 'The issue number');
        $this->addArgument('head', InputArgument::OPTIONAL, 'Which base to rebase against');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }
        $issueNumber = $input->getArgument('issue_number');
        if (!$issueNumber) {
            $sorted_branches = $this->shell_exec_split('git branch --sort=-committerdate');
            $issues = [];
            foreach ($sorted_branches as $sorted_branch) {
                $issue = $this->getBranchIssue($sorted_branch);
                if ($issue && !in_array($issue, $issues)) {
                    $issues[] = $issue;
                }
                if (count($issues) > 5) {
                    // Only display last 3 issues.
                    break;
                }
            }
            if (empty($issues)) {
                $this->style->warning('No issue branches found.');
                return self::FAILURE;
            }
            $issueIndex = 0;
            foreach ($issues as $issue) {
                $issue_titles[] = "#$issue: " . $this->getEntityInfo($issue)->title;

            }
            $title = $this->style->choice('Which issue do you want to work on?', $issue_titles);    
            $issueNumber = $issues[array_search($title, $issue_titles)];
        }

        $output->writeln("✍️ Title: " . $this->getEntityInfo($issueNumber)->title);
        $branches = $this->shell_exec_split("git branch --l \*$issueNumber\* --sort=-committerdate");
        $branches = array_map(function ($branch) {
            return trim(str_replace('* ', '', $branch));
        }, $branches);
        $current_branch = $this->getCurrentBranch();

        if ($branches) {
            if (array_search($current_branch, $branches) !== FALSE) {
                $this->style->warning("🚨 Currently on already on branch for this issue: $current_branch");
            }
            $list = $branches;
            $list['x'] = 'Do not switch. Exit';
            $branch = $this->style->choice('which branch to checkout? (sorted by most recent commit)', $list);
            if ($branch === 'x') {
                return self::SUCCESS;
            }
            $branch = $branches[$branch];
            $this->style->info('You have just selected: ' . $branch);
            shell_exec("git checkout $branch");
            if ($this->getMergeBase()) {

                $this->style->info("Probably Merge request. No rebase");
                return self::SUCCESS;
            }
            else {
                $current_head = $this->getCurrentHead($input);
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
                $list['x'] = 'Do not create a branch, going to use merge request instead';
                $current_head = $this->getCurrentHead($input);
                $choice = $this->style->choice("Create a new branch from patch against $current_head using patch?", $list);
                if ($choice === 'x') {
                    $this->style->info('Merge request, right on!');
                }
                system("new-branch.sh {$patches[$choice]->url} $current_head");
                return self::SUCCESS;
            }
            else {
                $this->style->warning("😱 No patches!");
                return self::FAILURE;
            }


        }
    }

    private function getCurrentHead(InputInterface $input)
    {
        $current_head = $input->getArgument('head');
        if ( !$current_head ) {
            $current_head = $this->getNodeBranch($input->getArgument('issue_number'));
        }
        return $current_head;
    }


}