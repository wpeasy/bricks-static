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
  rmSync, mkdirSync, cpSync, existsSync, readFileSync, readdirSync, writeFileSync, statSync,
} from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { deflateRawSync, crc32 as zlibCrc32 } from 'node:zlib';

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

// Unix permissions stamped into the archive. WordPress plugin payloads are data,
// never executables, so files are 644 and directories 755.
const MODE_FILE = 0o644;
const MODE_DIR = 0o755;

/** CRC-32, using node:zlib's native one when present (Node >= 20.15). */
const crc32 = typeof zlibCrc32 === 'function'
  ? (buf) => zlibCrc32(buf) >>> 0
  : (() => {
    const table = Array.from({ length: 256 }, (_, n) => {
      let c = n;
      for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
      return c >>> 0;
    });
    return (buf) => {
      let c = 0xffffffff;
      for (let i = 0; i < buf.length; i++) c = table[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
      return (c ^ 0xffffffff) >>> 0;
    };
  })();

/** Pack a Date into the DOS time/date pair the zip format stores. */
function dosStamp(d) {
  if (d.getFullYear() < 1980) return { time: 0, date: 0x21 };
  return {
    time: (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1),
    date: ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate(),
  };
}

/** Depth-first walk yielding {name, isDir, data, mtime}, parents before children. */
function collect(base, prefix, out = []) {
  for (const entry of readdirSync(base, { withFileTypes: true }).sort((a, b) => (a.name < b.name ? -1 : 1))) {
    const abs = join(base, entry.name);
    // Zip paths are ALWAYS forward-slashed, whatever the host separator is.
    const name = `${prefix}${entry.name}`;
    if (entry.isDirectory()) {
      out.push({ name: `${name}/`, isDir: true, mtime: statSync(abs).mtime });
      collect(abs, `${name}/`, out);
    } else if (entry.isFile()) {
      out.push({ name, isDir: false, data: readFileSync(abs), mtime: statSync(abs).mtime });
    }
  }
  return out;
}

/**
 * Zip a staged folder into dist/<name>.zip.
 *
 * Written by hand rather than shelled out to, because the archive has to carry
 * Unix permission bits: `Compress-Archive` (and .NET's ZipFile) mark every entry
 * as MS-DOS with external attributes of 0, so on Linux the extracted directories
 * land with no execute bit and nothing inside them can be traversed — the plugin
 * unpacks but reads as broken. We stamp "made by Unix" plus explicit 644/755 so
 * the same zip installs correctly on Linux, macOS and Windows hosts alike.
 */
function zip(stagedParent, folderName, outName) {
  const out = join(DIST, `${outName}.zip`);
  rmSync(out, { force: true });

  // Archive the FOLDER itself so the zip wraps everything in a single <slug>/
  // directory, as WordPress requires.
  const root = statSync(join(stagedParent, folderName));
  const entries = [
    { name: `${folderName}/`, isDir: true, mtime: root.mtime },
    ...collect(join(stagedParent, folderName), `${folderName}/`),
  ];

  const locals = [];
  const centrals = [];
  let offset = 0;

  for (const entry of entries) {
    const nameBuf = Buffer.from(entry.name, 'utf8');
    const raw = entry.isDir ? Buffer.alloc(0) : entry.data;
    const deflated = entry.isDir ? Buffer.alloc(0) : deflateRawSync(raw, { level: 9 });
    // Only pay for compression when it actually pays off.
    const stored = !entry.isDir && deflated.length >= raw.length;
    const body = entry.isDir || stored ? raw : deflated;
    const method = entry.isDir || stored ? 0 : 8;
    const { time, date } = dosStamp(entry.mtime);
    const sum = entry.isDir ? 0 : crc32(raw);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0, 6);
    local.writeUInt16LE(method, 8);
    local.writeUInt16LE(time, 10);
    local.writeUInt16LE(date, 12);
    local.writeUInt32LE(sum, 14);
    local.writeUInt32LE(body.length, 18);
    local.writeUInt32LE(raw.length, 22);
    local.writeUInt16LE(nameBuf.length, 26);
    local.writeUInt16LE(0, 28);
    locals.push(local, nameBuf, body);

    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    // High byte 3 = Unix, so readers honour the mode in the external attributes.
    central.writeUInt16LE((3 << 8) | 20, 4);
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0, 8);
    central.writeUInt16LE(method, 10);
    central.writeUInt16LE(time, 12);
    central.writeUInt16LE(date, 14);
    central.writeUInt32LE(sum, 16);
    central.writeUInt32LE(body.length, 20);
    central.writeUInt32LE(raw.length, 24);
    central.writeUInt16LE(nameBuf.length, 28);
    central.writeUInt16LE(0, 30);
    central.writeUInt16LE(0, 32);
    central.writeUInt16LE(0, 34);
    central.writeUInt16LE(0, 36);
    // Unix mode in the high 16 bits; low bit 0x10 is the MS-DOS directory flag.
    const mode = entry.isDir ? MODE_DIR | 0o040000 : MODE_FILE | 0o100000;
    // The trailing >>> 0 matters: `|` would resign the value back to int32.
    central.writeUInt32LE(((mode << 16) | (entry.isDir ? 0x10 : 0)) >>> 0, 38);
    central.writeUInt32LE(offset, 42);
    centrals.push(central, nameBuf);

    offset += local.length + nameBuf.length + body.length;
  }

  const cd = Buffer.concat(centrals);
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(0, 4);
  eocd.writeUInt16LE(0, 6);
  eocd.writeUInt16LE(entries.length, 8);
  eocd.writeUInt16LE(entries.length, 10);
  eocd.writeUInt32LE(cd.length, 12);
  eocd.writeUInt32LE(offset, 16);
  eocd.writeUInt16LE(0, 20);

  writeFileSync(out, Buffer.concat([...locals, cd, eocd]));
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
