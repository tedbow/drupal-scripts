#! /usr/local/opt/php@7.2/bin/php
<?php
print system("vendor/bin/phpunit --configuration core core/modules/update/tests/src/Unit/ProjectCoreCompatibilityTest.php");
exit();
print_r($argv);

print_r(getopt(null, ["name:"]));
