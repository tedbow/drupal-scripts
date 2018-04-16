#!/bin/bash

# Creates a new branch from drupal.org patch files
#

function ensureClean() {
  if [[ $(git diff) ]] || [[ $(git ls-files . --exclude-standard --others) ]]; then
      echo "****** Repo must be clean to use this script ******"
      git status
      exit;
  fi
}

function ensureCoreRoot() {
  ## declare an array variable
declare -a rootFiles=("index.php" "robots.txt" "update2.php")

## now loop through the above array
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
br=${2}

if [[ $(git branch --l $br) ]]; then
    git checkout $br
else
    echo "****** Branch ${br} does NOT exist ******"
    exit;
fi


ensureClean

IFS='/' read -r -a patchparts <<< "${1}"

# Hardcoded for d.o file pattern
fileName="${patchparts[6]}"
 # echo "*${fileName}*"

IFS='.' read -r -a fileParts <<< "${fileName}"

newBranchName="${fileParts[0]}"

if [[ $(git branch --l $newBranchName) ]]; then
    echo "****** Branch ${newBranchName} already exists ******"
    exit;
fi

git checkout -b  "${newBranchName}"

wgit-apply.sh "${1}"


git add core

git commit -m "Patch ${1}"
