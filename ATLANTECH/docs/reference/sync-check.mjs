#!/usr/bin/env node
// Compares docs/reference with the team's local documents folder (the graduation project folder).
//
//   node docs/reference/sync-check.mjs            report only
//   node docs/reference/sync-check.mjs --apply    copy changed local files into docs/reference, update the manifest
//                                                 and rebuild the index (index/index.json)
//
// The local folder is read from ATLANTECH_DOCS_ROOT, falling back to manifest.sourceRoot.
// Exit code: 0 in sync, 1 differences found or index stale (report mode), 2 local folder not available.
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const manifestPath = path.join(here, 'manifest.json');
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const root = process.env.ATLANTECH_DOCS_ROOT || manifest.sourceRoot;
const apply = process.argv.includes('--apply');
const sha1 = f => crypto.createHash('sha1').update(fs.readFileSync(f)).digest('hex');
const sha1Of = buf => crypto.createHash('sha1').update(buf).digest('hex');

/** Runs build-index.mjs (rebuild, or --check) and returns true when it succeeded. */
function index(check) {
  const r = spawnSync(process.execPath, [path.join(here, 'build-index.mjs'), ...(check ? ['--check'] : [])], { stdio: 'inherit' });
  return r.status === 0;
}

// "extract": "roadmap" pulls the task data (const D = {...}) out of the progress tracker page as JSON.
function readSource(f, src) {
  const buf = fs.readFileSync(src);
  if (f.extract !== 'roadmap') return buf;
  const line = buf.toString('utf8').split('\n').find(l => l.startsWith('const D = '));
  if (!line) throw new Error(`No "const D = " line in ${f.source}`);
  return Buffer.from(JSON.stringify(JSON.parse(line.slice(10).replace(/;\s*$/, '')), null, 1) + '\n');
}

if (!root || !fs.existsSync(root)) {
  console.log(`Local docs folder not found (${root || 'unset'}). Set ATLANTECH_DOCS_ROOT to check for local changes.`);
  index(true);
  process.exit(2);
}

const changed = [], missingLocal = [], same = [];
for (const f of manifest.files) {
  const src = path.join(root, f.source);
  const dst = path.join(here, f.path);
  if (!fs.existsSync(src)) { missingLocal.push(f); continue; }
  const content = readSource(f, src);
  const s = sha1Of(content);
  const repoSha = fs.existsSync(dst) ? sha1(dst) : null;
  if (s === repoSha) { same.push(f); continue; }
  changed.push({ ...f, content, localSha: s, repoSha, localDate: fs.statSync(src).mtime.toISOString().slice(0, 16).replace('T', ' ') });
}

// Documents in the watched local folders that the manifest does not track yet
const tracked = new Set([...manifest.files.map(f => f.source), ...(manifest.ignore || [])].map(s => s.replace(/\\/g, '/')));
const untracked = [];
for (const dir of manifest.watch || []) {
  const abs = path.join(root, dir);
  if (!fs.existsSync(abs)) continue;
  (function walk(d) {
    for (const e of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) walk(p);
      else if (/\.(md|sql|pdf)$/i.test(e.name)) {
        const rel = path.relative(root, p).replace(/\\/g, '/');
        if (!tracked.has(rel)) untracked.push(rel);
      }
    }
  })(abs);
}

console.log(`Local docs folder: ${root}`);
console.log(`In sync: ${same.length}  Changed locally: ${changed.length}  Missing locally: ${missingLocal.length}  Untracked local docs: ${untracked.length}`);
for (const f of changed) console.log(`  CHANGED   ${f.path}  <-  ${f.source}  (local ${f.localDate}${f.repoSha ? '' : ', not in repo yet'})`);
for (const f of missingLocal) console.log(`  MISSING   ${f.source}  (repo copy kept: ${f.path})`);
for (const r of untracked) console.log(`  UNTRACKED ${r}  (add it to manifest.json if the backend needs it)`);

if (apply) {
  if (changed.length) {
    for (const f of changed) {
      const dst = path.join(here, f.path);
      fs.mkdirSync(path.dirname(dst), { recursive: true });
      fs.writeFileSync(dst, f.content);
      manifest.files.find(m => m.path === f.path).sha1 = f.localSha;
    }
    manifest.syncedAt = new Date().toISOString().slice(0, 10);
    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + '\n');
    console.log(`Copied ${changed.length} file(s) into docs/reference and updated manifest.json.`);
  }
  const built = index(false);
  console.log('Review the diff (documents and index) and commit it.');
  process.exit(built ? 0 : 1);
}
const indexOk = index(true);
process.exit(changed.length || !indexOk ? 1 : 0);
