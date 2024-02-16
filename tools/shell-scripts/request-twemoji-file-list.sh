#!/bin/bash

# Use a GitHub CLI API call to use graphql to get a list of file names in https://github.com/jdecked/twemoji/tree/main/assets/svg
# This is a list of all the twemoji svg files

gh api graphql -f query='{repository(owner: "jdecked", name: "twemoji") {object(expression: "main:assets/svg") {... on Tree {entries {name}}}}}'
