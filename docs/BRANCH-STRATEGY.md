# Branch Strategy

## Branches

| Branch | Purpose | Protected? |
|--------|---------|------------|
| `trunk` | Active development — all our AI security work | ✅ Yes |
| `main` | Stable/production-ready — only synced from trunk | ✅ Yes |

## Workflow

```
upstream/wordpress-develop (official)
         ↓ (sync)
    trunk (our dev work)
         ↓ (after testing & approval)
    main (frozen/push to production)
```

## Making Changes

1. **Create a branch from trunk** (for any feature/fix):
   ```bash
   git checkout -b feature/your-feature-name trunk
   ```

2. **Work on your branch** — commit changes

3. **Open a PR to trunk** — for review

4. **After approval** — merge to trunk

5. **When ready for production** — PR from trunk to main

## Key Rules

- ❌ Never push directly to `main`
- ❌ Never commit AI security features directly to `main`
- ✅ All changes start as a branch off `trunk`
- ✅ PRs required to merge to `trunk`
- ✅ After thorough testing, PR from `trunk` to `main`

## Syncing Upstream

1. Sync to `trunk` first (from upstream/trunk)
2. Test/verify changes
3. Later, merge `trunk` to `main` when stable