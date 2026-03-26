# WordPress Sync Workflow

## Overview

This document defines the workflow for keeping WordPress AI Security Edition in sync with upstream WordPress core without losing our custom changes.

## Remotes

| Remote | URL | Purpose |
|--------|-----|---------|
| `origin` | `https://github.com/cbuntingde/wordpress-develop.git` | Our fork (our changes) |
| `upstream` | `https://github.com/WordPress/wordpress-develop.git` | Official WordPress |

## Branch Strategy

- **Working Branch:** `trunk` — all our work happens here
- **Sync Process:** never overwrite — always merge, never rebase onto unstable

## Sync Checklist (Before Any Merge)

1. ✅ **Check uncommitted changes** — must be committed or stashed
2. ✅ **Fetch upstream** — `git fetch upstream`
3. ✅ **Compare commits** — see what's new
4. ✅ **Review changes** — inspect files that will be updated
5. ✅ **Merge with review** — `git merge upstream/trunk --no-ff`
6. ✅ **Run code quality checks** — phpstan + php-cs-fixer
7. ✅ **Test if needed** — npm test, phpunit
8. ✅ **Push to origin** — only after verification

## Automated Sync Check

- **Job:** `wordpress-sync-check` — runs every 3 hours
- **Location:** `scripts/sync-check.sh`
- **Action:** Reports to chat when upstream has new commits

## When Upstream Has Updates

1. I notify you in chat with:
   - Number of commits behind
   - List of upstream commits
   - Files that will change

2. You decide:
   - **Approve sync** — I merge and we review the result
   - **Defer** — wait for another time
   - **Investigate** — look at specific commits first

3. After merge:
   - Run PHPStan analysis
   - Run PHP CS Fixer (dry-run)
   - Verify nothing broke

4. Push to origin only after you approve

## Critical Rules

- ❌ **Never** rebase our trunk onto upstream (we lose history)
- ❌ **Never** force push to trunk (could lose work)
- ❌ **Never** merge without reviewing what's coming in
- ✅ **Always** use `--no-ff` when merging upstream
- ✅ **Always** run quality checks after any merge
- ✅ **Always** get your approval before pushing to origin

## Troubleshooting

### Merge conflicts
If there are conflicts, I will:
1. Show you which files have conflicts
2. Help you resolve them
3. Not push until resolved

### Breaking changes
If upstream changes break our AI security features:
1. I identify the issue
2. We fix before pushing
3. Document the conflict for future reference

## Quick Commands

```bash
# Manual sync check
cd ~/wordpress-develop && bash scripts/sync-check.sh

# Fetch & view upstream changes
git fetch upstream && git log trunk..upstream/trunk

# Merge (with review)
git merge upstream/trunk --no-ff
```