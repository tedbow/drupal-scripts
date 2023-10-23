<?php


namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use TedbowDrupalScripts\FunStyle;
use TedbowDrupalScripts\Settings;

class CommandBase extends Command
{
    protected const REQUIRE_CLEAN_GIT = true;
    // Commands that will take a lot longer if xdebug is enabled should confirm.
    protected const CONFIRM_XDEBUG = false;

    protected static $requireAtRoot = true;

    private static function getIssueInString(?string $branch): ?string
    {
        if ($branch) {
            $parts = explode('-', $branch);
            foreach ($parts as $part) {
                if (is_numeric($part)) {
                    // always use first numeric
                    if ((int) $part < 2000) {
                        return null;
                    }
                    return $part;
                }
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->addOption('no-tests');
        $this->addOption('no-rebase');
        $this->addOption('no-catch');
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
        if (static::$requireAtRoot && !$this->isAtRoot()) {
            $this->style->error("This command must be run at Drupal root");
            return self::FAILURE;
        }

        return parent::run($input, $output);
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('no-catch')) {
            $this->getApplication()->setCatchExceptions(false);
        }
        $this->style = new FunStyle($input, $output);
        if (!$this->confirmXedbug()) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    protected function isGitStatusClean(?OutputInterface $output = null): bool
    {
        $status_output = shell_exec('git status');
        if (strpos($status_output, 'nothing to commit, working tree clean') === false) {
            if ($output) {
                $output->write($status_output);
            }
            return false;
        }
        return true;
    }

    protected function ensureGitClean(OutputInterface $output) {
      if (!$this->isGitStatusClean($output)) {
        throw new \Exception("Not clean");
      }
    }



    /**
     * Get the d.o issue for the current branch.
     *
     * @return string
     */
    protected function getBranchIssue(string $branch = null): string
    {
        $useCurrent = false;
        if (!$branch) {
            $branch = $this->getCurrentBranch();
            $useCurrent = true;
        }
        $issue = static::getIssueInString($branch);
        if ($issue === null && $useCurrent) {
            print "probably not issue number $branch\n";
            return '';
        }
        return $issue;
    }

    protected function getCurrentBranch()
    {
        return trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
    }


    /**
     * @param $issue
     *
     * @return mixed
     */
    protected function getEntityInfo($issue, $type = 'node'): \stdClass
    {
        static $infos = [];
        if (!isset($infos[$issue])) {
            $url = "https://www.drupal.org/api-d7/$type/$issue.json";
            $infos[$issue] = $this->getURLDecodedJson($url);
        }
        return $infos[$issue];
    }

    protected function getURLDecodedJson(string $url)
    {
        return json_decode(file_get_contents($url));
    }

    protected function getTimeFromTimeStamp($timestamp)
    {
        $dt = new \DateTime("now", new \DateTimeZone(Settings::getSetting('timezone'))); //first argument "must" be a string
        $dt->setTimestamp($timestamp); //adjust the object to correct timestamp
        //echo $dt->format('d.m.Y, H:i:s');
        return $dt->format('d.m.Y, H:i:s');
    }

    protected function getIssueStatus($status_code)
    {
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
    protected function getNodeBranch($issue = null)
    {
        if (empty($issue)) {
            $issue = $this->getBranchIssue();
        }
        if (empty($issue)) {
            return '';
        }
        $version = $this->getEntityInfo($issue)->field_issue_version;
        if (strpos($version, '-dev') !== false) {
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
    protected static function shellExecSplit($string)
    {
        $output = shell_exec($string);
        $output = preg_split('/\n+/', trim($output));
        $output = array_map(function ($line) {
            return trim($line);
        }, $output);

        return array_filter($output);
    }

    protected function getMergeBase():?string
    {
        static $mergeBase = false;
        if ($mergeBase === false) {
            if ($current_branch = $this->getCurrentBranch()) {
                $issue_branch = $this->getNodeBranch();
                if (!$issue_branch) {
                    throw new \Exception("issue not in branch found");
                }
            } else {
                throw new \Exception("current branch  not found");
            }


            $commit = trim(shell_exec("git merge-base $issue_branch $current_branch"));
            $mergeBase = $commit ?? null;
        }
        return $mergeBase;
    }

    protected function getDiffPoint(): ?string
    {
        static $diffPoint = false;
        if ($diffPoint === false) {
            $mergeBase = $this->getMergeBase();
            if ($mergeBase) {
                $diffPoint = $mergeBase;
            } else {
                $diffPoint = $this->getNodeBranch();
            }
        }
        return $diffPoint;
    }

    /**
     * @param $issue
     */
    protected function getIssueFiles($issue, $pattern): array
    {
        $node_info = $this->getEntityInfo($issue);
        if (empty($node_info->field_issue_files)) {
            return [];
        } else {
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
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $diffPoint
     *
     * @return string[]
     */
    protected function getDiffFiles(string $diffPoint): array
    {
        return $this->shellExecSplit("git diff $diffPoint --name-only");
    }

  /**
   * Confirms running with xdebug on.
   *
   * @return bool
   */
    protected function confirmXedbug(): bool
    {
        /** @var \TedbowDrupalScripts\ScriptApplication $app */
        $app = $this->getApplication();
        // If running calling other commands only run this check once.
        if (static::CONFIRM_XDEBUG && !$app->isXdebugConfirmed() && static::isXdebugOn()) {
            $app->setXdebugConfirmed();
            return $this->style->confirm("️Xdebug is on, tests & composer will take longer! Continue?", false);
        }
        return true;
    }

  /**
   * Gets the root directory of the drupal project.
   *
   * @return string
   * @throws \Exception
   */
    protected function getDrupalRoot(): string
    {
        $originial_dir = getcwd();
        if ($this->isAtRoot()) {
            return getcwd();
        }
        $top_dir = Settings::getRequiredSetting("top_dir");
        $dir = getcwd();
        while (!$this->isAtRoot()) {
            if (strpos($dir, $top_dir) !== 0) {
                throw new \Exception("beyond top dir $top_dir");
            }
            chdir('..');
            $dir = getcwd();
            $this->style->info("dir = $dir");
        }
        chdir($originial_dir);
        return $dir;
    }

  /**
   * @param mixed $patch_path
   *
   * @return void
   */
  protected function applyPatch(string $patch_path, bool $reverse = FALSE): void {
    $options = $reverse ? ' -R ' : '';
    $return = NULL;
    system("git apply $options $patch_path", $return);
    if ($return !== 0) {
      throw new \Exception("Could not apply $options $patch_path");
    }

  }

  /**
   * @return void
   */
  protected function composerInstall(): void {
    system('rm -rf vendor');
    system('composer install');
  }

  /**
   * Determines is xdebug is on.
   *
   * @return bool
   */
    protected static function isXdebugOn(): bool
    {
        return ini_get('xdebug.mode') === "debug";
    }
}
