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
rm -rf vendor && composer install
drush si standard --account-pass=admin -y
drush en locale -y
open http://d8/user/login
read -p "login and enable spanish as default. Continue? " -n 1 -r choice

drush cex -y
read -p "change view empty text . Continue? " -n 1 -r choice
drush cim
# Enabling a module is required to invoke locale_modules_installed().
drush en action -y
git checkout 8.6.0
sudo git reset --h
rm -rf vendor && composer install
open http://d8/update.php/selection
read -p "run update.php via web. Continue? " -n 1 -r choice
open http://d8/admin/structure/views/view/watchdog/edit/page
read -p "edit and save the view. Continue? " -n 1 -r choice



