<?php

namespace TedbowDrupalScripts\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TedbowDrupalScripts\IssueRef;
use TedbowDrupalScripts\Settings;
use TedbowDrupalScripts\Traits\GitLabTrait;
use TedbowDrupalScripts\Traits\SiteSetupTrait;

/**
 * Spins up an isolated Drupal dev site using a core clone plus a contrib
 * module clone (ported and generalized from canvas_dev/canvasCoreSetup.php).
 *
 * Clones drupal/drupal at the tag the module's CI uses, then clones the
 * module into modules/contrib/<project>. Matches CI so phpcs/phpstan work.
 */
class SiteSetup extends CommandBase
{
    use GitLabTrait;
    use SiteSetupTrait;

    protected static $defaultName = 'site:setup';
    protected static $requireAtRoot = false;
    protected const REQUIRE_CLEAN_GIT = false;

    private const DRUPAL_CORE_GIT = 'git@git.drupal.org:project/drupal.git';

    /**
     * Per-project behavior overrides; projects not listed use generic
     * defaults (default branch from GitLab, single dev-deps install, npm
     * build only when package.json defines a build script).
     */
    private const PROJECT_OVERRIDES = [
        'canvas' => [
            'default_branch'     => '1.x',
            // 4-way composer install order (core-dev, deps, coder, phpstan).
            'split_dev_deps'     => true,
            // drupal/coder:^8.3 pins phpcs 3.x — canvas's phpcs config
            // references sniffs that coder 9.x (phpcs 4.x) dropped.
            'extra_dev_requires' => ['drupal/coder' => '^8.3'],
            'npm_build'          => 'npm run build -w ui',
        ],
    ];

