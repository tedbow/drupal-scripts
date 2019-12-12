#!/bin/bash

# to call from keyboard shorcut -     gnome-terminal -e "bash /home/ted/Desktop/campscripts/demo1.sh"
# cd /Users/ted/htdocs/d7_firstmodule/sites/all/modules/custom/role_notices
git stash
CURRENT_TAG="$(git describe --always --tag)"
echo "***** Current Tag     ********"
echo $CURRENT_TAG
echo "***** Available Tags *********"
git tag -l "8.x-4.demo*" -n2
echo "Choose tag"
read -n 1 TAG
if [[ "${TAG}" == "q" ]]; then
echo '-- No changes'
exit
fi

if [[ "${TAG}" == "f" ]]; then
TAG="final"
fi

git checkout 8.x-4.demo.$TAG
drush cr
git diff $CURRENT_TAG --compact-summary
echo "***** Current stage *********"
git tag -l 8.x-4.demo.$TAG -n2
exit
