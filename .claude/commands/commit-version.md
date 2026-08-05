---
description: Version bump + changelog + zip build, then commit & sync (Bricks Static)
---

Bricks Static ships as a single free plugin (`bricks-static.php`, `BS_VERSION`), wp.org. Changelog: `CHANGELOG.md`.

Do the following:

### 1. Determine whether a bump is warranted
Run `git status --porcelain` and `git diff --name-only HEAD` to see what changed.

Build output (`assets/dist/**`), `node_modules/`, `dist/`, `vendor/`, and `.md` changelog files do NOT by themselves trigger a bump.

If nothing in the shipped surfaces (`bricks-static.php`, `src/**`, `src-svelte/**`, `uninstall.php`, `README.md`) changed, say so and skip the bump.

### 2. Bump the version by 0.0.1 (preserve any `-beta`/pre-release suffix)
Increment `Version:` in the `bricks-static.php` header AND the `BS_VERSION` define to match. If `package.json` exists, set its `version` to match (drop the `-beta` suffix if package.json uses bare semver).

### 3. Update the changelog
- Add the new version to `CHANGELOG.md` with notes on what changed.
- Run the **project-local** `/user-changelog` to regenerate the user-facing snapshot: `CHANGELOG_FREE_VERSION.md`/`.html` at the repo root (overwrites in full with just the latest release — there is no cumulative `CHANGELOG_USER.*` file in this repo).

### 4. CLAUDE.md
If the change introduces a new pattern/convention worth recording, add a short note to `CLAUDE.md`.

### 5. Build the distributable zip
Run `npm run zip` (this runs `npm run build`, then stages the allowlisted files and writes the version-stamped zip `dist/bricks-static-<ver>.zip`). Do NOT use the old single `/zip-plugin`. If the build fails, STOP and report — do not commit a broken build.

### 6. Commit & sync
- Stage and commit with a short, meaningful message naming the new version, e.g. `2.0.1: fix single-page modal styling`.
- Push/sync to the remote. If on the default branch and the repo convention requires a branch/PR, follow that instead of pushing directly.

Report the new version number, the changelog files touched, and the zip path.
