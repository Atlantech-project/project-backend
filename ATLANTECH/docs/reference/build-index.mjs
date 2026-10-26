#!/usr/bin/env node
// Builds docs/reference/index/index.json: one node per ID found in the reference documents,
// with its title (verbatim), status where the source has one, the exact source file and line
// range, and its links to other IDs. Titles and pointers only: no summaries, so the original
// documents stay the only source for meaning.
//
//   node docs/reference/build-index.mjs           rebuild the index
//   node docs/reference/build-index.mjs --check   exit 1 if the index is stale or an ID used in the
//                                                 backend tasks does not resolve
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(here, 'index');
const outFile = path.join(outDir, 'index.json');
const splitDir = path.join(here, 'split');

const SOURCES = {
  tasks: 'backend/atlantech_backend_tasks.md',
  roadmap: 'roadmap/roadmap-tasks.json',
  contracts: 'contracts/ATLANTECH_Contracts.md',
  decisions: 'decisions/ATLANTECH_Decisions.md',
  aiDeps: 'ai/ATLANTECH_AI_BACKEND_DEPENDENCIES.md',
  aiTasks: 'ai/ATLANTECH_AI_TASKS.md',
  fr: 'requirements/Functional Requirements.md',
  nfr: 'requirements/Non Functional Requirements.md',
  states: 'uml/ATLANTECH_State_Diagrams.md',
  portSql: 'database/PortSync.sql',
  saasSql: 'database/SaaS.sql',
  portMd: 'database/PortSync.md',
  saasMd: 'database/SaaS.md',
  sequence: 'uml/ATLANTECH_Sequence_Diagrams.md',
  activity: 'uml/ATLANTECH_Activity_Diagrams.md',
  useCases: 'uml/ATLANTECH_Use_Case_Diagrams.md',
  umlContext: 'uml/UML_Context.md',
};

const read = rel => fs.readFileSync(path.join(here, rel), 'utf8');
const lines = rel => read(rel).split(/\r?\n/);
const sha1 = rel => crypto.createHash('sha1').update(fs.readFileSync(path.join(here, rel))).digest('hex');
const cells = line => line.split('|').slice(1, -1).map(c => c.trim());

const ID = String.raw`(?:P\d-[A-Z]\d+|CT-\d{2}|DEC-\d{2}|AIBD-\d{3}|AI-\d{3}|OD-\d{2}|D-\d{2}|FR-[A-Z]+-\d+|NFR-[A-Z]+-\d+|SM-\d+|AD-\d+|WF-\d+|FT-\d{2}|C-0\d)`;

// Split files: verbatim copies of one section each, so a reader opens a few lines instead of a large
// document. Each starts with a comment naming its source file and line range; the original stays the
// source of truth and the split files are regenerated with the index.
const splits = {};
/** Registers a split file made of verbatim line ranges ({ file, a, b } 1-based, inclusive). */
function addSplit(rel, parts, id) {
  const text = parts.map(({ file, a, b }) =>
    `<!-- Generated from docs/reference/${file} lines ${a}-${b} by build-index.mjs. Do not edit: edit the source and rebuild. -->\n` +
    lines(file).slice(a - 1, b).join('\n')).join('\n\n');
  splits[rel] = text + '\n';
  if (id && nodes[id]) nodes[id].split = 'split/' + rel;
}
const slug = s => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60);

/** All IDs mentioned in a piece of text, expanding "AIBD-001 to 007", "AIBD-002, 013", "NFR-PERF-1, 3" and "P2-D1-D3". */
function refs(text) {
  const found = new Set();
  const re = new RegExp(`\\b(${ID})((?:\\s*(?:,|to|–|-)\\s*[A-Z]?\\d{1,3}\\b)*)`, 'g');
  for (const m of text.matchAll(re)) {
    const [id, tail] = [m[1], m[2] || ''];
    found.add(id);
    const head = id.replace(/\d+$/, '');
    const width = id.match(/\d+$/)[0].length;
    const rangeTo = tail.match(/^\s*(?:to|–)\s*(\d+)/) || (/^P\d-[A-Z]\d+$/.test(id) && tail.match(/^-[A-Z](\d+)/));
    if (rangeTo) {
      for (let n = +id.match(/\d+$/)[0] + 1; n <= +rangeTo[1]; n++) found.add(head + String(n).padStart(width, '0'));
      continue;
    }
    for (const n of tail.matchAll(/,\s*(\d{1,3})\b/g)) found.add(head + String(+n[1]).padStart(width, '0'));
  }
  return [...found];
}

