---
description: Split-aware version bump + changelog + two-zip build, then commit & sync (Bricks Static Free + Pro monorepo)
---

This repo ships **two** plugins from one codebase:
- **Free** — `bricks-static.php` (`BS_VERSION`), wp.org. Changelog: `CHANGELOG.md`.
- **Pro** — `pro/bricks-static-pro.php` (`BSP_VERSION`), Fluent Cart. Changelog: `pro/CHANGELOG.md`.

Bump only the side(s) whose **shipped** code changed. Each side has its own
independent version; they are NOT kept in lock-step. Do the following:

### 1. Determine which side(s) changed
Run `git status --porcelain` and `git diff --name-only HEAD` and classify the changed paths:

- **Free** changed if any of: `bricks-static.php`, `src/**`, `src-svelte/dashboard/**`, `src-svelte/frontend/**`, `src-svelte/docs/**`, `assets/css/**`, `uninstall.php`, `README.md`.
- **Pro** changed if any of: `pro/**` (excluding `pro/CHANGELOG*` and `pro/assets/dist/**` build output).
- **BOTH** changed if any of: `src-svelte/shared/**` or `src-svelte/lib/**`. ⚠️ These compile into BOTH bundles (Pro imports them across the namespace boundary), so a change here ships in both plugins and must bump both.

Build output (`assets/dist/**`, `pro/assets/dist/**`), `node_modules/`, `dist/`, `vendor/`, and `.md` changelog files do NOT by themselves trigger a bump.

If nothing in the shipped surfaces changed, say so and skip the bump.

### 2. Bump the affected side(s) by 0.0.1 (preserve any `-beta`/pre-release suffix)
- **Free**: increment `Version:` in the `bricks-static.php` header AND the `BS_VERSION` define to match. If `package.json` exists, set its `version` to the Free version (drop the `-beta` suffix if package.json uses bare semver).
- **Pro**: increment `Version:` in the `pro/bricks-static-pro.php` header AND the `BSP_VERSION` define to match.

### 3. `BSP_MIN_FREE` — do NOT auto-bump
`BSP_MIN_FREE` (in `pro/bricks-static-pro.php`) is the compatibility contract: the minimum Free version Pro needs. Only raise it when this change makes Pro depend on a **new Free seam/hook** that didn't exist before. If you bumped Free in this run AND Pro now calls a new Free seam, set `BSP_MIN_FREE` to the new Free version — but PAUSE and confirm with the user first; never raise it silently.

### 4. Update the changelog(s) — separately, only for bumped side(s)
- **Free** bumped → add the new version to `CHANGELOG.md` with notes on Free-facing fixes/changes/features.
- **Pro** bumped → add the new version to `pro/CHANGELOG.md` with notes on Pro-facing changes. Create `pro/CHANGELOG.md` if it doesn't exist.
- Keep entries scoped: Free-only changes never appear in the Pro changelog and vice-versa. A shared change is described from each product's perspective in both.
- Run the **project-local** `/user-changelog` to regenerate the user-facing snapshot(s) for the bumped side(s): Free → `CHANGELOG_FREE_VERSION.md`/`.html` at the repo root; Pro → `pro/CHANGELOG_PRO_VERSION.md`/`.html`. Each overwrites in full with just the latest release (there are no cumulative `CHANGELOG_USER.*` files in this repo).

### 5. CLAUDE.md
If the change introduces a new pattern/convention worth recording, add a short note to `CLAUDE.md`.

### 6. Build the distributable zips
Run `npm run zip` (this runs `npm run build` for both bundles, enforces the "no Pro code in Free" guard, and writes version-stamped zips `dist/bricks-static-<ver>.zip` + `dist/bricks-static-pro-<ver>.zip`). Do NOT use the old single `/zip-plugin`. If the build or the guard fails, STOP and report — do not commit a broken build.

### 7. Commit & sync
- Stage and commit with a short, meaningful message that names the side(s) bumped and the new version(s), e.g. `Free 0.0.5-beta: fix single-page modal cap` or `Pro 0.0.5-beta + Free 0.0.5-beta: shared lib update`.
- Push/sync to the remote. If on the default branch and the repo convention requires a branch/PR, follow that instead of pushing directly.

Report which side(s) were bumped, the new version number(s), the changelog files touched, and the zip paths.
