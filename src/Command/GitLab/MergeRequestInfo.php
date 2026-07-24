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
        $this->addArgument('issue', InputArgument::REQUIRED, 'The issue number or issue URL (drupal.org or git.drupalcode.org)');
        $this->addOption('comments', null, InputOption::VALUE_OPTIONAL, 'Include comments in output', false);
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }

        $ref = $this->resolveIssueRef($input->getArgument('issue'));
        if (!$ref) {
            return self::FAILURE;
        }
        if ($ref->number === null) {
            $this->style->error('This command needs an issue number or issue URL, not an MR URL.');
            return self::FAILURE;
        }
        $issue = $ref->number;

        $includeComments = (bool) $input->getOption('comments');
        $this->style->info('Getting merge request info for issue ' . $issue . " (include comments: " . ($includeComments ? 'yes' : 'no') . ')');
        try {
            $projectInfo = $this->getProjectInfo($issue, $ref->projectPath ? basename($ref->projectPath) : null);
            if (!$projectInfo) {
                $this->style->error("No project found for issue $issue");
                return self::FAILURE;
            }
            $mrs = $this->getProjectMrs((string) $projectInfo['id'], $includeComments, $this->getCanonicalProjectPath($projectInfo, $ref));

            $output->write(json_encode($mrs, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->style->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