    protected function configure()
    {
        parent::configure();
        $this->setDescription('Spin up an isolated Drupal dev site with a contrib module cloned from git');
        $this->setAliases(['setup-site']);
        $this->addArgument('site-dir', InputArgument::REQUIRED, 'Directory name for the new site (created under sites_dir)');
        $this->addOption('project', null, InputOption::VALUE_REQUIRED, 'Contrib project machine name', 'canvas');
        $this->addOption('issue-url', null, InputOption::VALUE_REQUIRED, 'Issue URL or MR URL — checks out the matching MR branch');
        $this->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Module branch to checkout (default: project default branch)');
        $this->addOption('drupal-version', null, InputOption::VALUE_REQUIRED, 'Override Drupal version (default: detected from module CI)');
        $this->addOption('modules', null, InputOption::VALUE_REQUIRED, 'Extra modules to require (e.g. drupal/ai,drupal/search_api)');
        $this->addOption('site-name', null, InputOption::VALUE_REQUIRED, 'Drupal site name (default: "<Project> Dev:<site-dir>")');
        $this->addOption('skip-install', null, InputOption::VALUE_NONE, 'Skip drush site-install');
        $this->addOption('deep-clone', null, InputOption::VALUE_NONE, 'Full core git clone instead of --depth=1');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (self::FAILURE === parent::execute($input, $output)) {
            return self::FAILURE;
        }

        $siteName = $input->getArgument('site-dir');
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $siteName)) {
            $this->style->error('Site name must contain only letters, numbers, underscores, and hyphens.');
            return self::FAILURE;
        }

        if ($input->getOption('issue-url') && $input->getOption('branch')) {
            $this->style->error('--issue-url and --branch cannot be combined; the MR determines the branch.');
            return self::FAILURE;
        }

        $project = $input->getOption('project');
        $canonicalPath = "project/$project";

        // -------------------------------------------------------------------
        // Resolve MR from --issue-url (issue URL or MR URL).
        // -------------------------------------------------------------------
        $mrSelection = null;
        if ($issueUrl = $input->getOption('issue-url')) {
            try {
                $ref = IssueRef::parse($issueUrl);
            } catch (\InvalidArgumentException $e) {
                $this->style->error($e->getMessage());
                return self::FAILURE;
            }
            // The URL names the canonical project; let it drive --project.
            if ($ref->projectPath) {
                $urlProject = basename($ref->projectPath);
                if ($urlProject !== $project) {
                    $this->style->info("Using project '$urlProject' from --issue-url (overrides '$project').");
                    $project = $urlProject;
                }
                $canonicalPath = $ref->projectPath;
            }
            try {
                $mrSelection = $this->resolveMrSelection($ref, $canonicalPath);
            } catch (\Exception $e) {
                $this->style->error($e->getMessage());
                return self::FAILURE;
            }
            if (!$mrSelection) {
                $this->style->error("No merge requests found for $issueUrl");
                return self::FAILURE;
            }
            // A bare drupal.org/i/N URL carries no project; the fork's
            // upstream project is authoritative.
            if ($mrSelection['canonicalPath'] !== $canonicalPath) {
                $canonicalPath = $mrSelection['canonicalPath'];
                $project = basename($canonicalPath);
                $this->style->info("Using project '$project' derived from the issue fork.");
            }
        }

        $overrides = self::PROJECT_OVERRIDES[$project] ?? [];

        $sitesDir  = rtrim(Settings::getSetting('sites_dir', ($_SERVER['HOME'] ?? getenv('HOME')) . '/sites'), '/');
        $siteDir   = $sitesDir . '/' . $siteName;
        $moduleDir = $siteDir . '/modules/contrib/' . $project;
        $siteTitle = $input->getOption('site-name') ?? ucfirst($project) . " Dev:$siteName";

        if (is_dir($siteDir)) {
            $this->style->error("Directory already exists: $siteDir");
            return self::FAILURE;
        }

        // -------------------------------------------------------------------
        // Resolve module branch.
        // -------------------------------------------------------------------
        if ($mrSelection) {
            $branch = $mrSelection['sourceBranch'];
        } else {
            $branch = $input->getOption('branch')
                ?? $overrides['default_branch']
                ?? $this->getProjectDefaultBranch($canonicalPath);
            if (!$branch) {
                $this->style->error("Could not determine default branch for $canonicalPath; use --branch.");
                return self::FAILURE;
            }
        }

        // -------------------------------------------------------------------
        // Detect Drupal version.
        // -------------------------------------------------------------------
        $drupalVersion = $input->getOption('drupal-version');
        if (!$drupalVersion) {
            if ($mrSelection) {
                // The MR branch only exists on the fork; its CI file may
                // differ from the canonical project's.
                $drupalVersion = $this->detectDrupalVersion($mrSelection['forkPath'], $mrSelection['sourceBranch'])
                    ?? $this->detectDrupalVersion($canonicalPath, $mrSelection['targetBranch']);
            } else {
                $drupalVersion = $this->detectDrupalVersion($canonicalPath, $branch);
            }
        }
        if (!$drupalVersion) {
            $drupalVersion = $this->style->ask('Could not detect DRUPAL_CORE from CI. Drupal core version (e.g. 11.3.x)');
            if (!$drupalVersion) {
                return self::FAILURE;
            }
        }

        $this->style->info("Project        : $project");
        $this->style->info("Drupal version : $drupalVersion");
        $this->style->info("Module branch  : $branch" . ($mrSelection ? " (MR !{$mrSelection['iid']})" : ''));
        $this->style->info("Site dir       : $siteDir");

        $composerEnv = ['COMPOSER_ROOT_VERSION' => self::composerRootVersion($drupalVersion)];

        // -------------------------------------------------------------------
        // Clone Drupal core at exact tag/branch.
        // -------------------------------------------------------------------
        $this->style->section("Cloning drupal/drupal at $drupalVersion...");
        $depth = $input->getOption('deep-clone') ? '' : '--depth=1 ';
        $this->runLive(sprintf(
            'git clone %s--branch %s %s %s',
            $depth,
            escapeshellarg($drupalVersion),
            escapeshellarg(self::DRUPAL_CORE_GIT),
            escapeshellarg($siteDir)
        ));

        // -------------------------------------------------------------------
        // Clone the module (always full — MR checkout needs real refs).
        // -------------------------------------------------------------------
        $this->style->section("Cloning $project module...");
        mkdir(dirname($moduleDir), 0755, true);
        $this->runLive(sprintf(
            'git clone %s %s',
            escapeshellarg("git@git.drupal.org:$canonicalPath.git"),
            escapeshellarg($moduleDir)
        ));
        if ($mrSelection) {
            $remoteName = basename($mrSelection['forkPath']);
            $this->runLive(sprintf(
                'git remote add %s %s',
                escapeshellarg($remoteName),
                escapeshellarg("git@git.drupal.org:{$mrSelection['forkPath']}.git")
            ), $moduleDir);
            $this->runLive(sprintf('git fetch %s %s', escapeshellarg($remoteName), escapeshellarg($branch)), $moduleDir);
            $this->runLive(sprintf(
                'git checkout -b %s --track %s',
                escapeshellarg($branch),
                escapeshellarg("$remoteName/$branch")
            ), $moduleDir);
        } else {
            $this->runLive(sprintf('git checkout %s', escapeshellarg($branch)), $moduleDir);
        }

        // -------------------------------------------------------------------
        // Composer: install core deps, pin symfony, require drush.
        // -------------------------------------------------------------------
        $this->style->section('Running composer install for Drupal core...');
        $this->runLive('composer install', $siteDir);

        // 'composer install' uses composer.lock; later 'composer require'
        // calls resolve fresh and can upgrade symfony past what CI uses.
        $this->style->section('Pinning Symfony packages to lock-file versions...');
        $this->pinLockedPackages($siteDir, 'symfony/');

        $this->style->section('Requiring drush...');
        $this->runLive('composer require drush/drush --no-interaction', $siteDir, $composerEnv);

        // -------------------------------------------------------------------
        // Module dev dependencies.
        // -------------------------------------------------------------------
        $this->style->section("Installing $project dev dependencies...");
        $this->installDevDeps(
            $siteDir,
            $moduleDir,
            $drupalVersion,
            $overrides['split_dev_deps'] ?? false,
            $overrides['extra_dev_requires'] ?? []
        );

        // -------------------------------------------------------------------
        // Extra modules.
        // -------------------------------------------------------------------
        $extraModules = array_filter(explode(',', (string) $input->getOption('modules')));
        if ($extraModules) {
            $this->style->section('Requiring extra modules: ' . implode(', ', $extraModules));
            $this->runLive(sprintf(
                'composer require %s --no-interaction -W',
                implode(' ', array_map('escapeshellarg', $extraModules))
            ), $siteDir, $composerEnv);
            $this->saveExtras($siteDir, $project, $extraModules);
        }

        // -------------------------------------------------------------------
        // Site install.
        // -------------------------------------------------------------------
        $this->style->section('Writing settings.php...');
        $this->scaffoldSettings($siteDir);

        if ($input->getOption('skip-install')) {
            $this->style->info('Skipped site install (--skip-install). Run manually:');
            $this->style->info("  cd $siteDir");
            $this->style->info('  vendor/bin/drush si --db-url=sqlite://sites/default/files/.ht.sqlite --account-name=admin --account-pass=admin -y');
            $this->style->info("  vendor/bin/drush en $project -y");
            return self::SUCCESS;
        }

        $this->style->section('Installing Drupal...');
        $this->runLive(sprintf(
            'vendor/bin/drush si --db-url=%s --site-name=%s --account-name=admin --account-pass=admin -y',
            escapeshellarg('sqlite://sites/default/files/.ht.sqlite'),
            escapeshellarg($siteTitle)
        ), $siteDir);

        // Required for hidden test modules to be discoverable.
        $this->appendSettingsLine($siteDir, "\$settings['extension_discovery_scan_tests'] = TRUE;");

        $this->style->section('Writing core/phpunit.xml for functional tests...');
        $this->writePhpUnitXml($siteDir, $siteName);

        $this->style->section("Enabling $project...");
        $this->runLiveWarn("vendor/bin/drush en $project -y", $siteDir);

        // -------------------------------------------------------------------
        // Frontend build (canvas: workspace build; generic: only when the
        // module's package.json defines a build script).
        // -------------------------------------------------------------------
        $npmBuild = $overrides['npm_build'] ?? $this->detectNpmBuild($moduleDir);
        if ($npmBuild) {
            $this->style->section('Installing JS dependencies...');
            $this->runLiveWarn('npm install', $moduleDir);
            $this->style->section('Building frontend...');
            $this->runLiveWarn($npmBuild, $moduleDir);
        }

        // -------------------------------------------------------------------
        // One-time login.
        // -------------------------------------------------------------------
        $siteUrl  = "http://$siteName.test/";
        // --no-browser: drush uli opens the browser by default; we open it
        // ourselves so the URL is also printed.
        $loginUrl = trim((string) shell_exec(sprintf(
            'cd %s && vendor/bin/drush uli --uri=%s --no-browser 2>/dev/null',
            escapeshellarg($siteDir),
            escapeshellarg($siteUrl)
        )));
        $this->style->success('Site ready!');
        if ($loginUrl) {
            $this->style->info("One-time login: $loginUrl");
            $this->runLiveWarn(sprintf('open %s', escapeshellarg($loginUrl)));
        }
        $this->style->info("Module: $moduleDir");

        return self::SUCCESS;
    }

    /**
     * Resolves --issue-url (issue or MR URL) to the MR to check out.
     *
     * @return array{sourceBranch: string, targetBranch: string, forkPath: string, canonicalPath: string, iid: string}|null
     */
    private function resolveMrSelection(IssueRef $ref, string $canonicalPath): ?array
    {
        if ($ref->mrIid !== null) {
            $mr = $this->getMergeRequest($canonicalPath, $ref->mrIid);
            $fork = $this->getProjectById($mr['source_project_id']);
            return [
                'sourceBranch'  => $mr['source_branch'],
                'targetBranch'  => $mr['target_branch'],
                'forkPath'      => $fork['path_with_namespace'],
                'canonicalPath' => $canonicalPath,
                'iid'           => (string) $mr['iid'],
            ];
        }

        $forkInfo = $this->getProjectInfo($ref->number, basename($canonicalPath));
        if (!$forkInfo) {
            throw new \RuntimeException("No issue fork found for issue {$ref->number}");
        }
        $resolvedCanonical = $this->getCanonicalProjectPath($forkInfo, $ref);
        $mrs = $this->getProjectMrs((string) $forkInfo['id'], false, $resolvedCanonical);
        $selected = $this->chooseOpenMr($mrs, "issue {$ref->number}");
        if (!$selected) {
            return null;
        }
        return [
            'sourceBranch'  => $selected['source_branch'],
            'targetBranch'  => $selected['target_branch'],
            'forkPath'      => $forkInfo['path_with_namespace'],
            'canonicalPath' => $resolvedCanonical,
            'iid'           => (string) $selected['iid'],
        ];
    }

    /**
     * Returns "npm run build" when the module has a package.json build script.
     */
    private function detectNpmBuild(string $moduleDir): ?string
    {
        $packageFile = $moduleDir . '/package.json';
        if (!file_exists($packageFile)) {
            return null;
        }
        $package = json_decode(file_get_contents($packageFile), true);
        return isset($package['scripts']['build']) ? 'npm run build' : null;
    }
}
