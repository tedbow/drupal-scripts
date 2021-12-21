<?php

namespace TedbowDrupalScripts;

use Symfony\Component\Yaml\Yaml;

/**
 * Common functions for getting settings from yml files.
 */
abstract class SettingsBase
{

    use UtilsTrait;

  /**
   * The settings file.
   *
   * @var string
   */
    const FILE = '';

  /**
   * Get all settings.
   *
   * @return array
   */
    public static function getSettings()
    {
        static $settings;
        if (!isset($settings)) {
            $settings = Yaml::parseFile(static::getBaseDir() . '/' . static::FILE);
        }
        return $settings;
    }

  /**
   * Gets an individual setting.
   *
   * @param string $key
   * @param null $default
   *
   * @return mixed|null
   */
    public static function getSetting(string $key, $default = null)
    {
        $settings = static::getSettings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Gets a require setting.
     *
     * @param string $key
     *
     * @return mixed|null
     *
     * @throws \Exception
     *   Thrown if setting is not available.
     */
    public static function getRequiredSetting(string $key)
    {
        $setting = static::getSetting($key);
        if ($setting === null) {
            throw new \Exception("Setting $key is required");
        }
        return $setting;
    }
}
