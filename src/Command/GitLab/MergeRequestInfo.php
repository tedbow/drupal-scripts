<?php

namespace TedbowDrupalScripts\Command\GitLab;

use Symfony\Component\Console\Input\InputArgument;
use TedbowDrupalScripts\Command\CommandBase;
use TedbowDrupalScripts\Settings;

class MergeRequestInfo extends CommandBase
{
    protected static $defaultName = 'gitlab:mrinfo';
    protected static $requireAtRoot = false;
    protected const REQUIRE_CLEAN_GIT = false;

    protected function configure()
    {
        parent::configure();
        $this->setDescription('Get basic merge request info');
        $this->setAliases(['mr-info']);
        $this->addArgument('issue', InputArgument::REQUIRED, 'The issue id.');
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }

        $issue = $input->getArgument('issue');
        \assert(is_string($issue));

        try {
            $mrs = $this->getIssueMrs($issue);


            if ($this->outputJson($input)) {
                $output->write(json_encode($mrs, JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->style->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function getProjectId(string $issue): string
    {
        // First find the project associated with the issue.
        $project_response = $this->getGitLabApiData("https://git.drupalcode.org/api/v4/projects/?search=-$issue");
        if (empty($project_response)) {
            $this->style->error("No project found for issue $issue");
            return self::FAILURE;
        }
        \assert(is_array($project_response) && count($project_response) === 1, 'Expected exactly one project to be found.');
        \assert(isset($project_response[0]['id']), 'Expected project to have an ID.');
        return $project_response[0]['id'];
    }

    private function getProjectMrs(string $projectId)
    {
        $mr_response = $this->getGitLabApiData("https://git.drupalcode.org/api/v4/projects/project%2Fcanvas/merge_requests?state=all&source_project_id=$projectId");
        $important_keys = [
            'id',
            'iid',
            'title',
            'description',
            'draft',
            'state',
            'user_notes_count',
            'created_at',
            'merged_at',
            'closed_at',
            'merge_status',
            'detailed_merge_status',
            'author',
            'assignee',
            'web_url',
            'has_conflicts',
            'source_branch',
            'target_branch',
            'blocking_discussions_resolved',
            'created_at',
            'updated_at',
            'work_in_progress',
        ];

        $mrs = [];
        foreach ($mr_response as $mr) {
            $filtered_mr = array_intersect_key($mr, array_flip($important_keys));
            $filtered_mr['diff_web_url'] = $mr['web_url'] . '.diff';
            $mrs[] = $filtered_mr;
        }
        return $mrs;
    }

    public function getIssueMrs(string $issue): array
    {
        return $this->getProjectMrs($this->getProjectId($issue));
    }
}
