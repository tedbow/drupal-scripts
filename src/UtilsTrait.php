<?php

namespace TedbowDrupalScripts;


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

}
