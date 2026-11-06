import fs from 'fs';
import path from 'path';

const SCHEMA = 'E:/collage/GRADUATION PROGECT/PortHub/contracts/db/schema.sql';
const DOCS = 'E:/collage/GRADUATION PROGECT/PortHub/project-backend/ATLANTECH/docs/reference/split/tables/';
const OUT = process.argv[2] || 'E:/collage/GRADUATION PROGECT/Code Notes/Backend_Guide/F05b_database_diagrams.md';

// ---------- business groups (the only hand-written part) ----------
const GROUPS = [
  { id: 'access', name: 'Access and users', about: 'Who may sign in and where they work. A user holds one active role in one organisation unit (DEC-36); devices are the tablets a user signs in from. `personal_access_tokens` is a Laravel Sanctum framework table, not one of the 92 port tables: it holds the mobile token of each device and sits here because it points at `user_devices`.', tables: ['organization_units', 'users', 'user_role_assignments', 'user_devices', 'personal_access_tokens'] },
  { id: 'system', name: 'Audit, licence and notifications', about: 'Cross-cutting records: the append-only audit log, the one licence state row, and the log of notifications sent.', tables: ['audit_logs', 'license_states', 'notification_logs'] },
  { id: 'port', name: 'Port set-up', about: 'The port itself: its single profile row and settings, the navigation channel and waypoints, terminals, berths and which terminals use which berths, status vocabulary and the external systems the port talks to.', tables: ['port_profiles', 'port_settings', 'navigation_channels', 'channel_waypoints', 'terminals', 'berths', 'berth_terminals', 'external_systems', 'status_types'] },
  { id: 'people', name: 'Pilots, tugs and shifts', about: 'The marine workforce and fleet: pilots with their rank limits and schedules, tugs with their masters and schedules, and the port shifts those schedules refer to.', tables: ['pilot_profiles', 'pilot_rank_limits', 'pilot_schedules', 'tugboats', 'tug_master_profiles', 'tug_master_schedules', 'port_shifts'] },
  { id: 'vessels', name: 'Vessels and cargo reference', about: 'Reference data a vessel call is described with: vessel types and vessels, container size types and containers, dangerous-goods classes and cargo types.', tables: ['vessel_types', 'vessels', 'container_size_types', 'containers', 'imdg_classes', 'cargo_types'] },
  { id: 'equipment', name: 'Equipment', about: 'Terminal equipment (cranes and similar), what each can do, which cargo it can handle, and the productivity of each terminal.', tables: ['equipment_categories', 'capability_types', 'equipment', 'equipment_capabilities', 'equipment_cargo_compatibilities', 'terminal_productivity_profiles'] },
  { id: 'storage', name: 'Storage facilities', about: 'Non-container storage: storage types and facilities, tank and warehouse details, and which cargo each facility and terminal accepts.', tables: ['storage_types', 'storage_facilities', 'storage_facility_cargo_compatibilities', 'tank_details', 'warehouse_details', 'terminal_cargo_types'] },
  { id: 'safety', name: 'Safety constraints', about: 'The limits the Marine Safety Officer sets (wind, visibility, under-keel clearance and so on) and the constraint types they refer to.', tables: ['constraint_types', 'safety_constraints'] },
  { id: 'environment', name: 'Environment and tides', about: 'Weather and sea forecasts, and the tide model: constituent sets, prediction runs and predicted points.', tables: ['environment_forecast_points', 'forecast_runs', 'tide_constituents', 'tide_constituent_sets', 'tide_prediction_points', 'tide_prediction_runs'] },
  { id: 'request', name: 'Berthing request and entry window (AI Part 1)', about: 'A vessel call: the request, its cargo and status history, and what AI Part 1 produces for it: the demand forecast, feasible windows, blocked periods and the entry-window proposal.', tables: ['berthing_requests', 'berthing_request_cargos', 'berthing_request_status_histories', 'part1_runs', 'demand_forecast_slots', 'feasible_windows', 'blocked_periods', 'entry_window_proposals'] },
  { id: 'baplie', name: 'BAPLIE (vessel load files)', about: 'The uploaded load file of a vessel, its parse runs and issues, and the container manifest it yields.', tables: ['baplie_files', 'baplie_parse_runs', 'baplie_parse_issues', 'container_manifest_entries'] },
  { id: 'recommendation', name: 'Recommendation (AI Part 2)', about: 'What AI Part 2 recommends (berth, tugs, pilot): the recommendation and its options, the solver runs and model versions behind it, the rationale, and the human review and overrides.', tables: ['recommendations', 'recommendation_options', 'recommendation_option_tugs', 'recommendation_overrides', 'recommendation_rationale_factors', 'recommendation_reviews', 'solver_runs', 'solver_run_model_versions', 'objective_terms', 'rationale_templates'] },
  { id: 'models', name: 'AI models and learning', about: 'The registry of machine-learning models and their versions (one champion per model), and the records that watch them: drift, evaluation metrics and retraining runs.', tables: ['ml_models', 'ml_model_versions', 'drift_measurements', 'evaluation_metrics', 'retraining_runs'] },
  { id: 'plan', name: 'Plan and execution', about: 'The confirmed operational plan and its components (one per pilot, tug or terminal task), what actually happened in the field, and the deviations found.', tables: ['operational_plans', 'plan_components', 'actual_status_updates', 'deviation_logs'] },
  { id: 'yardzones', name: 'Yard layout', about: 'The container yard: zone types, zones, blocks and slots, with the special rules for dangerous, oversize and reefer zones.', tables: ['yard_zone_types', 'yard_zones', 'yard_blocks', 'yard_slots', 'dg_zone_allowed_classes', 'oog_zone_details', 'reefer_zone_details'] },
  { id: 'yardplace', name: 'Yard placement', about: 'Where containers are placed: the yard run, the terminal recommendation, the placement items, storage allocations and movements.', tables: ['yard_runs', 'terminal_recommendations', 'yard_placement_items', 'storage_allocation_items', 'storage_movements'] },
];
const HUB = 'users';
// Framework tables that appear in a group but are not among the 92 port tables (CT-17 section 3): they are drawn and described,
// but left out of the "92 port tables" counts.
const FRAMEWORK_IN_GROUPS = new Set(['personal_access_tokens']);
const FRAMEWORK_NOTE = {
  personal_access_tokens: 'Framework table (Laravel Sanctum), not one of the 92 port tables: the mobile token of a device (`user_device_id`, `revoked_at`); never deleted, workers get no grant.',
};

