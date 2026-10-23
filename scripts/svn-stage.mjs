/**
 * Stage a wordpress.org SVN release from the built zip + .wordpress-org/ art:
 *
 *   <target>/trunk/          the plugin, exactly as in dist/shipstatic-<ver>.zip
 *   <target>/tags/<ver>/     the same files (readme's "Stable tag" points here)
 *   <target>/assets/         icon, banners, screenshots for the plugin page
 *
 * Usage:
 *   npm run zip && npm run svn              → fresh layout in dist/svn/ (to inspect)
 *   npm run svn -- ~/svn/shipstatic         → sync into an existing SVN checkout
 *                                             (svn co https://plugins.svn.wordpress.org/shipstatic …)
 *
 * In a checkout, `.svn/` is never touched, an existing tags/<ver>/ is never
 * overwritten (tags are releases — bump the version instead), and the svn
 * commands to review + commit are printed at the end. Nothing is committed:
 * every SVN commit publishes to wordpress.org immediately, so that step is
 * the developer's.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, cpSync, readdirSync, mkdtempSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SLUG = 'shipstatic';

// Plugin-page assets wordpress.org reads from /assets (banner.svg is only the
// banner's foreground layer, and _* files are render inputs — not published).
const ASSETS = [
  'icon.svg', 'icon-256x256.png', 'icon-128x128.png',
  'banner-1544x500.png', 'banner-772x250.png',
  'screenshot-1.png', 'screenshot-2.png', 'screenshot-3.png',
];

function fail(msg) {
  console.error('✗ ' + msg);
  process.exit(1);
}

function readField(file, re, what) {
  const m = readFileSync(file, 'utf8').match(re);
  if (!m) fail(`Could not read ${what} from ${file}`);
  return m[1].trim();
}

const version = readField(join(ROOT, `${SLUG}.php`), /define\(\s*['"]SHIPSTATIC_VERSION['"]\s*,\s*['"]([^'"]+)['"]/, 'SHIPSTATIC_VERSION');
const stable = readField(join(ROOT, 'readme.txt'), /^Stable tag:\s*(.+)$/m, 'Stable tag');
if (stable !== version) fail(`readme.txt "Stable tag: ${stable}" doesn't match the plugin version ${version}.`);

const zip = join(ROOT, 'dist', `${SLUG}-${version}.zip`);
if (!existsSync(zip)) fail(`${zip} not found — run \`npm run zip\` first.`);

const arg = process.argv[2];
const target = arg ? resolve(arg) : join(ROOT, 'dist', 'svn');
const isCheckout = existsSync(join(target, '.svn'));
if (arg && !isCheckout) fail(`${target} is not an SVN checkout (no .svn/). Check out first: svn co https://plugins.svn.wordpress.org/${SLUG} ${target}`);
if (!arg) rmSync(target, { recursive: true, force: true });

// Unpack the release zip once; trunk and the tag are both copies of it.
const unpacked = mkdtempSync(join(tmpdir(), `${SLUG}-svn-`));
execFileSync('unzip', ['-q', zip, '-d', unpacked]);
const plugin = join(unpacked, SLUG);
if (!existsSync(join(plugin, `${SLUG}.php`))) fail(`The zip has no ${SLUG}/${SLUG}.php at its root.`);

// Mirror a folder into place: deletions included, .svn/ left alone.
function mirror(from, to) {
  mkdirSync(to, { recursive: true });
  execFileSync('rsync', ['-a', '--delete', '--exclude=.svn', from.replace(/\/?$/, '/'), to.replace(/\/?$/, '/')]);
}

mirror(plugin, join(target, 'trunk'));

const tagDir = join(target, 'tags', version);
let tagNote = 'created';
if (existsSync(tagDir) && readdirSync(tagDir).some((f) => f !== '.svn')) {
  tagNote = 'ALREADY EXISTS — left untouched (bump the version for a new release)';
} else {
  mirror(plugin, tagDir);
}

const assetsDir = join(target, 'assets');
mkdirSync(assetsDir, { recursive: true });
for (const name of ASSETS) {
  const src = join(ROOT, '.wordpress-org', name);
  if (!existsSync(src)) fail(`.wordpress-org/${name} is missing — run \`npm run assets\` (and add the screenshots).`);
  cpSync(src, join(assetsDir, name));
}

rmSync(unpacked, { recursive: true, force: true });

const count = (dir) => Number(execFileSync('sh', ['-c', `find "${dir}" -type f -not -path '*/.svn/*' | wc -l`]).toString().trim());
console.log(`✓ Staged ShipStatic ${version} in ${target}`);
console.log(`    trunk/          ${count(join(target, 'trunk'))} files`);
console.log(`    tags/${version}/    ${tagNote}`);
console.log(`    assets/         ${ASSETS.length} files`);

if (isCheckout) {
  console.log(`
Review, then publish (each commit goes live on wordpress.org immediately):

  cd "${target}"
  svn add --force trunk tags assets
  svn status | awk '/^!/ {print $2}' | while read -r f; do svn delete "$f"; done
  svn propset svn:mime-type image/png assets/*.png
  svn propset svn:mime-type image/svg+xml assets/*.svg
  svn status
  svn commit -m "ShipStatic ${version}"
`);
} else {
  console.log(`
This is a preview layout. After approval:
  brew install subversion          # once
  svn co https://plugins.svn.wordpress.org/${SLUG} ~/svn/${SLUG}
  npm run svn -- ~/svn/${SLUG}     # sync into the checkout, prints the commit commands
`);
}
