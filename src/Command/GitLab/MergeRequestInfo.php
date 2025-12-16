<?php

namespace TedbowDrupalScripts\Command\GitLab;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use TedbowDrupalScripts\Command\CommandBase;
use TedbowDrupalScripts\Settings;
use TedbowDrupalScripts\Traits\GitLabTrait;

class MergeRequestInfo extends CommandBase
{
    use GitLabTrait;

    protected static $defaultName = 'gitlab:mrinfo';
    protected static $requireAtRoot = false;
    protected const REQUIRE_CLEAN_GIT = false;


    protected function configure()
    {
        parent::configure();
        $this->setDescription('Get basic merge request info');
        $this->setAliases(['mr-info']);
        $this->addArgument('issue', InputArgument::REQUIRED, 'The issue id.');
        $this->addOption('comments', null, InputOption::VALUE_OPTIONAL, 'Include comments in output', false);
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }

        $issue = $input->getArgument('issue');
        \assert(is_string($issue));

        $includeComments = (bool) $input->getOption('comments');
        $this->style->info('Getting merge request info for issue ' . $issue . " (include comments: " . ($includeComments ? 'yes' : 'no') . ')');
        try {
            $mrs = $this->getProjectMrs($this->getProjectId($issue), $includeComments);

            $output->write(json_encode($mrs, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->style->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
