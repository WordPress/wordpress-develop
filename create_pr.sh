#!/bin/bash
git checkout develop
git pull
git branch
timestamp=$(date +%s)
echo $timestamp
git checkout -b temp/$timestamp
#echo "Auto generated PR: $timestamp" > $PWD/temp/$timestamp
#git add temp/$timestamp
git commit message "Auto generated PR: $timestamp"
git push origin temp/$timestamp
#git log