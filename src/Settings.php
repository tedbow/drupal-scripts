<?php

namespace TedbowDrupalScripts;

/**
 * Project settings.
 *
 * The setting file is not committed to the repo.
 *
 * It has sensitive information.
 */
class Settings extends SettingsBase {

  public const FILE = 'settings.yml';

  public static function isTesting() {
    return static::getSetting('is_testing');
  }

}
