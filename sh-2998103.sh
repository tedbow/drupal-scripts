function isClean() {
  if [[ $(git diff) ]] || [[ $(git ls-files . --exclude-standard --others) ]]; then
      echo 0
  else
      echo 1
  fi
}

function ensureClean() {
  if [[ $(isClean) == 0  ]]; then
      echo "****** Repo must be clean to use this script ******"
      git status
      exit;
  fi
}
# Ensure at Drupal root.
function ensureCoreRoot() {
  ## Files that should be in root.
declare -a rootFiles=("index.php" "robots.txt" "update.php")

  ## Make sure each file is there.
  for rootFile in "${rootFiles[@]}"
  do
     if [[ $(ls ${rootFile}) != "${rootFile}" ]]; then
       echo "${rootFile} not found.";
       exit;
     fi
  done
}

ensureCoreRoot
ensureClean
git checkout 8.5.5
sudo git reset --h
drush sql-drop -y
new-code-base.php
drush si standard --account-pass=admin --locale=es -y
open http://d8/user/login
read -p "sign-in in the web. Continue? " -n 1 -r choice
git checkout 8.6.0
sudo git reset --h
new-code-base.php
open http://d8/update.php/selection
read -p "run update.php via web. Continue? " -n 1 -r choice
open http://d8/admin/structure/views/view/watchdog/edit/page
read -p "edit and save the view. Continue? " -n 1 -r choice



