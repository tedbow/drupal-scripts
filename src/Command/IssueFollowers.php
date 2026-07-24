<?php


namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\IssueRef;

class IssueFollowers extends CommandBase
{

    protected static $defaultName = "issue:followers";
    protected static $requireAtRoot = false;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setAliases(['fw']);
        $this->setDescription('Show issue followers');
        $this->addArgument('issue_number', InputArgument::OPTIONAL, 'The issue number');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }
        $ref = $this->resolveIssueRef($input->getArgument('issue_number'));
        if (!$ref) {
            $this->style->warning("could not find issue");
            return static::FAILURE;
        }
        if ($ref->source === IssueRef::SOURCE_GITLAB) {
            $this->style->error('This command uses Drupal.org issue data and does not support GitLab issues.');
            return static::FAILURE;
        }
        $issueNumber = $ref->number;
        $info = $this->getEntityInfo($issueNumber);
        $followerNames = [];
        foreach ($info->flag_project_issue_follow_user as $follower) {
            $user = $this->getEntityInfo($follower->id, 'user');
            $followerNames[] = $user->name;
        }
        $this->style->info("followers: " . implode(', ', $followerNames));
        return self::SUCCESS;
    }
}
