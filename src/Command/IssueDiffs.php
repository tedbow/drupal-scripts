<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IssueDiffs extends CommandBase
{

    protected static $defaultName = 'issue:diffs';

    protected function configure()
    {
        parent::configure();
        $this->setAliases(['diffs']);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }
        $issueNumber = $this->getBranchIssue();
        $this->style->info('Last commit');
        $this->style->info(static::shell_exec_split('git log --pretty=format:"%s - %aI" --max-count=1')[0]);

        $diffs = $this->getIssueFiles($issueNumber, '/interdiff/');
        $diffNames = [];
        foreach ($diffs as $diff) {
            $diffNames[] = $diff->name;
        }
        $choice = $this->style->choice('Apply diffs from?', $diffNames);

        foreach ($diffs as $i => $diff) {
            if ($i < $choice) {
                continue;
            }
            $diffContents = file_get_contents($diff->url);
            $temp = tmpfile();
            fwrite($temp, $diffContents);
            $tmpfile_path = stream_get_meta_data($temp)['uri'];
            static::shell_exec_split("git apply $tmpfile_path ");
            if ($this->isGitStatusClean()) {
                print "🔥 Didn't apply\n";
                exit(1);
            }
            system('git add .');
            system('git status');
            system("git commit -am '➕ {$diff->url}'");

        }



        return self::SUCCESS;
    }

}