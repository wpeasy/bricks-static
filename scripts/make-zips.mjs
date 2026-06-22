/**
 * Build the two distributable zips from this single repo (filenames carry each
 * plugin's version; the folder INSIDE each zip stays the bare slug):
 *   dist/bricks-static-<version>.zip       — the FREE plugin (wp.org)
 *   dist/bricks-static-pro-<version>.zip   — the PRO addon (Fluent Cart)
 *
 * The Free zip is assembled from an explicit ALLOWLIST (never a denylist) so
 * `pro/` and source trees can never leak, and a guard fails the build if any
 * staged Free PHP references the Pro namespace — enforcing wp.org's "no dormant
 * paid code" rule. Run `npm run build` first (this script also runs it).
 *
 * Usage: npm run zip                           (build + stage + zip)
 *        node scripts/make-zips.mjs --no-build (skip the vite build)
 */

import { execSync } from 'node:child_process';
import {
  rmSync, mkdirSync, cpSync, existsSync, readdirSync, statSync, readFileSync,
} from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DIST = join(ROOT, 'dist');

// Slug => { folder name inside the zip, allowlisted relative paths }.
const FREE = {
  zip: 'bricks-static',
  allow: [
    'bricks-static.php',
    'uninstall.php',
    'readme.txt',
    'src',
    'assets/css',
    'assets/dist',
    'templates',
    'languages',
    'vendor',
  ],
};

// Pro paths are relative to `pro/`; staged under the pro plugin folder.
const PRO = {
  zip: 'bricks-static-pro',
  base: 'pro',
  allow: [
    'bricks-static-pro.php',
    'src',
    'assets/dist',
    'templates',
    'languages',
  ],
};

function run(cmd) {
  execSync(cmd, { cwd: ROOT, stdio: 'inherit' });
}

/** Read a plugin's version from its main file's define('CONST', 'x.y.z'). */
function pluginVersion(file, constName) {
  const m = readFileSync(file, 'utf8').match(
    new RegExp(`define\\(\\s*['"]${constName}['"]\\s*,\\s*['"]([^'"]+)['"]`),
  );
  if (!m) {
    console.error(`✗ Could not read ${constName} from ${file}`);
    process.exit(1);
  }
  return m[1];
}

/** Recursively collect files under dir matching a predicate. */
function walk(dir, pred, out = []) {
  if (!existsSync(dir)) return out;
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) walk(p, pred, out);
    else if (pred(p)) out.push(p);
  }
  return out;
}

/** Copy allowlisted paths into a staging folder. */
function stage(srcBase, allow, destFolder) {
  rmSync(destFolder, { recursive: true, force: true });
  mkdirSync(destFolder, { recursive: true });
  for (const rel of allow) {
    const src = join(srcBase, rel);
    if (!existsSync(src)) continue;
    const dest = join(destFolder, rel);
    mkdirSync(dirname(dest), { recursive: true });
    cpSync(src, dest, { recursive: true });
  }
}

/** Fail the build if any staged PHP references the Pro namespace. */
function guardNoPro(stagedFolder) {
  const offenders = walk(stagedFolder, (p) => p.endsWith('.php')).filter((p) =>
    readFileSync(p, 'utf8').includes('BricksStaticPro'),
  );
  if (offenders.length) {
    console.error('\n✗ FREE zip contains Pro code:\n' + offenders.join('\n'));
    process.exit(1);
  }
}

/** Zip a staged folder into dist/<name>.zip (cross-platform). */
function zip(stagedParent, folderName, outName) {
  const out = join(DIST, `${outName}.zip`);
  rmSync(out, { force: true });
  if (process.platform === 'win32') {
    // Archive the FOLDER itself (no trailing /*) so the zip wraps everything in
    // a single <slug>/ directory, as WordPress requires.
    const src = join(stagedParent, folderName);
    run(`powershell -NoProfile -Command "Compress-Archive -Path '${src}' -DestinationPath '${out}' -Force"`);
  } else {
    execSync(`cd "${stagedParent}" && zip -rq "${out}" "${folderName}"`, { stdio: 'inherit' });
  }
  console.log(`  → ${out}`);
}

// 1. Build both bundles unless skipped.
if (!process.argv.includes('--no-build')) {
  console.log('• Building Free + Pro bundles…');
  run('npm run build');
}
for (const [label, p] of [['Free', 'assets/dist/.vite/manifest.json'], ['Pro', 'pro/assets/dist/.vite/manifest.json']]) {
  if (!existsSync(join(ROOT, p))) {
    console.error(`✗ Missing ${label} build manifest (${p}). Run "npm run build".`);
    process.exit(1);
  }
}

rmSync(DIST, { recursive: true, force: true });
mkdirSync(DIST, { recursive: true });

// 2. FREE — allowlist stage, guard, zip.
console.log('• Staging FREE…');
const freeVer = pluginVersion(join(ROOT, 'bricks-static.php'), 'BS_VERSION');
const freeStage = join(DIST, '_stage_free');
stage(ROOT, FREE.allow, join(freeStage, FREE.zip));
guardNoPro(join(freeStage, FREE.zip));
zip(freeStage, FREE.zip, `${FREE.zip}-${freeVer}`);

// 3. PRO — stage pro/ only, zip.
console.log('• Staging PRO…');
const proVer = pluginVersion(join(ROOT, PRO.base, 'bricks-static-pro.php'), 'BSP_VERSION');
const proStage = join(DIST, '_stage_pro');
stage(join(ROOT, PRO.base), PRO.allow, join(proStage, PRO.zip));
zip(proStage, PRO.zip, `${PRO.zip}-${proVer}`);

// 4. Clean staging.
rmSync(freeStage, { recursive: true, force: true });
rmSync(proStage, { recursive: true, force: true });

console.log('\n✓ Done. Zips in dist/.');
