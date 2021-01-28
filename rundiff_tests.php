#! /usr/local/opt/php@7.3/bin/php
<?php
require_once "global.php";
if (isset($global_options['no-tests'])) {
    print "⚠️no tests\n";
    return;
}
function runDiffTests($branch) {

  $files = getDiffFiles($branch);

  // Only run unit for now
  $modules_to_run = [];
  $all_pass = TRUE;
  foreach ($files as $file) {
    if (strpos($file, 'core/modules/') === 0) {
      $parts = explode('/', $file);
      // Make a list modules to run all unit tests for any modules changed.
      $module = $parts[2];
      if (!in_array($module, $modules_to_run)) {
        $modules_to_run[] = $module;
      }
        if (strpos($file, '/tests/src') !== FALSE && strpos($file, '/Unit') === FALSE) {
            // Run any non-unit tests that are different
            $output = shell_exec("vendor/bin/phpunit --configuration core $file");
            print $output;
            if (strpos($output, 'Errors') !== FALSE || strpos($output, 'FAILURES!') !== FALSE) {
                $all_pass = FALSE;
            }
        }
    }
  }

  if ($modules_to_run) {
    foreach ($modules_to_run as $module) {
        $unit_dir = "core/modules/$module/tests/src/Unit";
        if (file_exists($unit_dir)) {
            $output = shell_exec("vendor/bin/phpunit --configuration core $unit_dir");
            if ($module !== 'system') {
                //$output .= shell_exec("vendor/bin/phpunit --configuration core core/modules/$module/tests/src/Kernel");
            }
            print $output;
            if (strpos($output, 'Errors:') !== FALSE) {
                $all_pass = FALSE;
            }
        }


    }
  }
  if ($all_pass) {
      print "🎉🎉🎉🎉🎉 PHPUnit Passed 🎉🎉🎉🎉🎉\n";
  }
  else {
      print "☹️☹️☹️☹️☹️ Tests Failed ☹️☹️☹️☹️☹️\n";
      exit(1);
  }

  return $all_pass;
}

if (isset($argv[1]) && $argv[1] === 'y') {
    if (runDiffTests($current_head)) {
        print "🎉🎉🎉🎉🎉 All Pass 🎉🎉🎉🎉🎉\n";
    }
    else {
        print "🔥🔥🔥🔥🔥🔥🔥 Fails \n";
    }
}

