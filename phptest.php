#! /usr/local/opt/php@7.3/bin/php
<?php
print_r(getopt(null, ["h:"]));
exit;
print system("vendor/bin/phpunit --configuration core core/modules/update/tests/src/Unit/ProjectCoreCompatibilityTest.php");
exit();
print_r($argv);

print_r(getopt(null, ["name:"]));