// ---------- parse the schema ----------
const sql = fs.readFileSync(SCHEMA, 'utf8').split(String.fromCharCode(13)).join('');
const T = {};
for (const m of sql.matchAll(/CREATE TABLE `([a-z_0-9]+)` \(\n([\s\S]*?)\n\) ENGINE/g)) {
  const [_, name, body] = m;
  const t = { name, cols: [], pk: [], uniques: [], fks: [], checks: 0 };
  for (const line of body.split('\n')) {
    const l = line.trim().replace(/,$/, '');
    let mm;
    if ((mm = l.match(/^`([a-z_0-9]+)` (.+)$/))) {
      const rest = mm[2];
      const typeMatch = rest.match(/^(enum\([^)]*\)|[a-z]+(?:\([^)]*\))?(?: unsigned)?)/);
      const type = typeMatch ? typeMatch[1] : rest.split(' ')[0];
      const comment = (rest.match(/COMMENT '((?:[^']|'')*)'/) || [])[1];
      t.cols.push({
        name: mm[1], type, notNull: /NOT NULL/.test(rest), generated: /GENERATED ALWAYS/.test(rest),
        comment: comment ? comment.replace(/''/g, "'") : '',
        enumVals: type.startsWith('enum(') ? [...type.matchAll(/'([^']*)'/g)].map(x => x[1]) : null,
        version: /datetime\(6\)/.test(type) && mm[1] === 'updated_at',
      });
    } else if ((mm = l.match(/^PRIMARY KEY \((.+)\)$/))) {
      t.pk = [...mm[1].matchAll(/`([^`]+)`/g)].map(x => x[1]);
    } else if ((mm = l.match(/^UNIQUE KEY `([^`]+)` \((.+)\)/))) {
      t.uniques.push({ name: mm[1], cols: [...mm[2].matchAll(/`([^`]+)`/g)].map(x => x[1]) });
    } else if ((mm = l.match(/^CONSTRAINT `([^`]+)` FOREIGN KEY \((.+?)\) REFERENCES `([^`]+)`/))) {
      const cols = [...mm[2].matchAll(/`([^`]+)`/g)].map(x => x[1]);
      t.fks.push({ name: mm[1], col: cols[0], cols, to: mm[3] });
    } else if (/^CONSTRAINT `[^`]+` CHECK/.test(l)) {
      t.checks++;
    }
  }
  T[name] = t;
}
// deferred foreign keys added by ALTER TABLE
for (const m of sql.matchAll(/ALTER TABLE `([a-z_0-9]+)`\s+ADD CONSTRAINT `([^`]+)` FOREIGN KEY \((.+?)\) REFERENCES `([^`]+)`/g)) {
  const cols = [...m[3].matchAll(/`([^`]+)`/g)].map(x => x[1]);
  if (T[m[1]]) T[m[1]].fks.push({ name: m[2], col: cols[0], cols, to: m[4], deferred: true });
}
// CHECK count straight from each table body (cross-check of the parser above)
for (const m of sql.matchAll(/CREATE TABLE `([a-z_0-9]+)` \(\n([\s\S]*?)\n\) ENGINE/g)) {
  T[m[1]].checks = (m[2].match(/CONSTRAINT `[^`]+` CHECK/g) || []).length;
}

const groupOf = {};
for (const g of GROUPS) for (const t of g.tables) groupOf[t] = g;
const missing = Object.keys(T).filter(n => !groupOf[n] && !['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'password_reset_tokens', 'sessions'].includes(n));
const unknown = GROUPS.flatMap(g => g.tables).filter(n => !T[n]);
if (missing.length || unknown.length) { console.error('MISSING from groups:', missing, 'UNKNOWN in groups:', unknown); process.exit(1); }

// ---------- helpers ----------
const SING = {};
for (const m of fs.readFileSync('E:/collage/GRADUATION PROGECT/PortHub/contracts/db/table-names.md', 'utf8').matchAll(/\| \d+ \| `([a-z_0-9]+)` \| `[A-Za-z]+` \| `([a-z_0-9]+)` \|/g)) SING[m[2]] = m[1];
const F05P = {};
for (const line of fs.readFileSync('E:/collage/GRADUATION PROGECT/Code Notes/Backend_Guide/F05_database_schema_and_migrations.md', 'utf8').split('\n')) {
  const m = line.match(/^\| `\d+` \| `([a-z_0-9]+)` \| (.+?) \| (?:yes)?\s*\|/);
  if (m) F05P[m[1]] = m[2];
}
const purposeOf = name => {
  if (FRAMEWORK_NOTE[name]) return FRAMEWORK_NOTE[name];
  if (F05P[name]) return F05P[name];
  try {
    const s = fs.readFileSync(DOCS + (SING[name] || name) + '.md', 'utf8');
    const m = s.match(/^> (.+)$/m);
    return m ? m[1].trim().replace(/\|/g, '/') : '';
  } catch { return ''; }
};
const clean = s => s.replace(/"/g, "'").replace(/\|/g, '/').replace(/\s+/g, ' ').trim();
const mtype = t => t.replace(/ unsigned/, '').replace(/,/g, '_').replace(/\s/g, '').replace(/^enum\(.*\)$/, 'enum');
const isUniqueCol = (t, c) => t.uniques.some(u => u.cols.length === 1 && u.cols[0] === c) || (t.pk.length === 1 && t.pk[0] === c);
const fkCols = t => new Set(t.fks.flatMap(f => f.cols));

function entity(t) {
  const fk = fkCols(t);
  const lines = [];
  for (const c of t.cols) {
    const keys = [];
    if (t.pk.includes(c.name)) keys.push('PK');
    if (fk.has(c.name)) keys.push('FK');
    if (t.uniques.some(u => u.cols.length === 1 && u.cols[0] === c.name)) keys.push('UK');
    const note = [];
    if (c.enumVals) { const v = c.enumVals.join('|'); note.push(v.length > 44 ? v.slice(0, 41) + '...' : v); }
    if (t.fks.some(fk => fk.to === HUB && fk.cols.includes(c.name))) note.push('to users, not drawn');
    if (c.version) note.push('version token');
    if (c.generated) note.push('generated');
    if (!keys.length && !note.length) continue;
    lines.push(`    ${mtype(c.type)} ${c.name}${keys.length ? ' ' + keys.join(',') : ''}${note.length ? ` "${clean(note.join('; '))}"` : ''}`);
  }
  return `  ${t.name} {\n${lines.join('\n')}\n  }`;
}
const stub = (t, g) => `  ${t.name} {\n    bigint id PK\n    string group "${clean(g ? g.name : 'framework')}"\n  }`;

function rel(child, fk) {
  const col = child.cols.find(c => c.name === fk.col);
  const one = fk.cols.length === 1 && isUniqueCol(child, fk.col);
  const left = one ? '|o' : '}o';
  const right = fk.cols.every(cn => (child.cols.find(c => c.name === cn) || {}).notNull) ? '||' : 'o|';
  return `  ${child.name} ${left}--${right} ${fk.to} : "${fk.cols.join(' + ')}"`;
}

// ---------- build the document ----------
const out = [];
const gTables = g => g.tables.map(n => T[n]);
const allFks = Object.values(T).flatMap(t => t.fks.map(f => ({ ...f, from: t.name })));
const isPort = n => groupOf[n] && !FRAMEWORK_IN_GROUPS.has(n);
const portTables = Object.keys(T).filter(isPort);
const hubCount = allFks.filter(f => f.to === HUB && isPort(f.from)).length;
const ext = allFks.filter(f => groupOf[f.from] && groupOf[f.to] && groupOf[f.from] !== groupOf[f.to] && f.to !== HUB);

out.push(`# F05b · Database diagrams by group

Companion of [F05](F05_database_schema_and_migrations.md): the same ${portTables.length} port tables, drawn **one group at a time** instead of all at once. Each group is a business area with 2 to 10 tables, so each diagram stays readable.

Generated by a script from \`contracts/db/schema.sql\` (the schema snapshot published for the other teams, CT-17) and the table notes in \`docs/reference/split/tables/\`. Nothing here is hand-drawn except the group membership and the one-line group descriptions. If a migration changes a foreign key, regenerate the file; do not edit the diagrams by hand. The snapshot is the one republished after the \`personal_access_tokens\` migration (header: last migration \`2026_11_05_100000_create_personal_access_tokens_table\`); it holds 101 tables: the ${portTables.length} port tables, the Sanctum framework table \`personal_access_tokens\` (drawn in "Access and users" and marked as a framework table, it is not counted below) and the 8 other Laravel framework tables (not drawn).

## How to read the diagrams

- A box is a table. Inside it only the columns that matter for understanding the model are listed: **PK** (primary key), **FK** (foreign key), **UK** (a single-column unique key), every **ENUM** column with its values, the \`updated_at\` **version token** (microsecond precision, used for the 409 check, F12) and **generated** columns. The full column lists are in F05 section 3.3 and the table notes.
- A line is a foreign key, drawn from the **child** (the table that holds the column) to the **parent**. The label is the foreign-key column. \`}o--||\` means many children to exactly one parent; \`}o--o|\` means the column may be empty (the parent is optional); \`|o--||\` means at most one child per parent (the column is unique).
- A box with only \`id\` and a \`group\` line is a **stub**: a parent table that lives in another group, shown so you can see what this group depends on. Open that group's diagram to see the table itself.
- **Edges to \`users\` are not drawn.** \`users\` is referenced by ${hubCount} foreign keys (created by, assigned to, owner, reviewer and so on); drawing them would tangle every diagram. Each group lists them in a small table instead.
- **Inbound links** (other groups that point at this group's tables) are listed in a table, not drawn.

Reading order: the overview below, then the groups in the order given (reference data first, the vessel-call flow in the middle, yard and execution last).

## Overview: how the groups depend on each other

An arrow means "has a foreign key to". The number is how many foreign keys go that way. Links to \`users\` and links inside a group are left out.
`);

// overview: counts between groups
const pair = {};
for (const f of ext) { const k = groupOf[f.from].id + '>' + groupOf[f.to].id; pair[k] = (pair[k] || 0) + 1; }
out.push('```mermaid\nflowchart LR');
for (const g of GROUPS) out.push(`  ${g.id}["${clean(g.name)}<br/>${g.tables.filter(isPort).length} tables"]`);
for (const [k, n] of Object.entries(pair).sort()) { const [a, b] = k.split('>'); out.push(`  ${a} -->|${n}| ${b}`); }
out.push('```\n');

out.push('| Group | Tables | Foreign keys to other groups | Foreign keys to `users` |\n| --- | --- | --- | --- |');
for (const g of GROUPS) {
  const tabs = new Set(g.tables);
  const toOther = allFks.filter(f => tabs.has(f.from) && groupOf[f.to] && groupOf[f.to] !== g && f.to !== HUB).length;
  const toUsers = allFks.filter(f => tabs.has(f.from) && f.to === HUB).length;
  out.push(`| [${g.name}](#${g.id}) | ${g.tables.filter(isPort).length} | ${toOther} | ${toUsers} |`);
}
out.push('');
out.push(`Hub tables everyone leans on: \`users\` (${hubCount} foreign keys in), then \`terminals\`, \`berths\`, \`tugboats\`, \`ml_model_versions\`, \`berthing_requests\`, \`imdg_classes\`, \`pilot_profiles\`. They sit in the groups "Access and users", "Port set-up", "Pilots, tugs and shifts", "AI models and learning", "Berthing request and entry window" and "Vessels and cargo reference".\n`);

for (const g of GROUPS) {
  const tabs = gTables(g);
  const tabSet = new Set(g.tables);
  out.push(`<a id="${g.id}"></a>\n## ${g.name}\n`);
  out.push(g.about + '\n');
  // diagram
  const ents = tabs.map(entity);
  const stubs = new Map();
  const rels = [];
  for (const t of tabs) for (const f of t.fks) {
    if (f.to === HUB) continue;
    if (!T[f.to]) continue;
    if (!tabSet.has(f.to)) stubs.set(f.to, T[f.to]);
    rels.push(rel(t, f));
  }
  out.push('```mermaid\nerDiagram');
  out.push(ents.join('\n'));
  for (const s of stubs.values()) out.push(stub(s, groupOf[s.name]));
  out.push(rels.join('\n'));
  out.push('```\n');
  // tables
  out.push('| Table | Purpose | Foreign keys (to) | Unique rules | CHECKs |\n| --- | --- | --- | --- | --- |');
  for (const t of tabs) {
    const fkTxt = t.fks.filter(f => f.to !== HUB).map(f => `\`${f.cols.join(' + ')}\` to \`${f.to}\`${f.deferred ? ' (deferred)' : ''}`).join('; ') || (t.fks.length ? 'only `users`' : 'none');
    const uq = t.uniques.filter(u => u.name !== 'PRIMARY').map(u => `\`${u.name}\` (${u.cols.join(', ')})`).join('; ') || 'none';
    out.push(`| \`${t.name}\` | ${clean(purposeOf(t.name))} | ${fkTxt} | ${uq} | ${t.checks} |`);
  }
  out.push('');
  // users edges
  const ue = tabs.flatMap(t => t.fks.filter(f => f.to === HUB).map(f => ({ t, f })));
  if (ue.length) {
    out.push('**Links to `users`** (not drawn):\n\n| Table | Column | Meaning |\n| --- | --- | --- |');
    for (const { t, f } of ue) {
      const c = t.cols.find(x => x.name === f.col);
      out.push(`| \`${t.name}\` | \`${f.col}\` | ${clean((c && c.comment) || '') || (c && c.notNull ? 'required' : 'optional')} |`);
    }
    out.push('');
  }
  // inbound
  const inbound = allFks.filter(f => tabSet.has(f.to) && f.to !== HUB && groupOf[f.from] && groupOf[f.from] !== g);
  if (tabSet.has(HUB)) out.push(`\`users\` is referenced by ${hubCount} columns in every group; they are listed under "Links to \`users\`" in each group, not repeated here.\n`);
  if (inbound.length) {
    out.push('**Referenced from other groups:**\n\n| Other table | Column | Points at | Group |\n| --- | --- | --- | --- |');
    for (const f of inbound.sort((a, b) => (groupOf[a.from].name + a.from).localeCompare(groupOf[b.from].name + b.from))) {
      out.push(`| \`${f.from}\` | \`${f.col}\` | \`${f.to}\` | ${groupOf[f.from].name} |`);
    }
    out.push('');
  }
}

out.push(`## Regenerating this file

The generator is \`Code Notes/Backend_Guide/tools/gen_db_diagrams.mjs\` (run it with \`node gen_db_diagrams.mjs\`; its input and output paths are absolute and point at this machine, so adjust the constants at the top first). It is a short Node script that reads \`contracts/db/schema.sql\` and the table notes, and writes this file. The group membership is the only hand-written input (a list of table names per group). Two checks run every time: every table of the schema (except the eight Laravel framework tables \`cache\`, \`cache_locks\`, \`failed_jobs\`, \`job_batches\`, \`jobs\`, \`migrations\`, \`password_reset_tokens\`, \`sessions\`) must belong to exactly one group, and every table named in a group must exist; if either fails the script stops. \`personal_access_tokens\` is in a group but listed in \`FRAMEWORK_IN_GROUPS\`, so it is drawn and described but not counted among the 92 port tables. When a migration adds a table, add it to a group and regenerate; when it changes a foreign key, regenerate.

Numbers in this file: ${portTables.length} port tables, ${GROUPS.length} groups, ${allFks.filter(f => isPort(f.from)).length} foreign keys (${hubCount} to \`users\`, ${ext.length} between groups, ${allFks.filter(f => isPort(f.from) && groupOf[f.to] === groupOf[f.from] && f.to !== HUB).length} inside a group, ${portTables.reduce((n, k) => n + T[k].checks, 0)} CHECK constraints).
`);

fs.writeFileSync(OUT, out.join('\n'));
console.log('written', OUT, 'groups', GROUPS.length, 'tables', portTables.length, 'fks', allFks.filter(f => isPort(f.from)).length);