const nodes = {};
const missing = new Set();
function node(id, type) {
  nodes[id] ??= { type, title: '', src: [] };
  if (type && nodes[id].type !== type && !nodes[id].type.split('+').includes(type)) nodes[id].type += '+' + type;
  return nodes[id];
}
const src = (file, a, b = a) => ({ file, lines: a === b ? `${a}` : `${a}-${b}` });
function link(from, rel, to) {
  if (from === to) return;
  const n = nodes[from];
  ((n.out ??= {})[rel] ??= []).includes(to) || n.out[rel].push(to);
}
/** End line (1-based) of a section starting at index i, ending before the next heading matching re. */
function sectionEnd(ls, i, re) {
  let j = i + 1;
  while (j < ls.length && !re.test(ls[j])) j++;
  while (j > i + 1 && ls[j - 1].trim() === '') j--;
  return j;
}

// Backend tasks: one row per task, plus the D-nn integration decisions.
{
  const f = SOURCES.tasks, ls = lines(f);
  let phase = '', pack = '';
  ls.forEach((l, i) => {
    if (/^## Phase /.test(l)) phase = l.replace(/^## /, '');
    if (/^### [A-Z]\. /.test(l)) pack = l.replace(/^### /, '');
    const c = cells(l);
    if (c.length >= 6 && /^P\d-[A-Z]\d+$/.test(c[1])) {
      const n = node(c[1], 'task');
      Object.assign(n, { title: c[2], effort: c[3], phase, package: pack });
      n.src.push(src(f, i + 1));
      refs(c[4] + ' ' + c[5]).forEach(r => link(c[1], 'refs', r));
    }
    if (c.length >= 6 && /^D-\d{2}$/.test(c[0])) {
      const n = node(c[0], 'integration-decision');
      Object.assign(n, { title: c[1], needed_before: c[5] });
      n.src.push(src(f, i + 1));
      refs(c[1]).filter(r => r.startsWith('OD-')).forEach(r => link(c[0], 'same_as', r));
      refs(c[4]).forEach(r => link(c[0], 'blocks', r));
    }
  });
}

// Roadmap: every team's tasks, features and dependencies.
{
  const f = SOURCES.roadmap, ls = lines(f), D = JSON.parse(read(f));
  const lineOf = {};
  ls.forEach((l, i) => { const m = l.match(/^\s*"id": "([^"]+)"/); if (m && !lineOf[m[1]]) lineOf[m[1]] = i + 1; });
  for (const t of D.tasks) {
    const n = node(t.id, t.id.startsWith('DEC-') ? 'decision' : t.id.startsWith('CT-') ? 'contract' : 'task');
    n.title ||= t.title;
    Object.assign(n, { team: t.team, sprint: t.sprint, feature: t.feat });
    n.src.push(src(f, lineOf[t.id], lineOf[t.id] + 8));
    t.deps.forEach(d => link(t.id, 'depends_on', d));
  }
  for (const [id, title] of Object.entries(D.features)) Object.assign(node(id, 'feature'), { title });
}

// Contracts: summary table (owner, receiver, related decision) and one section per contract.
{
  const f = SOURCES.contracts, ls = lines(f);
  ls.forEach((l, i) => {
    const c = cells(l);
    if (c.length === 5 && /^CT-\d{2}$/.test(c[0])) {
      const n = node(c[0], 'contract');
      Object.assign(n, { title: c[1], from: c[2], to: c[3] });
      refs(c[4]).forEach(r => link(c[0], 'decided_by', r));
      const alias = c[1].match(/^(C-0\d)\b/);
      if (alias) { Object.assign(node(alias[1], 'payload'), { title: c[1] }); link(alias[1], 'same_as', c[0]); }
    }
    const h = l.match(/^## (CT-\d{2}) · (.+)$/);
    if (h) {
      const end = sectionEnd(ls, i, /^## /), n = node(h[1], 'contract');
      n.src.push(src(f, i + 1, end));
      for (let j = i; j < end; j++) { const r = cells(ls[j]); if (r[0] === 'Status') n.status = r[1]; }
      addSplit(`contracts/${h[1]}.md`, [{ file: f, a: i + 1, b: end }], h[1]);
    }
  });
  // CT-15 table ownership: table → writer, quoted from the contract.
  const start = ls.findIndex(l => l.startsWith('## CT-15'));
  for (let i = start; i < sectionEnd(ls, start, /^## /); i++) {
    const c = cells(ls[i]);
    if (c.length === 3 && c[0].startsWith('`')) {
      for (const t of c[0].match(/`([a-z_0-9]+)`/g).map(x => x.slice(1, -1))) {
        const n = node('table:' + t, 'table');
        n.written_by = { created_by: c[1], completed_by: c[2], source: src(f, i + 1) };
      }
    }
  }
}

// Decisions: summary row (owner, status, close by) and section (source, affects).
{
  const f = SOURCES.decisions, ls = lines(f);
  ls.forEach((l, i) => {
    const c = cells(l);
    if (c.length === 5 && /^DEC-\d{2}$/.test(c[0])) Object.assign(node(c[0], 'decision'), { title: c[1], owner: c[2], status: c[3], close_by: c[4] });
    const h = l.match(/^### (DEC-\d{2}) · (.+)$/);
    if (h) {
      const end = sectionEnd(ls, i, /^#{2,3} /);
      const n = node(h[1], 'decision');
      n.src.push(src(f, i + 1, end));
      for (let j = i; j < end; j++) {
        const r = cells(ls[j]);
        if (r[0] === 'Source') refs(r[1]).forEach(x => link(h[1], 'same_as', x));
        if (r[0] === 'Affects') refs(r[1]).forEach(x => link(h[1], 'affects', x));
        // A filled "Decision" row while the summary still says Open is flagged, not interpreted.
        if (r[0] === 'Decision') n.decision_recorded = !/^_+$/.test(r[1]);
      }
      addSplit(`decisions/${h[1]}.md`, [{ file: f, a: i + 1, b: end }], h[1]);
    }
  });
}

// AI-backend dependencies: summary table, detail paragraphs, open decisions, workflows.
{
  const f = SOURCES.aiDeps, ls = lines(f);
  ls.forEach((l, i) => {
    const c = cells(l);
    if (c.length === 6 && /^AIBD-\d{3}$/.test(c[0])) {
      const n = node(c[0], 'ai-dependency');
      Object.assign(n, { title: c[2], direction: c[1], required: c[3], spec_status: c[4] });
      n.src.push(src(f, i + 1));
      refs(c[5]).forEach(r => link(c[0], 'ai_tasks', r));
    }
    const d = l.match(/^\*\*(AIBD-\d{3})(?: \/ (AIBD-\d{3}))*/);
    if (d) refs(l.match(/^\*\*([^*]+)\*\*/)[1]).forEach(id => node(id, 'ai-dependency').src.push(src(f, i + 1)));
    if (c.length >= 10 && /^OD-\d{2}$/.test(c[1])) {
      const n = node(c[1], 'open-decision');
      Object.assign(n, { title: c[3], priority: c[0], blocking: c[9] });
      n.src.push(src(f, i + 1));
      refs(c[8]).forEach(r => link(c[1], 'ai_tasks', r));
    }
    const wf = c.length === 4 && c[0].match(/^(WF-\d+) (.+)$/);
    if (wf) { const n = node(wf[1], 'workflow'); n.title = wf[2]; n.src.push(src(f, i + 1)); }
  });
}

// One split file per AIBD: its summary row and its detail paragraph.
{
  const f = SOURCES.aiDeps;
  for (const [id, n] of Object.entries(nodes)) {
    if (!id.startsWith('AIBD-')) continue;
    addSplit(`ai-deps/${id}.md`, n.src.map(s => { const [a, b = a] = s.lines.split('-').map(Number); return { file: f, a, b }; }), id);
  }
}

// AI tasks: the main table only (first occurrence of each ID with a phase column).
{
  const f = SOURCES.aiTasks, ls = lines(f);
  ls.forEach((l, i) => {
    const c = cells(l);
    if (c.length === 7 && /^AI-\d{3}$/.test(c[0]) && /^P\d+$/.test(c[2]) && !nodes[c[0]]?.phase) {
      const n = node(c[0], 'task');
      Object.assign(n, { title: n.title || c[1], ai_title: c[1], phase: c[2], priority: c[3] });
      n.src.push(src(f, i + 1));
      refs(c[5]).forEach(r => link(c[0], 'depends_on', r));
    }
  });
}

// Functional and non-functional requirements (verbatim opening words as the title).
{
  const f = SOURCES.fr, ls = lines(f);
  ls.forEach((l, i) => {
    const m = l.match(/^\*\*(FR-[A-Z]+-\d+)\*\* · (P\d|FW) · (✔|✘) (.*)$/);
    if (m) {
      const n = node(m[1], 'requirement');
      Object.assign(n, { title: m[4].length > 100 ? m[4].slice(0, 100) + '…' : m[4], priority: m[2], mvp: m[3] === '✔' });
      n.src.push(src(f, i + 1));
    }
  });
}
{
  const f = SOURCES.nfr, ls = lines(f);
  ls.forEach((l, i) => {
    const c = cells(l);
    const m = c[0]?.match(/^\*\*(NFR-[A-Z]+-\d+)\*\*$/);
    // Each NFR is defined once and listed again in a traceability table; the first row holds the title.
    if (m) { const n = node(m[1], 'nfr'); n.title ||= c[1]; n.src.push(src(f, i + 1)); }
  });
}

// State machines.
{
  const f = SOURCES.states, ls = lines(f);
  ls.forEach((l, i) => {
    const m = l.match(/^### (SM-\d+) — (.+)$/);
    if (m) {
      const end = sectionEnd(ls, i, /^#{2,3} /), n = node(m[1], 'state-machine');
      n.title = m[2];
      n.src.push(src(f, i + 1, end));
      addSplit(`uml/state/${m[1]}.md`, [{ file: f, a: i + 1, b: end }], m[1]);
    }
  });
}

// Activity diagrams (AD-n), sequence diagrams (n.m), use-case diagrams and UML Context sections.
{
  const f = SOURCES.activity, ls = lines(f);
  ls.forEach((l, i) => {
    const m = l.match(/^### (AD-\d+) — (.+)$/);
    if (m) {
      const end = sectionEnd(ls, i, /^#{2,3} /), n = node(m[1], 'activity');
      n.title = m[2];
      n.src.push(src(f, i + 1, end));
      addSplit(`uml/activity/${m[1]}.md`, [{ file: f, a: i + 1, b: end }], m[1]);
    }
  });
}
for (const [f, re, prefix, dir, stop] of [
  [SOURCES.sequence, /^### (\d+\.\d+) (.+)$/, 'SEQ-', 'uml/sequence', /^#{2,3} /],
  [SOURCES.useCases, /^## Diagram (\d+): (.+)$/, 'UCD-', 'uml/use-case', /^## /],
  [SOURCES.umlContext, /^## (\d+)\. (.+)$/, 'CTX-', 'uml/context', /^## /],
]) {
  const ls = lines(f);
  ls.forEach((l, i) => {
    const m = l.match(re);
    if (!m) return;
    const id = prefix + m[1], end = sectionEnd(ls, i, stop), n = node(id, dir.split('/')[1]);
    n.title = m[2];
    n.src.push(src(f, i + 1, end));
    addSplit(`${dir}/${m[1]}-${slug(m[2])}.md`, [{ file: f, a: i + 1, b: end }], id);
  });
}

// Database tables: CREATE TABLE ranges in the SQL, and the table's section in the explained schema.
for (const [sql, md, db] of [[SOURCES.portSql, SOURCES.portMd, 'port'], [SOURCES.saasSql, SOURCES.saasMd, 'saas']]) {
  const ls = lines(sql);
  ls.forEach((l, i) => {
    const m = l.match(/^CREATE TABLE `([a-z_0-9]+)`/);
    if (m) {
      let j = i;
      while (j < ls.length && !/^\)/.test(ls[j])) j++;
      const n = node('table:' + m[1], 'table');
      Object.assign(n, { title: m[1], database: db });
      n.src.push(src(sql, i + 1, j + 1));
    }
  });
  const ms = lines(md);
  ms.forEach((l, i) => {
    const m = l.match(/^## `([a-z_0-9]+)`$/);
    if (m && nodes['table:' + m[1]]) nodes['table:' + m[1]].src.push(src(md, i + 1, sectionEnd(ms, i, /^## /)));
  });
}
// One split file per table: its CREATE TABLE statement and its explained section.
for (const [id, n] of Object.entries(nodes)) {
  if (n.type !== 'table' || !n.src.length) continue;
  addSplit(`tables/${n.title}.md`, n.src.map(s => { const [a, b = a] = s.lines.split('-').map(Number); return { file: s.file, a, b }; }), id);
}

// Reverse links, so a lookup shows what waits on a node and what refers to it.
for (const [id, n] of Object.entries(nodes)) {
  for (const [rel, targets] of Object.entries(n.out || {})) {
    for (const t of targets) {
      if (!nodes[t]) { missing.add(t); continue; }
      const inRel = { depends_on: 'unblocks', affects: 'affected_by', refs: 'referenced_by', blocks: 'blocked_by', ai_tasks: 'needed_by', decided_by: 'decides', same_as: 'same_as' }[rel] || 'in_' + rel;
      ((nodes[t].in ??= {})[inRel] ??= []).includes(id) || nodes[t].in[inRel].push(id);
    }
  }
}

// IDs used in the backend tasks file that resolve nowhere.
const unresolved = [...new Set(refs(read(SOURCES.tasks)))].filter(r => !nodes[r]).sort();

const sources = Object.fromEntries(Object.values(SOURCES).map(f => [f, sha1(f)]));
const ids = Object.keys(nodes).sort();
const body = [
  '{',
  ` "about": "Generated by docs/reference/build-index.mjs from the documents in docs/reference. Titles and pointers only; read the source lines for meaning. Do not edit by hand.",`,
  ` "sources": ${JSON.stringify(sources)},`,
  ` "not_defined_in_reference": ${JSON.stringify([...missing].sort())},`,
  ' "nodes": {',
  ids.map(id => `  ${JSON.stringify(id)}: ${JSON.stringify(nodes[id])}`).join(',\n'),
  ' }',
  '}',
  '',
].join('\n');

if (process.argv.includes('--check')) {
  let ok = true;
  if (!fs.existsSync(outFile)) { console.log('Index missing: run node docs/reference/build-index.mjs'); process.exit(1); }
  const current = JSON.parse(fs.readFileSync(outFile, 'utf8'));
  const stale = Object.keys(sources).filter(f => current.sources?.[f] !== sources[f]);
  if (stale.length) { ok = false; console.log('Index is stale; source changed: ' + stale.join(', ') + '. Run node docs/reference/build-index.mjs'); }
  const onDisk = fs.existsSync(splitDir) ? fs.readdirSync(splitDir, { recursive: true }).filter(p => p.endsWith('.md')).map(p => p.replace(/\\/g, '/')) : [];
  const badSplits = [...Object.keys(splits).filter(p => !onDisk.includes(p) || fs.readFileSync(path.join(splitDir, p), 'utf8') !== splits[p]), ...onDisk.filter(p => !splits[p])];
  if (badSplits.length) { ok = false; console.log(`Split files out of date (${badSplits.length}, e.g. ${badSplits.slice(0, 3).join(', ')}). Run node docs/reference/build-index.mjs`); }
  if (unresolved.length) { ok = false; console.log('IDs used in the backend tasks that resolve nowhere: ' + unresolved.join(', ')); }
  if (ok) console.log(`Index up to date: ${ids.length} nodes, ${Object.keys(splits).length} split files; every ID in the backend tasks resolves.`);
  process.exit(ok ? 0 : 1);
}

fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(outFile, body);
fs.rmSync(splitDir, { recursive: true, force: true });
for (const [rel, text] of Object.entries(splits)) {
  fs.mkdirSync(path.dirname(path.join(splitDir, rel)), { recursive: true });
  fs.writeFileSync(path.join(splitDir, rel), text);
}
const byDir = Object.keys(splits).reduce((a, p) => ((a[p.split('/').slice(0, -1).join('/')] = (a[p.split('/').slice(0, -1).join('/')] || 0) + 1), a), {});
console.log(`Wrote ${Object.keys(splits).length} split files ${JSON.stringify(byDir)}`);
const byType = ids.reduce((a, id) => ((a[nodes[id].type] = (a[nodes[id].type] || 0) + 1), a), {});
console.log(`Wrote index/index.json: ${ids.length} nodes ${JSON.stringify(byType)}`);
console.log(`Referenced but not defined in docs/reference: ${missing.size}${missing.size ? ' (' + [...missing].sort().slice(0, 15).join(', ') + (missing.size > 15 ? ', …' : '') + ')' : ''}`);
if (unresolved.length) console.log('IDs used in the backend tasks that resolve nowhere: ' + unresolved.join(', '));
