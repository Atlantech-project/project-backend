# F13 · Seeders and the demo port

Task origin: P1-B6 (Sprint 2, PR #48, merged). Facts checked against `develop` at `106b40e`. Paths are inside `project-backend/ATLANTECH/` unless stated. Plan file: `docs/tasks/P1-B6.md` (decisions K1, C-1, R-1, R-2 and answers Q1 to Q5 are cited below).

## 1. What it does

`php artisan db:seed` (or `migrate --seed`, `migrate:fresh --seed`) fills an empty database with:

1. **Reference data** every installation needs: 20 IMDG classes, 13 container size types, 6 vessel types, 7 cargo types, 4 yard zone types, 4 storage types, 4 capability types, 6 equipment categories, 8 status types, 1 external system (the Egyptian Maritime Single Window), and two port settings (`reminder_lead_minutes`, `min_app_version`).
2. **A demo port at the scale of the reference port (Damietta)**, only where demo seeding is allowed: the one `port_profiles` row, 8 terminals, 36 berths (two shared by two terminals), a navigation channel with 4 waypoints, 3 shifts, the 2 port-wide organisation units and 8 terminal units.
3. **Demo people**: 32 users covering all 12 roles (10 role users, 12 pilots, 10 tug masters), 12 pilot profiles, 10 tugboats, and 7 days of pilot and tug-master schedules.
4. Outside production, one extra user `test.user` (from the factory).

It is **idempotent**: a second run adds nothing and, for reference rows, follows changes in the data file. It is **guarded**: the demo data never reaches a production database unless `SEED_DEMO_PORT=true`.

It does **not** seed: the AI team's rows (`constraint_types`, `safety_constraints`, `objective_terms`, `rationale_templates`, `ml_models`, `ml_model_versions`, AI `port_settings` keys): these belong to task P2-F9 (Sprint 3, depends on the AI seed spec and CT-18, see F15). It seeds no vessels, requests, tides or weather, no `license_states` row, and no real first Port Admin (task P2-E1 owns that, F15). It does not run on its own in Docker: someone must run `migrate --seed` (see gotchas).

## 2. Request or data flow

`php artisan db:seed` runs `Database\Seeders\DatabaseSeeder::run()` (`database/seeders/DatabaseSeeder.php:16`):

1. If the app is **not** in production (`app()->isProduction()` false): `User::firstOrCreate(['username' => 'test.user'], <factory attributes with name 'Test User'>)` (lines 18-23). It is skipped in production because the factory password is a known default (security finding during P1-B6 review).
2. `PortSettingsSeeder` (line 26): the two Backend `port_settings` keys.
3. `ReferenceDataSeeder` (line 27): the ten reference sections.
4. If `config('seeding.demo_port')` is true (line 30): `DemoPortSeeder` then `DemoPeopleSeeder`.
   - `DemoPortSeeder::run()` first calls `ensureAllowed()` (throws when demo seeding is off), then **calls `PortProfileSeeder`** (which itself calls `ensureAllowed()`), then in one transaction: organisation units, terminals (and one terminal unit each), berths (and their `berth_terminals` links), the channel and waypoints, the shifts.
   - `DemoPeopleSeeder::run()` calls `ensureAllowed()`, hashes the password **once**, then in one transaction creates the role users, pilots, tugs with masters, and the schedules.

`PortProfileSeeder` is **not** listed in `DatabaseSeeder`; it runs only through `DemoPortSeeder` (decision K1, below). So with demo seeding off there is no `port_profiles` row from the seeders.

Order matters: `PortSettingsSeeder` and `ReferenceDataSeeder` have no dependency on each other; `DemoPeopleSeeder` needs the organisation units, terminals, shifts and `port_profiles` row 1 that `DemoPortSeeder` creates (it reads the port timezone, `PortProfile::findOrFail(1)`, line 117).

## 3. Every piece of code

### 3.1 `DatabaseSeeder` (`database/seeders/DatabaseSeeder.php`)

Uses `WithoutModelEvents`. `run(): void` as in section 2. The only logic of its own is the `test.user` rule and the `config('seeding.demo_port')` switch.

### 3.2 `ReferenceDataSeeder` (`database/seeders/ReferenceDataSeeder.php`)

- Constant `SECTIONS` (lines 64-75): section name in the file => `[model class, key columns]`. Parents come before children:

| Section in `reference-seed.json` | Model | Upsert key |
| --- | --- | --- |
| `imdg_classes` | `MasterData\Models\ImdgClass` | `code` |
| `container_size_types` | `ContainerSizeType` | `code` |
| `vessel_types` | `VesselType` | `code` |
| `cargo_types` | `CargoType` | `code` (its `imdg_class` column points to an IMDG code, loaded first) |
| `yard_zone_types` | `Yard\Models\YardZoneType` | `code` |
| `storage_types` | `StorageType` | `code` |
| `capability_types` | `CapabilityType` | `code` |
| `equipment_categories` | `EquipmentCategory` | `code` |
| `status_types` | `StatusType` | `component_type`, `code` |
| `external_systems` | `ExternalSystem` | `code` |

- `run(): void` (line 77): reads `database/seeders/data/reference-seed.json` (`JSON_THROW_ON_ERROR`), opens one `DB::transaction`, and for each section calls `$model::query()->upsert($rows, $keys, $updateColumns)` where the update columns are all columns of the first row minus the keys (lines 83-87). Result: a missing row is inserted; an existing row is updated to the file's values (a changed name or unit follows the file). Needs the unique key to exist in the database; `status_types` has a unique key on (`component_type`, `code`) (review fix M3).
- Note: rows removed from the file are **not** deleted from the database.
- Assumes every section has at least one row (`$spec[$section][0]`).

### 3.3 `PortSettingsSeeder` (`database/seeders/PortSettingsSeeder.php`)

- `run(?string $specPath = null): void` (line 138): reads the file (the optional path is for tests), and in one transaction calls `upsert($spec['port_settings'])`.
- `upsert(array $rows)` (private, line 149): for each row, `PortSetting::firstOrNew(['setting_key' => ...])` and then the **CT-18 section 3 rules**:
  - `description`, `min_value` (from the JSON key `min`) and `max_value` (key `max`) are **always** set from the file (`null` when absent);
  - `setting_value` and `value_type` are set **only if the row is new or `updated_by` is NULL** (nobody changed it by hand). A value changed by the Port Admin (who sets `updated_by`) is never overwritten;
  - `save()` only when `isDirty()`.

### 3.4 `PortProfileSeeder` (`database/seeders/PortProfileSeeder.php`)

- `const PUBLIC_ID = '7d0f6b1e-5a1c-4e0a-9a53-0d1b7e3c2f10'`: a placeholder for the SaaS registry value, which a provisioned deployment overwrites.
- `run(): void` (line 109): `DemoPortSeeder::ensureAllowed()`, then `PortProfile::firstOrCreate(['id' => 1], [...])` with port code `EGDAM`, governorate `DT`, name `Damietta Port`, latitude `31.4333`, longitude `31.7833`, timezone `Africa/Cairo`. An **existing row is left alone** (the SaaS side owns it).

### 3.5 `DemoPortSeeder` (`database/seeders/DemoPortSeeder.php`)

| Member | What it does |
| --- | --- |
| `private array $fixture` / `fixture(): array` (line 56, public) | Loads `database/seeders/data/damietta-demo-port.json` once (memoised). `DemoPeopleSeeder` reuses it through `(new DemoPortSeeder)->fixture()['people']`. |
| `run()` (line 27) | `ensureAllowed()`; `$this->call(PortProfileSeeder::class)`; one transaction running the five private methods below. |
| `static ensureAllowed(): void` (line 46) | **The production guard.** Throws `RuntimeException('Demo seeding is off in this environment. Set SEED_DEMO_PORT=true to load the demo port.')` when `config('seeding.demo_port')` is false. Called by `PortProfileSeeder`, `DemoPortSeeder` and `DemoPeopleSeeder`, so even `db:seed --class=DemoPeopleSeeder` is refused in production. |
| `seedOrganizationUnits()` (line 61) | `OrganizationUnit::firstOrCreate` for `HARBOUR_AUTHORITY` and `MARINE_SERVICES` with `terminal_id` NULL (one of each; DEC-36 and the role/unit check `chk_ura_role_matches_unit` need them). |
| `seedTerminals()` (line 68) | For each fixture terminal `Terminal::updateOrCreate(['code' => ...], $row + ['is_active' => true])` and `OrganizationUnit::firstOrCreate(['unit_type' => 'TERMINAL', 'terminal_id' => $terminal->id])`. |
| `seedBerths()` (line 77) | For each berth `Berth::updateOrCreate(['code' => ...], <row without terminal_codes> + ['depth_datum' => fixture depth_datum, 'status' => 'ACTIVE'])`, then `$berth->terminals()->syncWithoutDetaching(<terminal ids by code>)`: this fills `berth_terminals` (at least one terminal per berth, shared berths have two). |
| `seedChannel()` (line 91) | `NavigationChannel::updateOrCreate(['name' => ...], <row without waypoints> + ['is_active' => true])`, then each waypoint `ChannelWaypoint::updateOrCreate(['channel_id' => ..., 'sequence_order' => index + 1], $waypoint)`. |
| `seedShifts()` (line 108) | `PortShift::updateOrCreate(['code' => ...], $row + ['is_active' => true])`. |

Idempotency: every write is keyed (`updateOrCreate`, `firstOrCreate`, `syncWithoutDetaching`), so a second run changes nothing, and a changed value in the JSON follows into existing rows.

### 3.6 `DemoPeopleSeeder` (`database/seeders/DemoPeopleSeeder.php`)

| Member | What it does |
| --- | --- |
| `private string $passwordHash` | One bcrypt hash, shared by all demo users. |
| `run()` (line 32) | `ensureAllowed()`; `$password = config('seeding.demo_user_password')`; `Hash::make(filled($password) ? $password : Str::random(40))` (random when no password is configured, so nobody can sign in); then in one transaction: (a) the 10 `role_users` (`assignRole($this->user(...), $row['role'], $this->unit($row['unit']))`); (b) the 12 `pilots`: user + `MARINE_PILOT` role in the Marine Services unit + `PilotProfile::firstOrCreate(['user_id'], ['pilot_rank'])`; (c) the 10 `tugs`: `Tugboat::firstOrCreate(['name'], tug_power, tug_power_unit, capacity, status)`, the master user with `TUG_MASTER` role and `TugMasterProfile::firstOrCreate(['user_id'], ['assigned_tugboat_id'])`; (d) `seedSchedules`. |
| `user(string $username, string $name): User` | `User::firstOrCreate(['username'], ['name', 'password_hash' => shared hash, 'is_active' => true, 'must_change_password' => true])`. Every demo user must change the password at first sign-in. An existing user is not touched (its password stays). |
| `unit(string $unit): OrganizationUnit` | `HARBOUR_AUTHORITY` / `MARINE_SERVICES` units by `unit_type`; otherwise the `TERMINAL` unit whose terminal has that code. `firstOrFail`. |
| `assignRole(User $user, string $role, OrganizationUnit $unit)` | Creates a `UserRoleAssignment` (`role`, `organization_unit_id`, `unit_type`, `active_from = now()`) unless an active one (`active_to` NULL) with the same user, role and unit exists. Note: it does not check "one active role per user" (DEC-36, `uq_ura_active`); each demo user is given exactly one role, so the database rule holds. |
| `seedSchedules(array $people)` (line 112) | Takes the shift ids ordered by `start_time`; the start day is `Carbon::today(<port timezone>)` from `port_profiles` id 1; pilot ids and master ids ordered by username; the "scheduled by" users from `people.scheduled_by` (`pilotage.officer`, `tug.officer`). For each of `schedule_days` (7) days and each person: `PilotSchedule::firstOrCreate` / `TugMasterSchedule::firstOrCreate` with shift `shiftIds[(index + day) % shiftCount]`, `created_by` = the scheduler. So each person works one shift a day, rotating. |

### 3.7 `config/seeding.php`

| Key | Value | Meaning |
| --- | --- | --- |
| `demo_port` | `env('SEED_DEMO_PORT', env('APP_ENV', 'production') !== 'production')` | On when `SEED_DEMO_PORT` is set to a true value, otherwise on outside production. A **missing** `APP_ENV` counts as production (a bug found and fixed during P1-B6: the first version treated a missing one as non-production). |
| `demo_user_password` | `env('DEMO_USER_PASSWORD')` | The shared password of the demo users. Empty/unset means a random hash. |

Documented in `.env.example` (lines 80-84): `# SEED_DEMO_PORT=` (commented out) and `DEMO_USER_PASSWORD=` (empty). Only config files read `env()` (F01 rule).

### 3.8 The data files (`database/seeders/data/`)

**`reference-seed.json`** (`spec_version` `"1.0"`; 8.5 KB). Sections named after tables, values as in the database (CT-18 format). Sections and counts:

| Section | Rows | Columns in each row | What is in it |
| --- | --- | --- | --- |
| `imdg_classes` | 20 | `code`, `name` | IMDG dangerous-goods classes (`1.1`, `1.2`, `3`, …) |
| `container_size_types` | 13 | `code`, `length_ft`, `is_high_cube`, `type_group`, `teu_factor` | `22G1`, `22R1`, `22T1`, `22U1`, `22P1`, `42G1`, `42R1`, `42T1`, `42U1`, `42P1`, `45G1`, `45R1`, `L5G1` |
| `vessel_types` | 6 | `code`, `name` | `CONTAINER`, `GENERAL_CARGO`, `BULK_CARRIER`, `TANKER`, `LNG_CARRIER`, `RO_RO` |
| `cargo_types` | 7 | `code`, `name`, `category`, `imdg_class`, `min_temperature`, `max_temperature` | `CONTAINERS`, `GENERAL`, `BREAK_BULK`, `DRY_BULK`, `LIQUID_BULK`, `LNG`, `RO_RO` |
| `yard_zone_types` | 4 | `code`, `name`, `zone_detail` | `GENERAL`, `REEFER`, `DANGEROUS_GOODS`, `OOG` |
| `storage_types` | 4 | `code`, `name`, `detail_kind` | `CONTAINER_YARD`, `WAREHOUSE`, `TANK`, `OPEN_STORAGE` |
| `capability_types` | 4 | `code`, `name`, `unit` | `LIFTING_CAPACITY` (TONNES), `OUTREACH` (METRES), `MOVES_RATE`, `FLOW_RATE` |
| `equipment_categories` | 6 | `code`, `name`, `equipment_role` | `STS_CRANE`, `MOBILE_HARBOUR_CRANE`, `RTG`, `REACH_STACKER`, `TERMINAL_TRACTOR`, `PIPELINE` |
| `status_types` | 8 | `component_type`, `code`, `name` | for `BERTH`, `PILOT`, `TUG`, `TERMINAL_OPS`: `STARTED` ("In progress") and `DONE` ("Executed") |
| `external_systems` | 1 | `code`, `name` | `EGMSW` Egyptian Maritime Single Window |
| `port_settings` | 2 | `setting_key`, `value_type`, `value`, `description` (optional `min`, `max`) | `reminder_lead_minutes` INT `120` ("Lead time of the pre-execution reminder"); `min_app_version` STRING `1.0.0` ("Oldest mobile app version allowed; older apps get 426") |

The `port_settings` section is read by `PortSettingsSeeder` only; the other ten by `ReferenceDataSeeder`. Neither of the two settings rows carries `min`/`max`, so `min_value` and `max_value` stay NULL today (the columns exist for the AI keys that P2-F9 will add). This file is a copy of the contract file `PortHub/contracts/db/reference-seed.json` (CT-18; the two differ only in line endings, checked with `diff` after removing `\r`), and the copy in `docs/reference/contracts/reference-seed.json` must be byte-identical (a test checks that, section 7). Reason for an in-app copy: the app must not depend on a contract folder in production (DEC-59 reading, conflict C-3 in the plan).

**`damietta-demo-port.json`** (16.7 KB, the same fixture the AI team uses for AI-015): keys:

| Key | Content |
| --- | --- |
| `note` | "Demo port at the scale of the reference port (Damietta: 8 terminals, 36 berths). Fictional data for tests; also the fixture for AI-015." |
| `depth_datum` | `"LAT"` (applied to every berth) |
| `terminals` | 8 rows `{code, name, terminal_type}`: `DCT`, `DIPCO`, `DCHC` (type `CONTAINER`); `GENCARGO`, `DRYBULK`, `GRAIN`, `LIQBULK`, `RORO` (type `NON_CONTAINER`) |
| `berths` | 36 rows `{code, name, terminal_codes[], length, water_depth, max_vessel_loa, latitude, longitude}`; codes `B01`…; per terminal DCT 6, DIPCO 7, DCHC 5, GENCARGO 6, DRYBULK 4, GRAIN 4, LIQBULK 3, RORO 3 = 38 links; two berths are shared: `B06` (DCT and DIPCO) and `B17` (DCHC and GENCARGO) (DEC-51, shared berths) |
| `navigation_channel` | `{name: "Damietta Entrance Channel", depth: 15, depth_datum: "LAT", width: 250, speed_limit_knots: 8, waypoints: [4 rows {label, latitude, longitude}: Fairway Buoy, Outer Gate, Inner Gate, Turning Basin]}` |
| `port_shifts` | 3 rows `{code, name, start_time, end_time}`: `NIGHT` 00:00-08:00, `DAY` 08:00-16:00, `EVENING` 16:00-00:00 |
| `people.role_users` | 10 rows `{username, name, role, unit}`: `port.gm` (PORT_GM), `port.staff` (PORT_STAFF), `safety.officer` (MARINE_SAFETY_OFFICER) in `HARBOUR_AUTHORITY`; `pilotage.manager`, `pilotage.officer`, `tug.manager`, `tug.officer` in `MARINE_SERVICES`; `terminal.admin`, `terminal.officer`, `terminal.supervisor` in terminal `DCT` |
| `people.pilots` | 12 rows `{username: pilot.01…, name, pilot_rank}` ranks FIRST ×3, SECOND ×4, THIRD ×5 |
| `people.tugs` | 10 rows `{name, tug_power, tug_power_unit: BOLLARD_PULL_TONNES, capacity: null, status: AVAILABLE, master_username: tugmaster.01…, master_name}` (Damietta 1-4, Nile Star, Nile Giant, Delta Pusher, Delta Fighter, Port Said 7, Port Said 9) |
| `people.schedule_days` | 7 |
| `people.scheduled_by` | `{pilots: "pilotage.officer", tug_masters: "tug.officer"}` |
| `people.note` | "Fake names. Usernames are the demo sign-in names; the password is DEMO_USER_PASSWORD." |

The 10 role users plus `MARINE_PILOT` (pilots) and `TUG_MASTER` (tug masters) make all **12 roles** (`Role` enum, F09).

### 3.9 The `port_settings` min/max migration

`database/migrations/2026_11_03_183535_alter_port_settings_add_min_max.php`: adds `min_value DECIMAL(18,6) NULL` after `value_type` and `max_value DECIMAL(18,6) NULL` after `min_value`, with comments "Lowest/Highest allowed value; NULL = no lower/upper limit (CT-18)". Additive, no CHECK (a `min <= max` CHECK was planned and dropped because the guard tests fix the design at 129 CHECK constraints; enforcement belongs to P2-F9 or the typed settings editor, P2-F1). `down()` drops both columns. It is the last of the 97 migrations. `PortSetting` model: `min_value` and `max_value` in `#[Fillable]` and cast `decimal:6`; `PortSettingFactory` was updated. Workers' grants need no change (table-level SELECT in `database/grants/workers.sql`). Because the migration touches a worker-visible table, the PR carries the `ai-impact` label (F02).

## 4. Why this design

| Decision | Reason | Source |
| --- | --- | --- |
| Upsert **by code**, insert if missing | A second run must add nothing; a corrected name must follow the file. | CT-18 section 3; P1-B6 |
| `port_settings` value follows the file only while `updated_by` is NULL | Once the Port Admin edits a setting, the seeder must never overwrite it; description, min and max stay under the contract's control. | CT-18 section 3 |
| `min_value` / `max_value` columns on `port_settings` | The typed settings editor and the AI keys need allowed ranges; the alternative (a separate rules table or code) was rejected. | DEC-59 Option 1 (Closed 2026-11-04: "`port_settings` has `min_value` and `max_value` (added by P1-B6, backend PR #48)"), CT-18 v1.1 (Approved 2026-11-04), lead's answer Q1 (2026-11-03: include the columns in B6) |
| `PortProfileSeeder` runs **only** with the demo port | The `port_profiles` row belongs to the SaaS registry and is written by provisioning in production. | K1, Marwan 2026-11-03 "demo only" |
| One user per role, demo-only, guarded, forced password change | Teams need to sign in as every role. Real user creation stays with P2-E1. | C-1 and Q4 (Marwan 2026-11-03: a user for each of the 12 roles) |
| `DEMO_USER_PASSWORD` from the environment; random hash when empty | No secret in a tracked file; an unconfigured environment gets users nobody can use. | Q2 (Marwan 2026-11-03) |
| Production guard in `ensureAllowed()` on **every** demo seeder | `db:seed --class=X` bypasses `DatabaseSeeder`; the guard must live in the seeder. | review fix M2 |
| `test.user` only outside production | Its password is the factory default. | security hook finding, step 1 |
| In-app copy of `reference-seed.json` plus a drift test | Production must not read a contract folder; the test keeps it equal to the docs copy. | C-3 |
| Volume: 8 terminals, 36 berths, 12 pilots, 10 tugs, 7 days of schedules, fake names | Believable data at the reference port's size; the AI team uses the same fixture. | Q3 (Marwan 2026-11-03), AI-015 |
| Schedule start day in the **port's** timezone | "Today" for the port is not "today" in UTC. | review fix S2 |
| Plural names from the migrations (`port_settings`) | CT-17 section 2 (the SQL file says `port_setting`). | C-2 |
| Rejected | Seeding the AI rows here (P2-F9 owns them, they wait for the AI spec); making `DatabaseSeeder` always run the demo; editing the readiness pack `FT-02` from here (it mirrors a PortHub file and would show as drift). | plan sections 1, 7 |

## 5. How it connects

- **Used by:** every developer, QA and the shared dev server (DEC-47: a dev server seeded on each merge to `develop`, target P6-R6); the mobile sign-in chain tasks P1-C5, P1-C3, P1-C4 need the `port_profiles` row and `min_app_version` (planned, F15); the AI team's tests reuse `damietta-demo-port.json` (AI-015); FT-02's readiness pack should list the 32 demo users (its update was left to the lead, step 7).
- **Uses:** models of Access (`User`, `OrganizationUnit`, `UserRoleAssignment`) and MasterData (`PortProfile`, `PortSetting`, `Terminal`, `Berth`, `NavigationChannel`, `ChannelWaypoint`, `PortShift`, `PilotProfile`, `PilotSchedule`, `Tugboat`, `TugMasterProfile`, `TugMasterSchedule`, and the reference models), `Yard\Models\YardZoneType`. The seeders are in the `Database\Seeders` namespace (outside `app/Modules`), so the module layer rule does not apply to them.
- **Depends on:** the schema and models (F05, F06); `Role` enum values (F09).
- **Contracts:** CT-18 (seed data and `port_settings` keys), CT-17 (schema, the min/max addition is a minor version), DEC-59. The published `schema.sql` in PortHub must be regenerated after the min/max migration (F14; see Open points).
- **Sign-in:** demo users sign in through F07 with `username` and `DEMO_USER_PASSWORD`, and are then forced through the password change gate (F08).

## 6. Data

Tables written (all owned by the Backend, CT-15):

| Seeder | Tables |
| --- | --- |
| `DatabaseSeeder` | `users` (`test.user`) |
| `PortSettingsSeeder` | `port_settings` |
| `ReferenceDataSeeder` | `imdg_classes`, `container_size_types`, `vessel_types`, `cargo_types`, `yard_zone_types`, `storage_types`, `capability_types`, `equipment_categories`, `status_types`, `external_systems` |
| `PortProfileSeeder` | `port_profiles` (id 1) |
| `DemoPortSeeder` | `organization_units`, `terminals`, `berths`, `berth_terminals`, `navigation_channels`, `channel_waypoints`, `port_shifts` |
| `DemoPeopleSeeder` | `users`, `user_role_assignments`, `pilot_profiles`, `tugboats`, `tug_master_profiles`, `pilot_schedules`, `tug_master_schedules` |

Constraints that back the rules: unique `port_settings.setting_key`; `uq_ura_active` (one active role per user, DEC-36) and `chk_ura_role_matches_unit` (role matches the unit type); `users.username` unique and lower-case (`chk_user_username_lower`); berth must have at least one terminal (enforced by the endpoint later; the seeder links every berth). Table names (from the models' `#[Table]`): `navigation_channels` and `channel_waypoints`.

**Result of a real run** on an empty own database `atlantech_testing_g4` (`migrate:fresh --seed`, 2026-11-04): port_profiles 1, terminals 8, berths 36, berth_terminals 38, organization_units 10, port_shifts 3, imdg_classes 20, port_settings 2, users 33, user_role_assignments 32, pilot_profiles 12, tugboats 10, pilot_schedules 84, tug_master_schedules 70. A second `db:seed` left users 33, berths 36, pilot_schedules 84 unchanged.

## 7. Tests

Files in `tests/Feature/Seeders/` (35 tests, all passed on 2026-11-04 on `atlantech_testing_g4`, about 63 s). Each test wraps its work in `DB::beginTransaction()` and rolls back, on the MySQL schema database.

**`ReferenceDataSeederTest.php`** (5): loads every section with the expected counts (20/13/6/7/4/4/4/6/8/1); a second run adds nothing and restores a changed `imdg_classes` name; the same for `status_types` whose key is (`component_type`, `code`); no `cargo_types.imdg_class` is orphaned; **drift test**: the file `database/seeders/data/reference-seed.json` equals `docs/reference/contracts/reference-seed.json` byte for byte (fails after a contract sync that changes the file until the app copy is updated).

**`PortProfileAndSettingsSeederTest.php`** (6): `PortProfileSeeder` creates one row with id 1, the placeholder `public_id`, `EGDAM`, `Damietta Port`; leaves an existing row ("From SaaS") alone; `min_app_version` is STRING `1.0.0` and `reminder_lead_minutes` INT `120`; two runs give 2 settings; a value changed by a user (`updated_by` set) is kept (`2.3.0`) while the description refreshes; an untouched value (`updated_by` NULL) moves to the new default.

**`PortSettingsMinMaxTest.php`** (4): the two columns exist and are nullable; keys without min/max stay NULL; min and max are **always** refreshed from the file even after the Port Admin changed the value (the value `45` is kept, min/max set to `1`/`2` before are reset to NULL); a spec row with `min: 1, max: 120` (written to a temp file, run through `PortSettingsSeeder::run($path)`) is stored as `1.0` / `120.0`.

**`DemoPortStructureTest.php`** (6): 8 terminals (3 `CONTAINER`) and 36 berths; every berth has at least one terminal and at least one is shared; 8 terminal units + 1 `HARBOUR_AUTHORITY` + 1 `MARINE_SERVICES`; every berth uses the channel's datum and the channel has 4 waypoints; 3 shifts that cover the 1440 minutes of a day without overlap; two runs change nothing (8, 36, 38 links, 10 units, 4 waypoints).

**`DemoPeopleSeederTest.php`** (14 with data sets; `beforeEach` runs `DemoPortSeeder` with `seeding.demo_user_password = null`): every `Role` case has an active assignment; 12 pilots, 10 tugs, each tug has its own master, 12 `MARINE_PILOT` and 10 `TUG_MASTER` assignments; 84 pilot schedules and 70 tug-master schedules from `2026-11-10` to `2026-11-16` (time frozen) with all 3 shifts used on day one; without a password every user has `must_change_password`, there are 32 users, `Hash::check('', …)` is false and all hashes are identical (one hash); with `DEMO_USER_PASSWORD` set `pilot.01` can verify it; two runs duplicate nobody (32 users, 32 assignments, 84 schedules); `DatabaseSeeder` loads the demo port only when the config allows (off: no berths added; on: 36 berths and 33 users); `ensureAllowed` refuses `DemoPortSeeder`, `DemoPeopleSeeder` and `PortProfileSeeder` by class name with the message containing `SEED_DEMO_PORT`; the config default is false for `production` and for a missing `APP_ENV`, true for `local`, true again for production with `SEED_DEMO_PORT=true` (the test rewrites `$_ENV`, `$_SERVER` and `putenv` and restores them).

A detail in the "loads the demo port only when the config allows it" test: with `demo_port` off it asserts `port_profiles` count is 1, but that row comes from the `beforeEach` `DemoPortSeeder` run, not from `DatabaseSeeder`. The test deletes berths and terminals only. So it does not prove what a fresh database without demo gets (it gets **no** profile row).

Related tests in other features: `ModelLayerTest` (F06) covers the factories; `ContractsSchemaTest` (F14) names the last migration `2026_11_03_183535_alter_port_settings_add_min_max`.

Run: `vendor/bin/pest tests/Feature/Seeders`.

## 8. Gotchas and invariants

- **Never run seeders on a database you care about.** They are idempotent, but `migrate:fresh --seed` destroys the database. In the Sprint 2 brief, only an own empty database was used.
- **The demo port is off in production and when `APP_ENV` is missing.** To load it on a server on purpose, set `SEED_DEMO_PORT=true` and know that `test.user` is still not created in production.
- **`PortProfileSeeder` throws when demo seeding is off.** Provisioning must write `port_profiles` row 1 in production (not built, F15).
- **Passwords:** an empty `DEMO_USER_PASSWORD` gives unusable hashes **only for users created in that run**. Users already in the database keep their old hash (`firstOrCreate`). Changing `DEMO_USER_PASSWORD` later does not change existing demo users.
- **`DemoPeopleSeeder` creates users with `must_change_password = true`**; after the first sign-in the demo user must call `POST auth/change-password` (F08) before anything else.
- **Reference rows removed from the JSON stay in the database**; and the upsert overwrites hand edits in those tables.
- **`port_settings` rows:** `min_value`/`max_value` are overwritten from the file on every run, so a range set by hand in the database is lost unless it is in the JSON. This matches CT-18 but surprises people.
- **Keep the three copies of `reference-seed.json` in step:** PortHub `contracts/db/`, `docs/reference/contracts/` (copy via `sync-check`), and `database/seeders/data/`. The drift test only compares the last two.
- **`DatabaseSeeder` order:** settings and reference before the demo; do not call `DemoPeopleSeeder` before `DemoPortSeeder` (it throws `ModelNotFoundException` on the missing units).
- **Do not give the fixture different `username` values lightly:** the demo sign-in names are documented to other teams and QA scripts (`readiness/FT-02.md` in PortHub lists the sign-in; it was not updated for the 32 demo users).
- **Fresh Docker does not seed itself.** Neither `Dockerfile` nor `docker-compose.yml` runs `migrate` or `db:seed`. The roadmap's "a fresh environment starts with the demo port loaded" holds only after someone runs `docker compose exec app php artisan migrate --seed` (or `php artisan migrate --seed` under Herd). The project README (`project-backend/README.md`, at the repository root; do not confuse it with `ATLANTECH/README.md`, which is Laravel's untouched default text) does document both commands: `php artisan migrate --seed` for Herd (line 113), `docker compose exec app php artisan migrate --seed` for Docker (line 129), and it says to rerun them after pulling new code (line 132). That is P1-B6 review fix M1. Neither `Dockerfile` nor `docker-compose.yml` mentions them.

## 9. How to extend it

**Add a reference row to an existing section:** add the object to `reference-seed.json` (same columns as the other rows), copy the file to `PortHub/contracts/db/reference-seed.json` (CT-18 is owned jointly: use a PortHub PR) and to `docs/reference/contracts/` (through `node docs/reference/sync-check.mjs --apply` after the shared documents folder has it), update the expected count in `ReferenceDataSeederTest` ("loads every Backend section"), run `vendor/bin/pest tests/Feature/Seeders`. A change in meaning is a CT-18 version change (minor for a new default).

**Add a new reference section/table:** add it to the JSON, add `[Model::class, ['code']]` to `ReferenceDataSeeder::SECTIONS` **after** any table it references, make sure the table has a unique key on the upsert keys, add its count to the test.

**Add a port setting (Backend keys):** add a row to `port_settings` in the JSON (`setting_key` ≤ 80 chars, `value_type` one of `INT`, `DECIMAL`, `BOOL`, `STRING`, `JSON`, `value` as a string, `description`, optional `min` and `max`). The AI team's keys are not added here: they arrive with task P2-F9 from `contracts/ai/seed-spec.json`.

**Add a demo terminal/berth/person:** edit `damietta-demo-port.json` (berth `terminal_codes` must name existing terminals; a person needs a `unit` that exists: `HARBOUR_AUTHORITY`, `MARINE_SERVICES`, or a terminal code), then update the counts in `DemoPortStructureTest` and `DemoPeopleSeederTest`. Check `DemoPeopleSeederTest`'s "12 roles" test still holds.

**Add a new seeder:** put it in `database/seeders`, call it from `DatabaseSeeder` (before the demo block if production needs it; inside the demo block if it is demo-only), call `DemoPortSeeder::ensureAllowed()` first if it is demo-only, key every write so a second run changes nothing, and add a test in `tests/Feature/Seeders` with the `useSchemaDatabase()` + transaction pattern.

**First real Port Admin (not built):** the P2-E1 plan adds `FirstPortAdminSeeder` reading `.env` values and creating a `PORT_GM` only when none exists (F15).

## 10. Try it

From `project-backend/ATLANTECH`, PowerShell. Real runs below used an own empty database `atlantech_testing_g4` created in the Docker MySQL (`CREATE DATABASE atlantech_testing_g4`), shell variables set as in the brief, on 2026-11-04.

```powershell
$env:DB_DATABASE="atlantech_testing_g4"; $env:DB_SCHEMA_TEST_DATABASE="atlantech_testing_g4"
php artisan migrate:fresh --seed --force
```
Real output ends with: `PortSettingsSeeder DONE`, `ReferenceDataSeeder DONE`, `DemoPortSeeder DONE` (with `PortProfileSeeder DONE` nested inside it), `DemoPeopleSeeder DONE` (about 1.9 s; most of it is the one password hash plus 32 inserts).

```powershell
docker exec atlantech-mysql-1 mysql -uroot atlantech_testing_g4 -e "select count(*) from users; select setting_key, setting_value, value_type, min_value, max_value from port_settings"
```
Real: 33 users; `reminder_lead_minutes 120 INT NULL NULL`; `min_app_version 1.0.0 STRING NULL NULL`.

```powershell
php artisan db:seed --force
```
Real: counts stay the same (users 33, berths 36, pilot_schedules 84).

```powershell
$env:APP_ENV="production"; php artisan db:seed --class=DemoPeopleSeeder --force; Remove-Item Env:APP_ENV
```
Real: `RuntimeException: Demo seeding is off in this environment. Set SEED_DEMO_PORT=true to load the demo port.` and nothing is written.

```powershell
vendor/bin/pest tests/Feature/Seeders
```
Real: `35 passed (82 assertions)` in about 63 s.

Sign in as a demo user: not run (needs the app served and a `DEMO_USER_PASSWORD`; the demo users were created in this run with a random hash on purpose). Expected, with `DEMO_USER_PASSWORD=Secret123!` in `.env` before seeding: `POST /api/v1/auth/login` with `{"username":"port.gm","password":"Secret123!"}` (after `GET /sanctum/csrf-cookie`) answers 200 with `must_change_password: true` (F07, F08).

## 11. Open points

- **Seeding is a manual step, and the README says so** (correction made in the completeness pass: an earlier draft claimed the README had no seeding instructions; that came from searching `ATLANTECH/README.md`, Laravel's default file, instead of `project-backend/README.md`). The project README documents `migrate --seed` for Herd and for Docker (lines 113, 129 and 132). Nothing seeds a fresh Docker environment automatically, so the roadmap's "a fresh environment starts with the demo port loaded" is true only after someone runs `migrate --seed`; that is a documented step, not a gap.
- **Published `schema.sql` is stale** (finding): `PortHub/contracts/db/schema.sql` ends at `2026_10_28_200000_add_deferred_foreign_keys` and contains no `min_value` (`grep -c min_value` gives 0). The P1-B6 plan left publishing to the lead ("step 7"). `ContractsSchemaTest` expects the generator to report the min/max migration as the last one; so the generator is right and the published file is behind. Run `composer contracts:schema` on a PortHub branch (F14).
- **Repo copies of the decisions/contracts are stale** (finding): `docs/reference` shows `DEC-59` as Open and `CT-18` as "Proposed v1.1", while the shared `Code Notes` files and PortHub `main` say DEC-59 Closed (2026-11-04) and CT-18 v1.1 Approved. `node docs/reference/sync-check.mjs` reports the two files as `CHANGED` (real output, 2026-11-04). Run `sync-check --apply` in a task branch of the lead's choosing (not done here).
- **CHAMPION `deployed_at`:** DEC-59's second part says a `CHAMPION` row in the seed carries `deployed_at`; no such row exists in this repo's seed (AI rows arrive with P2-F9).
- **AI seed rows and AI `port_settings` keys:** P2-F9 (Sprint 3), waits for the AI seed spec (AI-009) and CT-18.
- **DEC-30 (numeric thresholds register, AI Team, Open, close-by Sprint 2)** is still Open in the shared decisions file and blocks the port-wide default values of `safety_constraints` that CT-18/CT-25 will seed (F15).
- **First real Port Admin / provisioning of `port_profiles` in production:** not built (P2-E1 plan has `FirstPortAdminSeeder`; provisioning belongs to the SaaS work).
- **`test.user` test:** the "loads the demo port only when the config allows it" test does not prove the no-demo result of `DatabaseSeeder` on a fresh database (see section 7).
- **`DemoPeopleSeeder` does not enforce DEC-36 itself** (relies on the database unique key and on giving one role per user).
