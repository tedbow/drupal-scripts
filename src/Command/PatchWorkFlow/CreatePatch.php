<?php


namespace TedbowDrupalScripts\Command\PatchWorkFlow;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\Command\CommandBase;

class CreatePatch extends CommandBase
{

    use RunTestsTrait;

    protected static $defaultName = 'patch:create';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setAliases(['patch']);
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Force option to pass on to git push command.');
        $this->addArgument('pass_on', InputArgument::IS_ARRAY, 'Arguments to pass on to git push command.');
        $this->addTestRunOptions();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (parent::execute($input, $output) === self::FAILURE) {
            return self::FAILURE;
        }
        $runChecks = $this->getApplication()->get('run:checks');
        $input->setOption('skip', $input->getOption('skip'));
        if ($runChecks->execute($input, $output) === self::FAILURE) {
            return self::FAILURE;
        }

        $issue = $this->getBranchIssue();
        $base = $this->getNodeBranch();
        $node_info = $this->getEntityInfo($issue);
        $comment_number = ((int) $node_info->comment_count) + 1;
        $patch_name = "$issue-$comment_number.patch";
        $this->style->info("✂️ Creating patch $patch_name\n\n");
        // shell_exec("git diff $current_head -C35 > /Users/ted.bowman/sites/$patch_name");
        $this->style->warning("command " . "git diff $base > /Users/ted.bowman/sites/$patch_name");
        static::shellExecSplit("git diff $base > /Users/ted.bowman/sites/$patch_name");

        $display_lines = static::shellExecSplit('git log --pretty=format:"%s - %aI" --max-count=15');
        $log_lines = static::shellExecSplit('git log --pretty=format:"%H" --max-count=15');
        array_shift($log_lines);
        array_shift($display_lines);
        // Look if last commit is from actual core
        if (strpos($display_lines[0], 'Issue #') !== false) {
            print "⚠️No previous commits, no interdiff\n";
        } else {
            print "Which commit for interdiff?\n\n";
            print_r($display_lines);

            $line_number = readline("X to exit:");
            if ($line_number === 'x') {
                exit();
            }

            $from_comment = (int) readline("from comment #?");
            $line_number = (int) $line_number;
            $line = $log_lines[$line_number];
            $parts = explode(' ', $line);
            shell_exec("git diff {$parts[0]} > /Users/ted.bowman/sites/interdiff-$from_comment-$comment_number.txt");
        }

        return self::SUCCESS;
    }
}
