#!/bin/bash
# WordPress Sync Manager - Safe upstream sync workflow
# Ensures our custom changes are never overwritten

set -e

REPO_DIR="/home/gsxrchris/.openclaw/workspace/wordpress-develop"
cd "$REPO_DIR"

echo "=========================================="
echo "WordPress AI Security Edition Sync Check"
echo "=========================================="
echo ""

# Check for uncommitted changes
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "❌ ERROR: You have uncommitted changes!"
    echo "Please commit or stash them before syncing."
    exit 1
fi

# Fetch upstream
echo "Fetching upstream..."
git fetch upstream

# Compare commits
LOCAL=$(git rev-parse trunk)
UPSTREAM=$(git rev-parse upstream/trunk)

if [ "$LOCAL" = "$UPSTREAM" ]; then
    echo "✅ Already in sync with upstream"
    echo "Commit: $LOCAL"
    exit 0
fi

COMMITS_BEHIND=$(git rev-list --count trunk..upstream/trunk)
echo "⚠️  Upstream has $COMMITS_BEHIND new commit(s)"
echo ""

# Show what we're about to merge
echo "=== Upstream commits to merge ==="
git log --oneline trunk..upstream/trunk
echo ""

# Show files that will be changed
echo "=== Files that will be updated ==="
git diff --stat trunk...upstream/trunk
echo ""

echo "=========================================="
echo "ACTION REQUIRED: Review above changes"
echo "=========================================="
echo ""
echo "To merge these changes, run:"
echo "  cd $REPO_DIR"
echo "  git merge upstream/trunk --no-ff"
echo ""
echo "After merging, run analysis:"
echo "  vendor/bin/phpstan analyze"
echo "  vendor/bin/php-cs-fixer fix --dry-run"
echo ""
echo "Then push to origin/trunk after review"