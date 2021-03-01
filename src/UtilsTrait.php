<?php

namespace TedbowDrupalScripts;


use Symfony\Component\Yaml\Yaml;

/**
 * Misc utilities.
 */
trait UtilsTrait {

  /**
   * Gets the current base directory.
   *
   * @return string
   */
  protected static function getBaseDir() {
    [$scriptPath] = get_included_files();
    return dirname($scriptPath);
  }

  protected static function parseYml($file) {
      return Yaml::parseFile(static::getBaseDir() . "/$file.yml" );
  }
}
