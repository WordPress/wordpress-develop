#!/bin/bash
# Shell script to create pull request.
# -----------------------------------------------
#
# Usage: ./create-pr.sh arg1 arg2
# arg1 = Number of instance to be run in parallel
# arg2 = Number of PR per instance
#
#-------------------------------------------------
timestamp=$(date +%s)
echo $timestamp

# Remove old feature branches
git checkout develop
git pull
git branch --list 'feature-branch*' | xargs git branch -d

create_pr()
{
# Create shell script to be execute
cat <<'EOF'>> /tmp/$1-$2.sh
#!/bin/bash 
for (( j=1; j <= $2; ++j ))
do
#git checkout develop
#git pull
branch=feature-branch/$1-$j
git checkout -b $branch
git commit -m "Auto generated PR: $branch"
git push origin $branch:$branch
git request-pull develop origin
done
exit 0
EOF

# Execute the shell script to create PR
chmod +x /tmp/$1-$2.sh
nohup /tmp/$1-$2.sh $1+$2 $3 >/dev/null 2>&1
    

}

# Make shell scripts to create PRs 
for (( i=1; i <= $1; ++i ))
do
    create_pr $timestamp $i $2
    
done
exit 0