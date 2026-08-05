/**
 * Build the distributable zip from this repo:
 *   dist/bricks-static-<version>.zip
 *
 * Assembled from an explicit ALLOWLIST (never a denylist) so dev-only files
 * (node_modules, src-svelte, tests, etc.) can never leak. Run `npm run build`
 * first (this script also runs it).
 *
 * Usage: npm run zip                           (build + stage + zip)
 *        node scripts/make-zips.mjs --no-build (skip the vite build)
 */

import { execSync } from 'node:child_process';
import {
  rmSync, mkdirSync, cpSync, existsSync, readFileSync,
} from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DIST = join(ROOT, 'dist');

const PLUGIN = {
  zip: 'bricks-static',
  allow: [
    'bricks-static.php',
    'uninstall.php',
    'readme.txt',
    'src',
    'assets/dist',
    'templates',
    'languages',
    'vendor',
  ],
};

function run(cmd) {
  execSync(cmd, { cwd: ROOT, stdio: 'inherit' });
}

/** Read the plugin's version from its main file's define('CONST', 'x.y.z'). */
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

// 1. Build unless skipped.
if (!process.argv.includes('--no-build')) {
  console.log('• Building…');
  run('npm run build');
}
const manifestPath = 'assets/dist/.vite/manifest.json';
if (!existsSync(join(ROOT, manifestPath))) {
  console.error(`✗ Missing build manifest (${manifestPath}). Run "npm run build".`);
  process.exit(1);
}

rmSync(DIST, { recursive: true, force: true });
mkdirSync(DIST, { recursive: true });

// 2. Stage + zip.
console.log('• Staging…');
const version = pluginVersion(join(ROOT, 'bricks-static.php'), 'BS_VERSION');
const stageDir = join(DIST, '_stage');
stage(ROOT, PLUGIN.allow, join(stageDir, PLUGIN.zip));
zip(stageDir, PLUGIN.zip, `${PLUGIN.zip}-${version}`);

// 3. Clean staging.
rmSync(stageDir, { recursive: true, force: true });

console.log('\n✓ Done. Zip in dist/.');
