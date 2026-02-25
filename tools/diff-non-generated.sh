#!/usr/bin/env sh

# Generate a list of all files not marked as auto-generated, and produce a
# pathspec for the `git diff` command so it skips over them.
#
# Example:
#
#     ":!/src/wp-includes/assets/script-loader-packages.min.php" ":!/src/..."
GENERATED=$(cat .gitattributes | grep "diff=generated" | cut -w -f1 | sed 's/^\(.*\)$/":!\1"/g' | paste -sd ' ' -)

# This ENV cannot be passed directly afte the "--", so generate the command.
CMD="git diff --exit-code -- $GENERATED"

eval $CMD
