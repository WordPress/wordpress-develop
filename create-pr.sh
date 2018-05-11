#!/bin/bash
# Shell script to create pull request.
timestamp=$(date +%s)
echo $timestamp
for (( i=1; i <= $1; ++i ))
do
git checkout develop
git pull
git branch
branch=feature-branch/$timestamp-$i
git checkout -b $branch
git commit -m "Auto generated PR: $branch"
git push origin $branch:$branch
git request-pull develop origin
done
exit 0
