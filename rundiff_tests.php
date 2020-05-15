#! /usr/local/opt/php@7.2/bin/php
<?php
require_once "global.php";
if (isset($global_options['no-tests'])) {
    print "⚠️no tests\n";
    return;
}
function runDiffTests($branch) {

  $files = shell_exec_split("git diff $branch --name-only");

  // Only run unit for now
  $modules_to_run = [];

  foreach ($files as $file) {
    if (strpos($file, 'core/modules/') === 0) {
      $parts = explode('/', $file);
      $module = $parts[2];
      if (!in_array($module, $modules_to_run)) {
        $modules_to_run[] = $module;
      }
    }
  }
  if ($modules_to_run) {
    $all_pass = TRUE;
    foreach ($modules_to_run as $module) {
      $output = shell_exec("vendor/bin/phpunit --configuration core core/modules/$module/tests/src/Unit");
      if ($module !== 'system') {
        //$output .= shell_exec("vendor/bin/phpunit --configuration core core/modules/$module/tests/src/Kernel");
      }

      print $output;
      if (strpos($output, 'Errors:') !== FALSE) {
        $all_pass = FALSE;
      }
    }
    return $all_pass;
  }
  return TRUE;
}

if (isset($argv[1]) && $argv[1] === 'y') {
    if (runDiffTests($current_head)) {
        print "🎉🎉🎉🎉🎉 All Pass 🎉🎉🎉🎉🎉\n";
    }
    else {
        print "🔥🔥🔥🔥🔥🔥🔥 Fails \n";
    }
}

