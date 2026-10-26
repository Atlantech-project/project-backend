#!/usr/bin/env node
// Compares docs/reference with the team's local documents folder (the graduation project folder).
//
//   node docs/reference/sync-check.mjs            report only
//   node docs/reference/sync-check.mjs --apply    copy changed local files into docs/reference, update the manifest
//                                                 and rebuild the index (index/index.json)
//   node docs/reference/sync-check.mjs --mirrors  only refresh the skill and AGENTS.md copies for other agents
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

// --mirrors: refresh only the copies for other agents, without touching documents or the index.
if (process.argv.includes('--mirrors')) {
  const refreshed = mirrors(true); // function declaration below, hoisted
  console.log(refreshed.length ? `Refreshed copies for other agents: ${refreshed.join(', ')}` : 'Copies for other agents already match.');
  process.exit(0);
}
const sha1 = f => crypto.createHash('sha1').update(fs.readFileSync(f)).digest('hex');
const sha1Of = buf => crypto.createHash('sha1').update(buf).digest('hex');

/**
 * Our own skills (every skill in .claude/skills that is not one of Laravel Boost's) are mirrored to
 * .agents/skills and .junie/skills for teammates on other tools, and the "ATLANTECH workflow" section
 * of CLAUDE.md is mirrored into AGENTS.md. Returns the list of out-of-date copies; refreshes them when fix is true.
 */
function mirrors(fix) {
  const app = path.resolve(here, '..', '..');
  const boost = JSON.parse(fs.readFileSync(path.join(app, 'boost.json'), 'utf8')).skills || [];
  const skillsDir = path.join(app, '.claude', 'skills');
  const ours = fs.readdirSync(skillsDir).filter(s => !boost.includes(s) && fs.statSync(path.join(skillsDir, s)).isDirectory());
  const files = d => fs.readdirSync(d, { recursive: true }).filter(p => fs.statSync(path.join(d, p)).isFile()).map(p => p.replace(/\\/g, '/'));
  const stale = [];
  for (const target of ['.agents/skills', '.junie/skills']) {
    for (const skill of ours) {
      const from = path.join(skillsDir, skill), to = path.join(app, target, skill);
      const differs = !fs.existsSync(to) || files(from).join() !== files(to).join() ||
        files(from).some(p => fs.readFileSync(path.join(from, p), 'utf8') !== fs.readFileSync(path.join(to, p), 'utf8'));
      if (!differs) continue;
      stale.push(`${target}/${skill}`);
      if (fix) { fs.rmSync(to, { recursive: true, force: true }); fs.cpSync(from, to, { recursive: true }); }
    }
  }
  const marker = '\n# ATLANTECH workflow\n';
  const claude = fs.readFileSync(path.join(app, 'CLAUDE.md'), 'utf8');
  const agentsPath = path.join(app, 'AGENTS.md'), agents = fs.readFileSync(agentsPath, 'utf8');
  const section = claude.includes(marker) ? claude.slice(claude.indexOf(marker)) : '';
  const agentsSection = agents.includes(marker) ? agents.slice(agents.indexOf(marker)) : '';
  if (section && section !== agentsSection) {
    stale.push('AGENTS.md workflow section');
    if (fix) fs.writeFileSync(agentsPath, (agents.includes(marker) ? agents.slice(0, agents.indexOf(marker)) : agents.replace(/\n*$/, '\n')) + section);
  }
  return stale;
}

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
  const staleMirrors = mirrors(apply);
  if (staleMirrors.length) console.log(`${apply ? 'Refreshed' : 'Out of date (run with --apply)'}: ${staleMirrors.join(', ')}`);
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
      else if (/\.(md|sql|pdf|json)$/i.test(e.name)) {
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
  const refreshed = mirrors(true);
  if (refreshed.length) console.log(`Refreshed copies for other agents: ${refreshed.join(', ')}`);
  console.log('Review the diff (documents, index and copies) and commit it.');
  process.exit(built ? 0 : 1);
}
const indexOk = index(true);
const staleMirrors = mirrors(false);
if (staleMirrors.length) console.log(`Copies for other agents out of date (run with --apply): ${staleMirrors.join(', ')}`);
else console.log('Copies for other agents (.agents, .junie, AGENTS.md) match.');
process.exit(changed.length || !indexOk || staleMirrors.length ? 1 : 0);
