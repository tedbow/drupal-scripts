#!/bin/bash

# Creates a new branch from drupal.org patch files
# Parameters
# 1. patch from drupal.org(other files won't work for now)
# 2. The branch to start from.

## Ensure that there are no changes and no untracked files


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

function logBranch() {
  RED='\033[0;31m'
  NC='\033[0m' # No Color
  printf "${RED}"
  git log --pretty=format:"%an, %ar : %s" -3 ${startBranch}...${newBranchName}
  printf "${NC}"
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

if [ "$#" -lt 2 ]; then
    echo " ** Please provide patch url and branch ** "
    exit;
fi
path=${1}
startBranch=${2}

if [[ $(git branch --l $startBranch) ]]; then
    git checkout $startBranch
    ensureClean
    git pull
    ensureClean
else
    echo "****** Branch ${startBranch} does NOT exist ******"
    exit;
fi

IFS='/' read -r -a patchparts <<< "${1}"

# Hardcoded for d.o file pattern
fileName="${patchparts[6]}"
 # echo "*${fileName}*"

IFS='.' read -r -a fileParts <<< "${fileName}"

newBranchName="${fileParts[0]}"

echo "**${newBranchName}**"
if [[ $(git branch --l $newBranchName) ]]; then
    echo "****** Branch ${newBranchName} already exists ******"
    logBranch
    read -p "Switch to this branch? [y]es / [r] switch and rebase / [n] no " -n 1 -r choice
    echo ""
    case "$choice" in
      y)
        git checkout "${newBranchName}"
        ;;
      r)
        git checkout "${newBranchName}"
        git rebase 8.6.x
        ;;
    esac
else
  git checkout -b  "${newBranchName}"

  wgit-apply.sh "${1}"

  if [[ $(isClean) == 1  ]]; then
      echo "***** Patch did not apply. ****"
      read -p "Apply with rejects? y/n" -n 1 -r choice
      echo ""
      if [[ ${choice} == "y" ]]; then
        wget -q -O - $1 | git apply - --reject
        git status
        # @todo Actually commit with rejects?
        exit;
      fi
      git checkout $startBranch
      git branch -D "${newBranchName}"
      exit;
  fi

  git add core

  git commit -m "Patch ${1}"

  ensureClean
fi

read -p "Clear cache[c], Site install[i] or Neiter[n]?" -n 1 -r choice
echo ""
case "$choice" in
  c)
    drush cr
    ;;
  i)
    drush sql-drop
    drush si -y --account-pass=pass
    ;;
esac
