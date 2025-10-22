<?php

namespace TedbowDrupalScripts\Command\GitLab;

use Symfony\Component\Console\Input\InputArgument;
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
}
