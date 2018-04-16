#!/bin/bash

# Copied from timplunkett - https://gist.github.com/timplunkett/0fcd916aeb51a8cd5c60

PROFILE="standard"
DB="d8"
UI=false
NO_DEV=false

OPTS=`getopt -o h --longoptions db:,profile:,ui,no-dev -- "$@"`
eval set -- "$OPTS"
while true; do
  case "$1" in
    --db ) DB="$2"; shift; shift ;;
    --profile ) PROFILE="$2"; shift; shift ;;
    --ui ) UI=true; shift ;;
    --no-dev ) NO_DEV=true; shift ;;
    -- ) shift; break ;;
    * ) break ;;
  esac
done

#xdebug-toggle off;

# Drop the existing database, if any.
#drush sql-drop -y;

# Remove the files from a previous install.
sudo rm -rf sites/default;
# Restore the directories and files that are included by Drupal.
sudo git checkout -- sites/default;

# Fix permissions and ownership problems caused by sudo.
sudo chmod -R 777 sites/default;
sudo chown -R `whoami` sites/default;
# Because we are paranoid, do it again?!
sudo git checkout -- sites/default;
sudo chown -R `whoami` sites/default;

# If --ui is passed, do not install to allow for a UI-based install.
if [ "$UI" = true ] ; then
  exit
fi

drush si $PROFILE --db-url=mysql://root:root@localhost:3306/$DB -y --account-pass=pass

# If --no-dev is passed, do not set up any dev features.
if [ "$NO_DEV" = true ] ; then
  exit
fi

# Enable the development settings (disable CSS/JS aggregation, render caches, etc.)
sudo cp sites/example.settings.local.php sites/default/settings.local.php
printf "include __DIR__ . '/settings.local.php';\n\$settings['cache']['bins']['render'] = 'cache.backend.null';\n\$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';\n\$settings['extension_discovery_scan_tests'] = TRUE;" | sudo tee -a sites/default/settings.php > /dev/null

# Uninstall cache modules.
drush pmu page_cache -y
drush pmu dynamic_page_cache -y

# Enable simpletest.module
drush en simpletest -y

# Turn on logging.
drush cset system.logging error_level 'all' -y

xdebug-toggle on;
