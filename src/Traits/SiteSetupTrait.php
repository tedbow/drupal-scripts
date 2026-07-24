<?php

namespace TedbowDrupalScripts\Traits;

use Symfony\Component\Process\Process;

/**
 * Helpers for spinning up a Drupal dev site (ported from canvas_dev scripts).
 *
 * Expects the using class to provide $this->style (FunStyle/SymfonyStyle).
 */
trait SiteSetupTrait
{
    /**
     * Runs a shell command with live output; throws on failure.
     */
    protected function runLive(string $cmd, ?string $cwd = null, array $env = []): void
    {
        if (!$this->runLiveWarn($cmd, $cwd, $env)) {
            throw new \RuntimeException("Command failed: $cmd");
        }
    }

    /**
     * Like runLive(), but warns and returns false instead of throwing.
     */
    protected function runLiveWarn(string $cmd, ?string $cwd = null, array $env = []): bool
    {
        $this->style->writeln("<comment>Running: $cmd</comment>");
        // Process merges the given env over the inherited environment.
        $process = Process::fromShellCommandline($cmd, $cwd, $env ?: null);
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());
        $exit = $process->run(function ($type, $buffer) {
            $this->style->write($buffer);
        });
        if ($exit !== 0) {
            $this->style->warning("Command failed (exit $exit): $cmd");
            return false;
        }
        return true;
    }

    /**
     * Detects the Drupal core version a project's CI targets by parsing the
     * DRUPAL_CORE variable from its .gitlab-ci.yml.
     *
     * Returns null when the file is missing or has no DRUPAL_CORE variable.
     * Requires the using class to also use GitLabTrait (getRawProjectFile).
     */
    protected function detectDrupalVersion(string $projectPath, string $branch): ?string
    {
        $this->style->info("Fetching .gitlab-ci.yml from $projectPath@$branch to detect Drupal version...");
        $contents = $this->getRawProjectFile($projectPath, $branch, '.gitlab-ci.yml');
        if ($contents === null) {
            return null;
        }
        if (!preg_match('/^\s*DRUPAL_CORE:\s*([^\s#\r\n]+)/m', $contents, $matches)) {
            return null;
        }
        return $matches[1];
    }

    /**
     * Pin locked packages matching $prefix to their minor-version range.
     *
     * After 'composer install' from a lock file, subsequent 'composer require'
     * calls resolve fresh and can upgrade packages (e.g. symfony 7.3→7.4),
     * introducing deprecations that break phpstan. Pinning to ~X.Y.0 prevents
     * minor-version upgrades while still allowing patch updates.
     */
    protected function pinLockedPackages(string $siteDir, string $prefix): void
    {
        $lockFile = $siteDir . '/composer.lock';
        if (!file_exists($lockFile)) {
            return;
        }

        $lock = json_decode(file_get_contents($lockFile), true);
        if ($lock === null) {
            throw new \RuntimeException("Could not parse $lockFile");
        }

        $constraints = [];
        foreach ($lock['packages'] as $pkg) {
            if (str_starts_with($pkg['name'], $prefix)) {
                // ~X.Y.0 allows patch updates within the minor but blocks minor upgrades.
                $ver   = ltrim($pkg['version'], 'v');
                $parts = explode('.', $ver);
                $tilde = '~' . $parts[0] . '.' . ($parts[1] ?? '0') . '.0';
                $constraints[] = escapeshellarg($pkg['name'] . ':' . $tilde);
            }
        }

        if (empty($constraints)) {
            return;
        }

        // --no-update: add constraints to composer.json without triggering
        // installs. Subsequent 'composer require' calls respect these.
        $this->runLive(sprintf(
            'composer require %s --no-update --no-interaction',
            implode(' ', $constraints)
        ), $siteDir);
    }

    /**
     * Install a module's require-dev dependencies into the Drupal root.
     *
     * When $split is true (canvas), uses 4 separate composer require calls to
     * avoid solver interactions:
     * 1. drupal/core-dev alone — heaviest dep, drives most constraint resolution
     * 2. remaining module deps (drupal/ai, etc.)
     * 3. $extraDevRequires (e.g. drupal/coder:^8.3) — installed alone, last,
     *    so the solver picks the latest match without interference
     * 4. phpstan-family packages — installing with large Drupal deps causes
     *    silent drops
     *
     * When $split is false, everything installs in one composer require.
     */
    protected function installDevDeps(string $siteDir, string $moduleDir, string $drupalVersion, bool $split, array $extraDevRequires = []): void
    {
        $env = ['COMPOSER_ROOT_VERSION' => self::composerRootVersion($drupalVersion)];

        $moduleComposer = json_decode(file_get_contents($moduleDir . '/composer.json'), true);
        if ($moduleComposer === null) {
            throw new \RuntimeException("Could not parse $moduleDir/composer.json");
        }

        $requireDev = $moduleComposer['require-dev'] ?? [];
        if (empty($requireDev) && empty($extraDevRequires)) {
            $this->style->info('No require-dev in module composer.json — skipping.');
            return;
        }

        $phpstanPackages = [];
        $otherPackages   = [];
        foreach ($requireDev as $package => $version) {
            // Skip platform requirements (php, ext-*, lib-*) — no slash in name.
            if (!str_contains($package, '/')) {
                continue;
            }
            // Packages the caller pins explicitly are handled separately.
            if (isset($extraDevRequires[$package])) {
                continue;
            }
            $entry = escapeshellarg($package . ':' . $version);
            if (str_starts_with($package, 'phpstan/')  ||
                str_starts_with($package, 'phpat/')    ||
                str_starts_with($package, 'jangregor/') ||
                str_starts_with($package, 'mglaman/')  ||
                str_starts_with($package, 'shipmonk/')
            ) {
                $phpstanPackages[] = $entry;
            } else {
                $otherPackages[] = $entry;
            }
        }

        $extraEntries = [];
        foreach ($extraDevRequires as $package => $version) {
            $extraEntries[] = escapeshellarg($package . ':' . $version);
        }

        if (!$split) {
            $all = array_merge($otherPackages, $phpstanPackages, $extraEntries);
            if (empty($all)) {
                return;
            }
            $this->style->info('Installing module dev dependencies...');
            $this->runLive(sprintf(
                'composer require %s --dev --no-interaction -W',
                implode(' ', $all)
            ), $siteDir, $env);
            return;
        }

        $coreMajorMinor = implode('.', array_slice(explode('.', $env['COMPOSER_ROOT_VERSION']), 0, 2));

        $this->style->info('Installing drupal/core-dev...');
        $this->runLive(sprintf(
            'composer require %s --dev --no-interaction -W',
            escapeshellarg("drupal/core-dev:^$coreMajorMinor")
        ), $siteDir, $env);

        if (!empty($otherPackages)) {
            $this->style->info('Installing module Drupal dependencies...');
            $this->runLive(sprintf(
                'composer require %s --dev --no-interaction -W',
                implode(' ', $otherPackages)
            ), $siteDir, $env);
        }

        if (!empty($extraEntries)) {
            $this->style->info('Installing pinned dev packages: ' . implode(' ', array_keys($extraDevRequires)));
            $this->runLive(sprintf(
                'composer require %s --dev --no-interaction',
                implode(' ', $extraEntries)
            ), $siteDir, $env);
        }

        if (!empty($phpstanPackages)) {
            $this->style->info('Installing static analysis dependencies...');
            $this->runLive(sprintf(
                'composer require %s --dev --no-interaction -W',
                implode(' ', $phpstanPackages)
            ), $siteDir, $env);
        }
    }

    /**
     * COMPOSER_ROOT_VERSION requires semver — "11.3.x" → "11.3.0".
     */
    protected static function composerRootVersion(string $drupalVersion): string
    {
        return preg_replace('/\.x$/', '.0', $drupalVersion);
    }

    protected function writePhpUnitXml(string $siteDir, string $siteName): void
    {
        $distFile = $siteDir . '/core/phpunit.xml.dist';
        $xmlFile  = $siteDir . '/core/phpunit.xml';

        if (!file_exists($distFile)) {
            $this->style->info('core/phpunit.xml.dist not found — skipping phpunit.xml generation.');
            return;
        }

        $contents = file_get_contents($distFile);
        $baseUrl  = "http://{$siteName}.test/";
        $dbUrl    = 'sqlite://localhost/sites/default/files/.ht.sqlite';

        $contents = preg_replace(
            '/(<env name="SIMPLETEST_BASE_URL"\s+value=")[^"]*(")/m',
            '${1}' . $baseUrl . '${2}',
            $contents
        );
        $contents = preg_replace(
            '/(<env name="SIMPLETEST_DB"\s+value=")[^"]*(")/m',
            '${1}' . $dbUrl . '${2}',
            $contents
        );

        file_put_contents($xmlFile, $contents);
        $this->style->info("Written: $xmlFile");
        $this->style->info("  SIMPLETEST_BASE_URL=$baseUrl");
        $this->style->info("  SIMPLETEST_DB=$dbUrl");
    }

    /**
     * Append a settings.php line if not already present.
     * $webRoot is the directory that contains sites/ (i.e. the Drupal web root).
     */
    protected function appendSettingsLine(string $webRoot, string $line): void
    {
        $settingsFile = $webRoot . '/sites/default/settings.php';
        if (!file_exists($settingsFile) || str_contains(file_get_contents($settingsFile), $line)) {
            return;
        }
        // drush si sets settings.php to 444; make it writable temporarily.
        chmod($settingsFile, 0644);
        file_put_contents($settingsFile, "\n" . $line . "\n", FILE_APPEND);
        chmod($settingsFile, 0444);
    }

    /**
     * Ensures sites/default has settings.php and a writable files dir.
     */
    protected function scaffoldSettings(string $siteDir): void
    {
        $defaultDir   = $siteDir . '/sites/default';
        $settingsFile = $defaultDir . '/settings.php';

        if (!is_dir($defaultDir)) {
            mkdir($defaultDir, 0755, true);
        }
        if (!file_exists($settingsFile) && file_exists($defaultDir . '/default.settings.php')) {
            copy($defaultDir . '/default.settings.php', $settingsFile);
        }
        $filesDir = $defaultDir . '/files';
        if (!is_dir($filesDir)) {
            mkdir($filesDir, 0777, true);
        }
    }

    /**
     * Records extra modules in .site-extras.json so they can be re-required
     * after a git reset on core.
     */
    protected function saveExtras(string $siteDir, string $project, array $modules): void
    {
        $path     = $siteDir . '/.site-extras.json';
        $existing = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        $existing['project'] = $project;
        $existing['modules'] = array_values(array_unique(array_merge($existing['modules'] ?? [], $modules)));
        file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT) . "\n");
        $this->style->info('Extras saved to .site-extras.json');
    }
}
