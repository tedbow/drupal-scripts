<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\FunStyle;
use TedbowDrupalScripts\Settings;

class CommandBase extends Command
{
    protected const REQUIRE_CLEAN_GIT = true;
    // Commands that will take a lot longer if xdebug is enabled should confirm.
    protected const CONFIRM_XDEBUG = false;

    protected static $requireAtRoot = TRUE;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->addOption('no-tests');
        $this->addOption('no-rebase');
    }


    /**
     * @var \Symfony\Component\Console\Style\SymfonyStyle
     */
    protected $style;

    /**
     * @inheritDoc
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->style = new FunStyle($input, $output);
        if (static::REQUIRE_CLEAN_GIT && !$this->isGitStatusClean($output)) {
            $this->style->error('git status must be clean to run this command.');
            return self::FAILURE;
        }
        if (self::$requireAtRoot && !$this->isAtRoot()) {
            $this->style->error("This command must be run at Drupal root");
            return self::FAILURE;
        }
        return parent::run($input, $output);
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->style = new FunStyle($input, $output);
        if (!$this->confirmXedbug()) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    protected function isGitStatusClean(?OutputInterface $output = NULL) {
        $status_output = shell_exec('git status');
        if (strpos($status_output, 'nothing to commit, working tree clean') === FALSE) {
            if ($output) {
                $output->write($status_output);
            }
            return FALSE;
        }
        return TRUE;
    }



    /**
     * Get the d.o issue for the current branch.
     *
     * @return string
     */
    protected function getBranchIssue(): string {
        $branch = $this->getCurrentBranch();
        $issue = explode('-', $branch)[0];
        if (!is_numeric($issue) || (int) $issue < 2000) {
            print "probably not issue number $issue\n";
            return '';
        }
        return $issue;
    }

    protected function getCurrentBranch() {
        return trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
    }


    /**
     * @param $issue
     *
     * @return mixed
     */
    protected function getEntityInfo($issue, $type = 'node'): \stdClass {
        static $infos = [];
        if (!isset($infos[$issue])) {
            $url = "https://www.drupal.org/api-d7/$type/$issue.json";
            $infos[$issue] = $this->getURLDecodedJson($url);
        }
        return $infos[$issue];
    }

    protected function getURLDecodedJson(string $url) {
        return json_decode(file_get_contents($url));
    }

    protected function getTimeFromTimeStamp($timestamp) {
        $dt = new \DateTime("now", new \DateTimeZone(Settings::getSetting('timezone'))); //first argument "must" be a string
        $dt->setTimestamp($timestamp); //adjust the object to correct timestamp
        //echo $dt->format('d.m.Y, H:i:s');
        return $dt->format('d.m.Y, H:i:s');
    }

    protected function getIssueStatus($status_code) {
        $statuses = [
          '1' => 'active',
          '2' => 'fixed',
          '3' => 'closed (duplicate)',
          '4' => 'postponed',
          '5' => 'closed (won\'t fix)',
          '6' => 'closed (works as designed)',
          '7' => 'closed (fixed)',
          '8' => 'needs review',
          '13' => 'needs work',
          '14' => 'reviewed & tested by the community',
          '15' => 'patch (to be ported)',
          '16' => 'postponed (maintainer needs more info)',
          '17' => 'closed (outdated)',
          '18' => 'closed (cannot reproduce)',
        ];
        return $statuses[$status_code];
    }

    /**
     * Gets the branch an issue is against.
     * @param null|string $issue
     *
     * @return string
     *   The branch the issue is against.
     */
    protected  function getNodeBranch($issue = NULL) {
        if (empty($issue)) {
            $issue = $this->getBranchIssue();
        }
        if (empty($issue)) {
            return '';
        }
        $version = $this->getEntityInfo($issue)->field_issue_version;
        if (strpos($version, '-dev') !== FALSE ) {
            return str_replace('-dev', '', $version);
        }
        return '';

    }

    /**
     * Run exec and split into lines.
     * @param $string
     *
     * @return string[]
     */
    protected function shell_exec_split($string) {
        $output = shell_exec($string);
        $output = preg_split('/\n+/', trim($output));
        $output = array_map(function ($line) {
            return trim($line);
        }, $output);

        return array_filter($output);

    }

    protected function getMergeBase():?string {
        static $mergeBase = false;
        if ($mergeBase === false) {
            $current_branch = $this->getCurrentBranch();
            $issue_branch = $this->getNodeBranch();
            if (!($current_branch && $issue_branch)) {
                throw new \Exception("current branch or issue not found");
            }
            $commit = trim(shell_exec("git merge-base $issue_branch $current_branch"));
            $mergeBase = $commit ?? NULL;
        }
        return $mergeBase;
    }

    protected function getDiffPoint(): ?string {
        static $diffPoint = false;
        if ($diffPoint === false) {
            $mergeBase = $this->getMergeBase();
            if ($mergeBase) {
                $diffPoint = $mergeBase;
            }
            else {
                $diffPoint = $this->getNodeBranch();
            }
        }
        return $diffPoint;
    }

    /**
     * @param $issue
     */
    protected function getIssueFiles($issue, $pattern): array {
        $node_info = $this->getEntityInfo($issue);
        if (empty($node_info->field_issue_files)) {
            return [];
        }
        else {
            $files = [];
            foreach ($node_info->field_issue_files as $file_info) {

                if ($file_info->display) {
                    $file = $this->getURLDecodedJson($file_info->file->uri . '.json');
                    if (preg_match($pattern, $file->name)) {
                        $files[] = $file;
                    }
                }

            }
            return $files;
        }
    }

    /**
     * @return bool
     */
    private function isAtRoot()
    {
        foreach (['index.php', 'update.php'] as $file) {
            if (!file_exists($file)) {
                $this->style->error("Missing file: $file");
                return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * @param string $diffPoint
     *
     * @return string[]
     */
    protected function getDiffFiles(string $diffPoint): array
    {
        return $this->shell_exec_split("git diff $diffPoint --name-only");

    }

    protected function confirmXedbug(): bool {
        /** @var \TedbowDrupalScripts\ScriptApplication $app */
        $app = $this->getApplication();
        // If running calling other commands only run this check once.
        if (static::CONFIRM_XDEBUG && !$app->isXdebugConfirmed() && ini_get('xdebug.default_enable')) {
            $app->setXdebugConfirmed();
            return $this->style->confirm("️Xdebug is on, tests & composer will take longer! Continue?", false);
        }
        return true;
    }

}