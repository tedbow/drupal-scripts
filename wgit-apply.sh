path=${1}
if [ $1 ]
then
  wget -q -O - $1 | git apply -3
else
  echo "please enter url"
  exit
fi
