#!/usr/bin/env node
// Prints one index node and the titles of everything it links to, in a few lines.
//
//   node docs/reference/lookup.mjs P3-G2            a task, decision, contract, AIBD, FR, NFR, SM…
//   node docs/reference/lookup.mjs part1_run        a table (the "table:" prefix is optional)
//   node docs/reference/lookup.mjs --find "version" IDs whose title contains the text
//
// The output points to source lines; read those lines for meaning (titles are not summaries).
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const { nodes, not_defined_in_reference: undefinedIds } = JSON.parse(fs.readFileSync(path.join(here, 'index', 'index.json'), 'utf8'));
const short = (s, n = 70) => (s && s.length > n ? s.slice(0, n - 1) + '…' : s || '');
const args = process.argv.slice(2);

if (args[0] === '--find') {
  const q = (args[1] || '').toLowerCase();
  const hits = Object.entries(nodes).filter(([id, n]) => (id + ' ' + n.title).toLowerCase().includes(q));
  for (const [id, n] of hits.slice(0, 40)) console.log(`${id} · ${n.type} · ${short(n.title, 90)}`);
  if (hits.length > 40) console.log(`… ${hits.length - 40} more; narrow the search`);
  process.exit(hits.length ? 0 : 1);
}

for (const raw of args) {
  const id = nodes[raw] ? raw : nodes['table:' + raw] ? 'table:' + raw : null;
  if (!id) {
    console.log(undefinedIds.includes(raw) ? `${raw}: referenced, but not defined in docs/reference (its source document is missing)` : `${raw}: not in the index`);
    continue;
  }
  const n = nodes[id];
  const facts = ['team', 'sprint', 'feature', 'phase', 'package', 'effort', 'owner', 'status', 'close_by', 'direction', 'required', 'spec_status', 'priority', 'mvp', 'database', 'from', 'to', 'needed_before', 'blocking']
    .filter(k => n[k] !== undefined && n[k] !== '').map(k => `${k}: ${n[k]}`);
  console.log(`${id} · ${n.type} · ${n.title}`);
  if (facts.length) console.log('  ' + facts.join(' · '));
  for (const s of n.src) console.log(`  source: ${s.file}:${s.lines}`);
  if (n.written_by) console.log(`  written by: created ${n.written_by.created_by} · completed ${n.written_by.completed_by} (${n.written_by.source.file}:${n.written_by.source.lines})`);
  for (const [dir, links] of [['→', n.out], ['←', n.in]]) {
    for (const [rel, ids] of Object.entries(links || {})) {
      console.log(`  ${dir} ${rel}: ` + ids.map(t => `${t}${nodes[t] ? ' (' + short(nodes[t].title, 50) + ')' : ' (not defined)'}`).join(' · '));
    }
  }
}
