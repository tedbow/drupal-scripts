<?php

namespace TedbowDrupalScripts\Command\GitLab;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\Command\CommandBase;
use TedbowDrupalScripts\Traits\GitLabTrait;

class IssueCheckout extends CommandBase
{
    use GitLabTrait;

    protected static $defaultName = 'gitlab:issue-checkout';
    protected static $requireAtRoot = false;

    protected function configure()
    {
        parent::configure();
        $this->setDescription('Checkout an open merge request branch for a Drupal.org issue');
        $this->setAliases(['issue-mr-checkout', 'mr-checkout']);
        $this->addArgument('issue', InputArgument::REQUIRED, 'The issue number');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }

        $issue = $input->getArgument('issue');
        \assert(is_string($issue));

        $this->style->info('Finding open merge requests for issue ' . $issue);

        try {
            // Get project information
            $projectInfo = $this->getProjectInfo($issue);
            if (!$projectInfo) {
                $this->style->error("No project found for issue $issue");
                return self::FAILURE;
            }

            $projectId = $projectInfo['id'];
            $projectPath = $projectInfo['path_with_namespace'];

            // Get all MRs for the project
            $allMrs = $this->getProjectMrs($projectId, false);

            // Filter for only open MRs
            $openMrs = array_filter($allMrs, function ($mr) {
                return $mr['state'] === 'opened';
            });

            if (empty($openMrs)) {
                $this->style->warning("No open merge requests found for issue $issue");
                return self::FAILURE;
            }

            // If multiple open MRs, let user choose
            $selectedMr = null;
            if (count($openMrs) > 1) {
                $choices = [];
                foreach ($openMrs as $mr) {
                    $draftLabel = $mr['draft'] ? '[DRAFT] ' : '';
                    $choices[] = $draftLabel . $mr['title'];
                }

                $selectedTitle = $this->style->choice(
                    'Multiple open merge requests found. Please select one:',
                    $choices
                );

                $selectedIndex = array_search($selectedTitle, $choices);
                $selectedMr = array_values($openMrs)[$selectedIndex];
            } else {
                $selectedMr = reset($openMrs);
            }

            $branchName = $selectedMr['source_branch'];
            $this->style->info("Selected MR: {$selectedMr['title']}");
            $this->style->info("Branch: $branchName");

            // Construct the remote name and URL following Drupal.org pattern
            // The project path is already "issue/canvas-3562879" format
            // Remote name: basename of path (e.g., "canvas-3562879")
            // Remote URL: git@git.drupal.org:issue/canvas-3562879.git
            $remoteName = basename($projectPath);
            $remoteUrl = "git@git.drupal.org:$projectPath.git";

            // Check if remote exists, add if not
            $this->addRemoteIfNeeded($remoteName, $remoteUrl);

            // Fetch from the remote
            $this->style->info("Fetching from remote $remoteName...");
            $fetchCommand = "git fetch $remoteName 2>&1";
            $fetchOutput = shell_exec($fetchCommand);

            // Check if fetch was successful
            if ($fetchOutput && (strpos($fetchOutput, 'fatal:') !== false || strpos($fetchOutput, 'error:') !== false)) {
                $output->writeln($fetchOutput);
                $this->style->error("Failed to fetch from remote. Please check your SSH access to git.drupal.org");
                return self::FAILURE;
            }

            if ($fetchOutput) {
                $output->writeln($fetchOutput);
            }

            // Check if branch exists locally
            $localBranchCheck = shell_exec("git branch -l '$branchName' 2>&1");
            $branchExistsLocally = !empty(trim($localBranchCheck ?? ''));

            if ($branchExistsLocally) {
                // Branch exists locally, just check it out
                $this->style->info("Branch exists locally, checking it out...");
                $checkoutCommand = "git checkout '$branchName'";
                $output->writeln("<comment>Running: $checkoutCommand</comment>");
                system($checkoutCommand, $checkoutResult);

                if ($checkoutResult === 0) {
                    $this->style->success("Successfully checked out branch: $branchName");
                } else {
                    $this->style->error("Failed to checkout branch");
                    return self::FAILURE;
                }
            } else {
                // Branch doesn't exist locally, create it tracking the remote branch
                $this->style->info("Creating local branch tracking remote...");
                $checkoutCommand = "git checkout -b '$branchName' --track $remoteName/'$branchName'";
                $output->writeln("<comment>Running: $checkoutCommand</comment>");
                system($checkoutCommand, $checkoutResult);

                if ($checkoutResult === 0) {
                    $this->style->success("Successfully created and checked out branch: $branchName");
                } else {
                    $this->style->error("Failed to create and checkout branch");
                    return self::FAILURE;
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->style->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Get project information including path.
     */
    protected function getProjectInfo(string $issue): ?array
    {
        $project_response = $this->getGitLabApiData("https://git.drupalcode.org/api/v4/projects/?search=-$issue");
        if (empty($project_response)) {
            return null;
        }
        \assert(is_array($project_response) && count($project_response) === 1, 'Expected exactly one project to be found.');
        return $project_response[0];
    }

    /**
     * Add git remote if it doesn't already exist.
     */
    protected function addRemoteIfNeeded(string $remoteName, string $remoteUrl): void
    {
        // Check if remote exists
        $remoteCheck = shell_exec("git remote get-url $remoteName 2>&1");
        $remoteExists = !empty(trim($remoteCheck)) && strpos($remoteCheck, 'error') === false && strpos($remoteCheck, 'fatal') === false;

        if ($remoteExists) {
            $this->style->info("Remote $remoteName already exists");
        } else {
            $this->style->info("Adding remote $remoteName...");
            $addRemoteCommand = "git remote add $remoteName $remoteUrl";
            shell_exec($addRemoteCommand);
            $this->style->success("Added remote: $remoteName -> $remoteUrl");
        }
    }
}
