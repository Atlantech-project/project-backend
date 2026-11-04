# F05 · Database schema and migrations

Task origin: P1-B1 (migrations, grants, `schema.sql`), P1-B2 and P1-B3 (reviews and fixes), P1-B4 (constraint tests), DEC-59 (`port_settings` min and max, 2026-11-03). Facts checked against `develop` at `106b40e`. Paths are inside `project-backend/ATLANTECH/` unless stated. The reader should know `A_orientation.md` (modules in A4, vocabulary in A5) and `F01` (strict MySQL and UTC in `config/database.php`).

## 1. What it does

This feature is the **database of the port**: 92 tables, built only by Laravel migrations, with the rules of the design written into MySQL itself so that a bad row is refused even if application code forgets a check.

It gives you:

- **92 port tables** (plus the framework tables) created by 97 migration files: 3 Laravel framework files, 92 one-per-table files, `add_deferred_foreign_keys` and `alter_port_settings_add_min_max`. A fresh database has 100 tables: 92 port tables + `migrations`, `sessions`, `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.
- **Names and types that never vary:** plural table names (CT-17 `table-names.md`), `BIGINT UNSIGNED` ids and foreign keys, explicit names for all 156 foreign keys, InnoDB, `utf8mb4_0900_ai_ci`.
- **Rules inside the database:** 129 named CHECK constraints on 60 tables, 17 stored generated columns (12 of them unique keys over 8 distinct column names), 103 strict ENUM columns, and a microsecond `updated_at` on 24 tables that the API uses as its **version token** (F12).
- **A security boundary for the AI workers:** `database/grants/workers.sql` defines six MySQL roles (one per AI worker, DEC-08) and `php artisan atlantech:worker-accounts` creates the six accounts from `.env` passwords.
- **A published copy of the schema:** `php artisan atlantech:contracts-schema` (`composer contracts:schema`) rebuilds the schema on a scratch database, dumps it and writes `contracts/db/schema.sql` in PortHub for the AI team (CT-17).
- **Tests** that fail if a table, constraint, generated column, grant or the published schema drifts.

It does **not** give you: Eloquent models, enums and factories (F06), seed rows (F13), the `personal_access_tokens` table (not built yet, F15), the AI worker code, or a rule that MySQL cannot hold. Rules like "a berth has at least one terminal" or "the BAPLIE worker fills only the NULL vessel columns" live in application code (later features).

## 2. Request or data flow

There is no HTTP request here. Two flows matter.

**A. A schema change travels like this** (how every migration PR goes):

1. Write or edit a migration in `database/migrations/` (conventions in 3.2). Before the first release the `create_<table>` file is edited in place (P1-B2 answer 1); after release a new `alter_<table>_<change>` file is added and must be additive (CT-17 §4).
2. If a column a worker reads or writes changed, edit `database/grants/workers.sql` in the same PR (header comment, `workers.sql:1-3`).
3. Update the tests that name the thing: the 129-name guard (`DatabaseRulesTest.php:212`), the generated-column list and version-token list (`SchemaTablesTest.php:118,164`), the last migration name and table counts in `ContractsSchemaTest.php:13`.
4. Run the `schema` tests on an empty MySQL database (section 7).
5. Open the PR. `.github/labeler.yml` (repo root, not under `ATLANTECH/`) adds the **`ai-impact`** label because `ATLANTECH/database/migrations/**` or `ATLANTECH/database/grants/**` changed; the AI lead must approve (CT-17 §6; see 3.11).
6. After merge run `composer contracts:schema` and publish `contracts/db/schema.sql` in a **paired PortHub PR** (CT-17 §5). Today that copy is out of date (section 11).

**B. What happens when the schema is built** (`php artisan migrate`, `migrate:fresh`, or `useSchemaDatabase()` in tests):

1. Laravel runs the migrations in file-name order: `0001_01_01_00000x` framework files first, then `2026_10_28_100001` … `100092` (92 port tables, ordered so every foreign key points to a table that already exists), then `2026_10_28_200000_add_deferred_foreign_keys`, then `2026_11_03_183535_alter_port_settings_add_min_max`.
2. Inside each table file, `Schema::create` makes the columns, keys and foreign keys; then `DB::statement('ALTER TABLE … ADD CONSTRAINT chk_… CHECK (…)')` adds the CHECK constraints (3.2).
3. The connection is strict MySQL with session time zone `+00:00` (`config/database.php:60,62`), so a wrong ENUM value is an error, not an empty string.

## 3. Every piece of code

### 3.1 The 97 migration files

| Group | Files | What they make |
| --- | --- | --- |
| Laravel framework | `0001_01_01_000000_create_sessions_table`, `…000001_create_cache_table`, `…000002_create_jobs_table` | `password_reset_tokens` (keyed by `email`, left as Laravel makes it; unused, see 11), `sessions`; `cache`, `cache_locks`; `jobs`, `job_batches`, `failed_jobs`. The default Laravel `users` migration was **replaced** by the port `users` table (CT-17 §3). |
| Port tables | `2026_10_28_100001` … `2026_10_28_100092`, one file per table, named `create_<table>_table` | The 92 tables of the catalogue in 3.3. Numbers `100001-100090` were generated from `PortSync.sql` (one-off Node script, then edited by hand, P1-B1 answer 2); `100091` (`yard_runs`) and `100092` (`baplie_parse_runs`) were written by hand from CT-11 and CT-12. |
| Deferred foreign key | `2026_10_28_200000_add_deferred_foreign_keys` | One foreign key, `feasible_windows.proposal_id → entry_window_proposals.id`, named `fk_fw_proposal` (`…200000…php:15`). See 3.6. |
| Later change | `2026_11_03_183535_alter_port_settings_add_min_max` | Adds nullable `decimal(18,6)` `min_value` and `max_value` after `value_type` on `port_settings` (`…183535…php:19-20`); DEC-59 Option 1, CT-18 v1.1. `down()` drops both columns. This is the **model for a post-release change**: additive, nullable, no data rewrite. |

Every port-table migration has the same shape: `up()` creates the table and adds the CHECKs; `down()` is `Schema::dropIfExists('<table>')`.

### 3.2 How a migration is written here (conventions)

Take `2026_10_28_100005_create_users_table.php` (lines 15-27) and `…100011_create_user_role_assignments_table.php` as the two reference files.

| Convention | What the code does | Why |
| --- | --- | --- |
| Table name | Plural snake case: `users`, `user_role_assignments`. The model is singular (F06). | CT-17 §2; list in `docs/reference/contracts/db/table-names.md`, generated with `Str::snake(Str::pluralStudly(...))`. |
| Primary key | `$table->id()` (BIGINT UNSIGNED auto-increment). Singletons (`port_profiles`, `license_states`) use `unsignedBigInteger('id')->primary()` with default 1 and a CHECK `id = 1`. Link tables have a composite primary key. | CT-17 §2; P1-B2 answer 3 and 8 (the singletons were `tinyint` in `PortSync.sql`). |
| Foreign key columns | `unsignedBigInteger('x_id')`, never `foreignId()->constrained()` in a way that auto-names the key. | Names must match the design. |
| Foreign key declaration | Inside `Schema::create`, `$table->foreign('user_id', 'fk_ura_user')->references('id')->on('users')`, always with an explicit name. Composite keys pass arrays: `foreign(['organization_unit_id','unit_type'], 'fk_ura_unit')->references(['id','unit_type'])->on('organization_units')`. | CT-17 §2 "constraint and index names unchanged". All 155 `foreign()` calls in the create files carry a name (checked by script); a test fails if any database FK ends in `_foreign`. No `ON DELETE CASCADE` anywhere (workers write these tables; the Backend deletes explicitly). |
| Index and unique names | Named as in the design: `uq_ura_active`, `fk_ura_unit`, `idx_ura_unit`; inline uniques keep the column name (`unique('username')`). | Same rule. |
| Dates | `dateTime('created_at')->useCurrent()` (second precision) and, on token tables, `dateTime('updated_at', 6)->useCurrent()->useCurrentOnUpdate()` with the comment "Version token for the 409 check (microseconds)". | The token must change on every write, to the microsecond (DEC-57, F12). |
| ENUMs | `$table->enum('role', [...])` with the exact SQL names. | CT-17 §2: 1:1 with PHP enums (F06). |
| CHECKs | After `Schema::create`, one `DB::statement("ALTER TABLE `t` ADD CONSTRAINT `chk_xx` CHECK (...)")` per rule, named as in `PortSync_base_ddl_fixes.sql`. Laravel's schema builder has no named CHECK, hence raw SQL. | CT-17 §2 (created with the table); lead's answer 1 in P1-B1. |
| Generated columns | `->nullable()->storedAs('IF(`active_to` IS NULL, 1, NULL)')` with the comment `GENERATED (stored): …`; the unique key over it is declared next to the column (`…100011…php:23,26`). | See 3.5. |
| Comments | Column and table comments are kept in UTF-8 (broken characters from the SQL source were fixed, edit sheet G4). | Readable `information_schema`. |
| Case sensitivity | Text rules use `CAST(x AS BINARY)` or `regexp_like(..., 'c')`. | The database collation is case-insensitive (`_ci`), so `username = lower(username)` would always be true (P1-B1 step 2; `chk_user_username_lower`, `chk_br_ports`). |

### 3.3 Catalogue of the 92 tables

Grouped by the module that owns the model (F06; `A_orientation.md` A4). **Migration** is the file number after `2026_10_28_`. **Token** = has the `updated_at DATETIME(6)` version token. **FKs / CHECKs** are the counts declared in that file; **Generated** lists stored generated columns. The purpose lines come from the table and column comments in the migrations and the class diagrams; counts were produced by a script over the migration files and then checked against the live database (100 tables, 156 foreign keys, 129 CHECKs, 17 generated columns, 103 ENUM columns, 24 `datetime(6)` tokens).

**Shared** (1 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100086` | `audit_logs` | Append-only record of every change and refused attempt (actor, entity, before and after state). |  | 1 | 1 |  |

**Access** (4 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100005` | `users` | Port staff and field accounts; sign-in by lowercase `username`, no email (DEC-35). | yes |  | 1 |  |
| `100010` | `organization_units` | Where a user works: the Harbour Authority, Marine Services, or one terminal. |  | 1 | 1 | `single_key` |
| `100011` | `user_role_assignments` | Which role a user holds in which organisation unit; one active role per user (DEC-36). |  | 2 | 2 | `active_assignment_key` |
| `100088` | `user_devices` | Mobile devices a user signed in on, with push token; revoked, never deleted. |  | 1 | 1 |  |

**License** (1 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100003` | `license_states` | Single row: the local copy of the SaaS licence and the resulting FULL/SETUP mode. | yes |  | 2 |  |

**MasterData** (38 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100001` | `navigation_channels` | The approach channel(s): depth, width, speed limit. | yes |  | 1 |  |
| `100002` | `channel_waypoints` | Ordered waypoints (lat/lon) that draw a channel. |  | 1 | 3 |  |
| `100004` | `port_profiles` | Single row: the port's identity, position, time zone and boundary. | yes |  | 3 |  |
| `100006` | `port_settings` | Key/value settings of the port with value type and min/max (DEC-59). | yes | 1 |  |  |
| `100007` | `berths` | Berths with length, depth, maximum vessel length and status. | yes |  | 4 |  |
| `100008` | `terminals` | Terminals of the port (container or non-container) with boundary. | yes |  |  |  |
| `100009` | `berth_terminals` | Which terminals may use a berth (link table; shared berths have several rows). |  | 2 |  |  |
| `100012` | `pilot_profiles` | Pilot details of a user (rank); one row per pilot user. | yes | 1 |  |  |
| `100013` | `pilot_rank_limits` | Largest vessel length each pilot rank may handle (settings screen). | yes |  | 1 |  |
| `100014` | `port_shifts` | The fixed port shifts (code, start and end time). | yes |  | 1 |  |
| `100015` | `pilot_schedules` | Which pilot works which fixed shift on which day. |  | 3 |  |  |
| `100016` | `tugboats` | The port tug fleet: power, capacity, status. | yes |  | 1 |  |
| `100017` | `tug_master_profiles` | Tug-master details of a user, including the assigned tugboat. | yes | 2 |  |  |
| `100018` | `tug_master_schedules` | Which tug master works which fixed shift on which day. |  | 3 |  |  |
| `100019` | `container_size_types` | Container size/type list (length, high cube, TEU factor, reefer flag). |  |  | 2 | `is_reefer` |
| `100020` | `containers` | Container identity (ISO number and size type) only. |  | 1 | 1 |  |
| `100021` | `vessel_types` | Vessel type list (code, name). |  |  |  |  |
| `100022` | `vessels` | Vessel master data: IMO number, dimensions, call sign, flag. | yes | 1 | 3 |  |
| `100023` | `imdg_classes` | Fixed list of IMDG dangerous-goods classes. |  |  |  |  |
| `100024` | `cargo_types` | Cargo type list with category, IMDG class and temperature range. |  | 1 | 1 |  |
| `100025` | `storage_types` | Storage type list (tank, warehouse, container yard). |  |  |  |  |
| `100026` | `storage_facilities` | Storage facilities of a terminal with capacity, occupancy and generated available capacity. | yes | 2 | 7 | `available_capacity` |
| `100027` | `terminal_cargo_types` | Which cargo types each terminal handles (link table). |  | 2 |  |  |
| `100028` | `storage_facility_cargo_compatibilities` | Which cargo types a storage facility may hold (link table). |  | 2 |  |  |
| `100029` | `ml_models` | The four machine-learning models the AI uses (M2, M3, M4, forecast bias correction). |  |  |  |  |
| `100030` | `ml_model_versions` | Versions of each model with status; at most one CHAMPION per model. |  | 1 | 3 | `champion_key` |
| `100031` | `external_systems` | External systems that submit berthing requests through adapters. |  |  |  |  |
| `100045` | `tank_details` | Extra columns of a tank facility (temperature, pressure). |  | 1 | 3 |  |
| `100046` | `warehouse_details` | Extra columns of a warehouse facility (temperature range, monitoring). |  | 1 | 2 |  |
| `100054` | `capability_types` | Equipment capability kinds (code, name, unit). |  |  |  |  |
| `100055` | `equipment_categories` | Equipment category list (code, name, role). |  |  |  |  |
| `100056` | `equipment` | Equipment fleet per terminal with quantities in maintenance or blocked. | yes | 2 | 1 | `available_quantity` |
| `100057` | `equipment_capabilities` | Capability values of a piece of equipment (link table with value). |  | 2 | 1 |  |
| `100058` | `equipment_cargo_compatibilities` | Which cargo types a piece of equipment can handle (link table). |  | 2 |  |  |
| `100059` | `constraint_types` | Catalogue of safety constraint kinds (unit, comparator, hard safety limit). |  |  |  |  |
| `100060` | `safety_constraints` | Concrete safety limits by scope (terminal, berth, vessel type, IMDG class, equipment), with validity period. | yes | 8 | 1 |  |
| `100061` | `terminal_productivity_profiles` | Handling-rate inputs per terminal and vessel type for the service-time calculation. |  | 2 | 3 |  |
| `100080` | `status_types` | Allowed field statuses per component type (BERTH, TUG, ...). |  |  |  |  |

**Environment** (6 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100033` | `forecast_runs` | One weather/sea forecast pull (source, type, staleness). |  | 2 | 3 |  |
| `100034` | `tide_constituent_sets` | Versioned sets of tidal constituents; only one set active. |  |  |  | `active_key` |
| `100035` | `tide_prediction_runs` | One in-house harmonic tide computation over a horizon. |  | 1 | 1 |  |
| `100067` | `environment_forecast_points` | Time series values (wind, waves, visibility, current) of a forecast run. |  | 1 | 2 |  |
| `100068` | `tide_constituents` | Amplitude, phase and speed of each tidal constituent in a set. |  | 1 | 1 |  |
| `100069` | `tide_prediction_points` | Predicted tide heights of a prediction run. |  | 1 |  |  |

**Berthing** (3 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100032` | `berthing_requests` | A vessel's request to berth: ETA, drafts, tonnage, ports, status, source. | yes | 4 | 5 |  |
| `100062` | `berthing_request_cargos` | Cargo lines of non-container calls (operation, quantity, unit). |  | 2 | 1 |  |
| `100063` | `berthing_request_status_histories` | Every status change of a berthing request (who, when, note). |  | 2 | 1 |  |

**Baplie** (4 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100064` | `baplie_files` | Uploaded BAPLIE load files with hash, parse status and manual-correction flags. | yes | 4 | 2 | `live_hash_key`, `active_request_key` |
| `100065` | `baplie_parse_issues` | Diagnostics found while parsing a BAPLIE file. |  | 1 |  |  |
| `100066` | `container_manifest_entries` | Per-call container data from a BAPLIE (weight, stowage, ports, IMDG, reefer, OOG). |  | 3 | 6 |  |
| `100092` | `baplie_parse_runs` | Job rows for the BAPLIE parser worker (CT-12). |  | 2 | 3 | `active_key` |

**Recommendation** (15 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100036` | `feasible_windows` | The candidate time windows of an entry-window proposal. |  |  | 1 |  |
| `100037` | `entry_window_proposals` | Part 1 output: proposed safe entry windows, confirmed or rejected by staff. | yes | 7 | 4 | `confirmed_key` |
| `100038` | `solver_runs` | Job rows for the Part 2 port-wide joint solve (CT-10). |  | 1 | 4 |  |
| `100039` | `recommendations` | A Part 2 recommendation for a berthing request with status and purpose. | yes | 3 |  | `active_key` |
| `100040` | `rationale_templates` | Fixed versioned text templates for explanations (never generated by an LLM). |  |  | 1 | `active_code` |
| `100041` | `recommendation_options` | Ranked options of a recommendation: berth, pilot, times, service-time estimate. |  | 4 | 2 |  |
| `100070` | `blocked_periods` | Periods blocked inside a proposal and the constraint that limited them. |  | 2 | 1 |  |
| `100071` | `demand_forecast_slots` | Forecast of arrivals and congestion per time slot. |  | 1 | 2 |  |
| `100072` | `recommendation_option_tugs` | Tugboats proposed for an option (link table). |  | 2 |  |  |
| `100073` | `solver_run_model_versions` | Which model versions a solver run used (link table). |  | 2 |  |  |
| `100074` | `objective_terms` | Catalogue of solver objective terms agreed with the AI team. |  |  |  |  |
| `100075` | `recommendation_overrides` | Typed staff overrides of an option (berth, pilot, tug, start time). |  | 8 | 4 |  |
| `100076` | `recommendation_rationale_factors` | Constraints and objective terms that drove an option. |  | 3 | 1 |  |
| `100089` | `part1_runs` | Job rows for the AI Part 1 run of a berthing request (CT-09). |  | 2 | 1 |  |
| `100090` | `recommendation_reviews` | Which user viewed which recommendation and when. |  | 2 |  |  |

**Plan** (2 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100042` | `operational_plans` | The confirmed plan of a berthing request (at most one active per request). | yes | 3 |  | `active_key` |
| `100043` | `plan_components` | One dispatched unit of a plan (berth or tug) with assignee and reservation state. | yes | 6 | 3 |  |

**Yard** (12 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100044` | `storage_movements` | Cargo moved in or out of a storage facility. |  | 3 | 2 |  |
| `100047` | `yard_zone_types` | Yard zone type list; `zone_detail` says which detail table (NONE, REEFER, DANGEROUS_GOODS, OOG) zones of the type use. |  |  |  |  |
| `100048` | `yard_zones` | Zones of a container yard facility. |  | 2 | 1 |  |
| `100049` | `dg_zone_allowed_classes` | IMDG classes allowed in a dangerous-goods zone (link table). |  | 2 | 1 |  |
| `100050` | `oog_zone_details` | Limits of an out-of-gauge zone. |  | 1 | 2 |  |
| `100051` | `reefer_zone_details` | Power plugs and type of a reefer zone. |  | 1 | 2 |  |
| `100052` | `yard_blocks` | Blocks inside a yard zone. |  | 1 |  |  |
| `100053` | `yard_slots` | Bay/row/tier slots of a block with status and container. | yes | 2 | 2 |  |
| `100077` | `terminal_recommendations` | Per-terminal output of a recommendation (timing, yard placement, storage allocation) with decision. | yes | 5 | 5 |  |
| `100078` | `storage_allocation_items` | Storage facility allocations proposed for cargo (out of MVP, DEC-55). |  | 3 | 2 |  |
| `100079` | `yard_placement_items` | Slot/block placements proposed for manifest entries. |  | 4 | 1 |  |
| `100091` | `yard_runs` | Job rows for the yard-placement worker (CT-11). |  | 2 | 3 | `active_key` |

**Dispatch** (1 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100087` | `notification_logs` | Every notification attempt (channel, status, acknowledgement, failure). |  | 1 | 3 |  |

**Execution** (2 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100081` | `actual_status_updates` | Field status reports from the mobile app for a plan component. |  | 3 |  |  |
| `100082` | `deviation_logs` | Planned versus actual differences detected for a component (deferred feature). |  | 2 |  |  |

**Learning** (3 tables)

| Migration | Table | Purpose | Token | FKs | CHECKs | Generated |
| --- | --- | --- | --- | --- | --- | --- |
| `100083` | `drift_measurements` | Model drift metrics with threshold and generated drifted flag. |  | 1 | 1 | `is_drifted` |
| `100084` | `retraining_runs` | Champion versus challenger retraining history and review. |  | 4 | 3 | `review_pending` |
| `100085` | `evaluation_metrics` | Evaluation metrics of model versions or retraining runs. |  | 2 | 2 |  |

### 3.4 Catalogue of the 129 named CHECK constraints

Rule texts are paraphrased from the SQL in the migration (read the migration for the exact expression). Names come from `PortSync_base_ddl_fixes.sql` (113), Report A (8), FCM changes (2), CT-11 (3) and CT-12 (3). Sixty tables carry at least one; the Execution tables have none. `DatabaseRulesTest` breaks 17 of them on purpose (3.13) and guards the whole list by name.

| Table | Constraint | Rule |
| --- | --- | --- |
| `audit_logs` | `chk_al_states` | a CREATE has no before-state; a DELETE has no after-state |
| `users` | `chk_user_username_lower` | username is lowercase (binary compare) and at least 3 characters |
| `organization_units` | `chk_org_unit_terminal` | `terminal_id` is set exactly when the unit type is TERMINAL |
| `user_role_assignments` | `chk_ura_role_matches_unit` | the role belongs to the unit type (authority, marine services, terminal roles) |
| `user_role_assignments` | `chk_ura_period` | `active_to` is NULL or after `active_from` |
| `user_devices` | `chk_ud_push_revoked` | a revoked device keeps no push token |
| `license_states` | `chk_license_state_singleton` | `id` is 1 (one row only) |
| `license_states` | `chk_license_state_reason` | `read_only_reason` is `NONE` exactly when the mode is FULL or SETUP |
| `navigation_channels` | `chk_channel_dimensions` | depth, width and speed limit are positive |
| `channel_waypoints` | `chk_waypoint_lat` | latitude within -90..90 |
| `channel_waypoints` | `chk_waypoint_lon` | longitude within -180..180 |
| `channel_waypoints` | `chk_waypoint_order` | sequence order is positive |
| `port_profiles` | `chk_port_profile_lat` | latitude within -90..90 |
| `port_profiles` | `chk_port_profile_lon` | longitude within -180..180 |
| `port_profiles` | `chk_port_profile_singleton` | `id` is 1 (one row only) |
| `berths` | `chk_berth_dimensions` | length, water depth and max vessel length are positive |
| `berths` | `chk_berth_lat` | latitude NULL or within -90..90 |
| `berths` | `chk_berth_loa_fits` | max vessel length is not above the berth length |
| `berths` | `chk_berth_lon` | longitude NULL or within -180..180 |
| `pilot_rank_limits` | `chk_pilot_rank_limit_loa` | max vessel length NULL or positive |
| `port_shifts` | `chk_port_shift_times` | end time differs from start time |
| `tugboats` | `chk_tugboat_power` | tug power is positive |
| `container_size_types` | `chk_cst_length` | length is 10, 20, 40 or 45 ft |
| `container_size_types` | `chk_cst_teu` | TEU factor is positive |
| `containers` | `chk_container_number` | ISO 6346 shape: 3 letters, U, J or Z, 7 digits, upper case |
| `vessels` | `chk_vessel_dims` | LOA, beam, draft positive and beam smaller than LOA |
| `vessels` | `chk_vessel_imo` | IMO number is exactly 7 digits |
| `vessels` | `chk_vessel_tonnage` | net tonnage and moulded depth NULL or positive |
| `cargo_types` | `chk_ct_temp` | min temperature not above max temperature |
| `storage_facilities` | `chk_sf_area` | area NULL or positive |
| `storage_facilities` | `chk_sf_capacity` | capacity NULL or positive; occupancy, reserved and blocked are not negative |
| `storage_facilities` | `chk_sf_capacity_sum` | occupancy + reserved + blocked do not exceed capacity |
| `storage_facilities` | `chk_sf_floor_load` | max floor load NULL or positive |
| `storage_facilities` | `chk_sf_lat` | latitude NULL or within -90..90 |
| `storage_facilities` | `chk_sf_lon` | longitude NULL or within -180..180 |
| `storage_facilities` | `chk_sf_yard_rules` | a CONTAINER_YARD facility is a container terminal facility with no capacity and no occupancy |
| `ml_model_versions` | `chk_mlv_champion_deployed` | a CHAMPION version has `deployed_at` |
| `ml_model_versions` | `chk_mlv_version` | version number positive; training row count NULL or not negative |
| `ml_model_versions` | `chk_mlv_window` | training window end after its start |
| `tank_details` | `chk_td_kind` | detail kind is TANK |
| `tank_details` | `chk_td_pressure` | max pressure NULL or positive |
| `tank_details` | `chk_td_temp` | min temperature not above max |
| `warehouse_details` | `chk_wd_kind` | detail kind is WAREHOUSE |
| `warehouse_details` | `chk_wd_temp` | min temperature not above max |
| `equipment` | `chk_eq_quantity` | quantity positive; maintenance + blocked do not exceed quantity |
| `equipment_capabilities` | `chk_ecap_value` | capability value not negative |
| `safety_constraints` | `chk_sc_period` | `active_to` NULL or after `active_from` |
| `terminal_productivity_profiles` | `chk_tpp_period` | `valid_to` NULL or not before `valid_from` |
| `terminal_productivity_profiles` | `chk_tpp_unit` | container terminals use MOVES_PER_HOUR and only they do |
| `terminal_productivity_profiles` | `chk_tpp_values` | rate positive; overhead not negative |
| `forecast_runs` | `chk_fr_horizon` | horizon end after start |
| `forecast_runs` | `chk_fr_on_demand` | a SCHEDULED pull has no berthing request; others have one |
| `forecast_runs` | `chk_fr_manual` | a MANUAL pull has `submitted_by`; others do not |
| `tide_prediction_runs` | `chk_tpr_horizon` | horizon end after start |
| `environment_forecast_points` | `chk_efp_direction` | wind and wave directions within 0..359 |
| `environment_forecast_points` | `chk_efp_values` | speeds, gust, visibility and wave values not negative |
| `tide_constituents` | `chk_tc_values` | amplitude and phase not negative; phase below 360; speed positive |
| `berthing_requests` | `chk_br_cb` | block coefficient between 0.3 and 1.0 (exclusive) |
| `berthing_requests` | `chk_br_tonnage` | gross tonnage and deadweight are positive |
| `berthing_requests` | `chk_br_source` | EXTERNAL requests carry external system and reference; MANUAL requests carry the submitter |
| `berthing_requests` | `chk_br_departure_draft` | departure draft NULL or positive |
| `berthing_requests` | `chk_br_ports` | previous and next port are upper-case UN/LOCODE (case-sensitive regex) |
| `berthing_request_cargos` | `chk_brc_quantity` | quantity positive |
| `berthing_request_status_histories` | `chk_brsh_change` | a history row changes the status (from differs from to) |
| `baplie_files` | `chk_bf_hash` | file hash is 64 lower-case hex characters |
| `baplie_files` | `chk_bf_correction` | a manually corrected file records who and when |
| `container_manifest_entries` | `chk_cme_oog` | OOG overhangs NULL or positive |
| `container_manifest_entries` | `chk_cme_pod` | port of discharge is an upper-case UN/LOCODE |
| `container_manifest_entries` | `chk_cme_pol` | port of load is an upper-case UN/LOCODE |
| `container_manifest_entries` | `chk_cme_reefer` | reefer set temperature within -40..40 |
| `container_manifest_entries` | `chk_cme_stowage` | bay and tier positive; row not negative |
| `container_manifest_entries` | `chk_cme_weight` | weight NULL or positive |
| `baplie_parse_runs` | `chk_bpr_finished` | a run past QUEUED/RUNNING/REPLACED has `finished_at` |
| `baplie_parse_runs` | `chk_bpr_outcome` | DONE has an outcome; other statuses have none |
| `baplie_parse_runs` | `chk_bpr_error` | an error category exists exactly when the status is FAILED |
| `feasible_windows` | `chk_fw_values` | window end after start; confidence NULL or 0..1 |
| `entry_window_proposals` | `chk_ewp_confirm_set` | confirmed window, confirmer and time are all set or all NULL |
| `entry_window_proposals` | `chk_ewp_confirmed` | a CONFIRMED proposal has a confirmed window |
| `entry_window_proposals` | `chk_ewp_score` | peak congestion score NULL or 0..1 |
| `entry_window_proposals` | `chk_ewp_reject_set` | rejecter and time go together; a REJECTED proposal has a reason and only then |
| `solver_runs` | `chk_sr_finished` | a run that is not QUEUED or RUNNING has `finished_at` |
| `solver_runs` | `chk_sr_horizon` | horizon end after start |
| `solver_runs` | `chk_sr_manual` | a MANUAL trigger has `triggered_by` |
| `solver_runs` | `chk_sr_time` | solve time NULL or not negative |
| `rationale_templates` | `chk_rt_version` | template version positive |
| `recommendation_options` | `chk_ro_p90` | ML method has a P90 service time, others do not; P90 not below P50 |
| `recommendation_options` | `chk_ro_values` | rank positive, end after start, P50 positive, delay risk NULL or 0..1 |
| `blocked_periods` | `chk_bp_period` | period end after start |
| `demand_forecast_slots` | `chk_dfs_method` | the LIGHTGBM method requires a model version, others have none |
| `demand_forecast_slots` | `chk_dfs_values` | slot end after start; arrivals not negative; congestion 0..1 |
| `recommendation_overrides` | `chk_rov_berth` | a BERTH override carries original and override berth, others none |
| `recommendation_overrides` | `chk_rov_pilot` | a PILOT override carries a pilot, others none |
| `recommendation_overrides` | `chk_rov_time` | a START_TIME override carries original and override start, others none |
| `recommendation_overrides` | `chk_rov_tug` | a TUG override carries a tugboat, others none |
| `recommendation_rationale_factors` | `chk_rrf_kind` | a CONSTRAINT factor has a constraint type; an OBJECTIVE_TERM factor has an objective term; never both |
| `part1_runs` | `chk_p1r_retry` | retry count 0..3 (DEC-04: Part 1 up to 3 retries) |
| `plan_components` | `chk_pc_assigned` | an assigned user implies `assigned_at` |
| `plan_components` | `chk_pc_resource` | BERTH rows carry only a berth, TUG rows only a tugboat, other types only a terminal |
| `plan_components` | `chk_pc_time` | planned end after planned start |
| `storage_movements` | `chk_sm_quantity` | quantity positive |
| `storage_movements` | `chk_sm_source_direction` | VESSEL_DISCHARGE is IN, VESSEL_LOADING is OUT, MANUAL is free |
| `yard_zones` | `chk_yz_kind` | detail kind is CONTAINER_YARD |
| `dg_zone_allowed_classes` | `chk_dzac_type` | zone detail is DANGEROUS_GOODS |
| `oog_zone_details` | `chk_ozd_positive` | OOG limits NULL or positive |
| `oog_zone_details` | `chk_ozd_type` | zone detail is OOG |
| `reefer_zone_details` | `chk_rzd_plugs` | total power plugs positive |
| `reefer_zone_details` | `chk_rzd_type` | zone detail is REEFER |
| `yard_slots` | `chk_ys_blocked_empty` | only an ACTIVE slot may hold a container |
| `yard_slots` | `chk_ys_position` | bay, row and tier are positive |
| `terminal_recommendations` | `chk_tr_decision` | decider and time are set exactly when the decision is not PENDING |
| `terminal_recommendations` | `chk_tr_type_terminal` | YARD_PLACEMENT only for container terminals; STORAGE_ALLOCATION only for non-container |
| `terminal_recommendations` | `chk_tr_window` | a TIMING row has start and end (end after start); others have none |
| `terminal_recommendations` | `chk_tr_lifecycle_yard` | `lifecycle_status` is only used by YARD_PLACEMENT rows |
| `terminal_recommendations` | `chk_tr_confidence` | confidence NULL or 0..1 |
| `storage_allocation_items` | `chk_sai_quantity` | quantity positive |
| `storage_allocation_items` | `chk_sai_type` | recommendation type is STORAGE_ALLOCATION |
| `yard_placement_items` | `chk_ypi_type` | recommendation type is YARD_PLACEMENT |
| `yard_runs` | `chk_yr_finished` | a run past QUEUED/RUNNING/REPLACED has `finished_at` |
| `yard_runs` | `chk_yr_outcome` | DONE pairs with YARD_REC_GENERATED, FAILED with GENERATION_FAILED, others have no outcome |
| `yard_runs` | `chk_yr_error` | an error category exists exactly when the status is FAILED |
| `notification_logs` | `chk_nl_ack` | ACKNOWLEDGED exactly when `acknowledged_at` is set |
| `notification_logs` | `chk_nl_failed` | FAILED exactly when `failure_reason` is set |
| `notification_logs` | `chk_nl_push_no_ack` | a PUSH notification is never ACKNOWLEDGED |
| `drift_measurements` | `chk_dm_feature` | a PSI metric names its feature; others do not |
| `retraining_runs` | `chk_rr_review` | reviewer and review time go together |
| `retraining_runs` | `chk_rr_time` | finish not before start |
| `retraining_runs` | `chk_rr_versions` | champion and challenger are different versions |
| `evaluation_metrics` | `chk_em_period` | period end after start |
| `evaluation_metrics` | `chk_em_scope` | MAE belongs to a model version; OVERRIDE_RATE and WAIT_TIME to none |

### 3.5 The 17 generated columns and the unique keys over them

MySQL allows many NULLs in a UNIQUE index. A stored generated column that is **NULL when a row is not "active"** and a value when it is, plus a unique index over it, therefore gives a partial unique rule ("only one active row per X"), which MySQL cannot express directly. 12 of the 17 columns are used this way (8 distinct column names: `active_key`, `confirmed_key`, `champion_key`, `single_key`, `active_assignment_key`, `active_request_key`, `live_hash_key`, `active_code`; `active_key` appears on five tables). The rest are read-only calculations. A generated column cannot be written (MySQL refuses the insert), and the models leave them out of `$fillable` (F06).

| Column | Type and expression | Unique index | What it prevents (or calculates) |
| --- | --- | --- | --- |
| `organization_units.single_key` | `bigint unsigned`, `COALESCE(terminal_id, 0)` | `uq_org_unit_type_key` on (`unit_type`, `single_key`) | More than one Harbour Authority unit, more than one Marine Services unit, or two units for one terminal. |
| `user_role_assignments.active_assignment_key` | `tinyint`, `IF(active_to IS NULL, 1, NULL)` | `uq_ura_active` on (`user_id`, `active_assignment_key`) | A second active role for one user (DEC-36). Ended roles (`active_to` set) are history and unlimited. |
| `ml_model_versions.champion_key` | `bigint unsigned`, `IF(status='CHAMPION', ml_model_id, NULL)` | `champion_key` | Two CHAMPION versions of one model. |
| `tide_constituent_sets.active_key` | `tinyint`, `IF(is_active, 1, NULL)` | `active_key` | Two active tidal constituent sets. |
| `entry_window_proposals.confirmed_key` | `bigint unsigned`, `IF(status='CONFIRMED', berthing_request_id, NULL)` | `confirmed_key` | Two confirmed Part 1 proposals for one berthing request. |
| `recommendations.active_key` | `bigint unsigned`, `IF(status IN ('PENDING_REVIEW','MODIFIED_BY_STAFF','CONFIRMED') AND purpose='INITIAL', berthing_request_id, NULL)` | `uq_rec_active` | Two live INITIAL recommendations for one request. A DELAY_REPLAN recommendation does not count (its key is NULL). |
| `rationale_templates.active_code` | `varchar(60)`, `IF(is_active, code, NULL)` | `active_code` | Two active versions of one template code. |
| `operational_plans.active_key` | `bigint unsigned`, `IF(status='CONFIRMED', berthing_request_id, NULL)` | `active_key` | Two confirmed plans for one request. |
| `baplie_files.active_request_key` | `bigint unsigned`, `IF(status='ACTIVE', berthing_request_id, NULL)` | `active_request_key` | Two ACTIVE BAPLIE files for one request. |
| `baplie_files.live_hash_key` | `char(64)`, `IF(status='WITHDRAWN', NULL, file_hash_sha256)` | `uq_bf_request_live_hash` on (`berthing_request_id`, `live_hash_key`) | The same file (same SHA-256) uploaded twice for one request, unless the earlier copy was withdrawn. |
| `yard_runs.active_key` | `bigint unsigned`, `IF(status IN ('QUEUED','RUNNING'), terminal_recommendation_id, NULL)` | `uq_yr_active` | Two open yard runs for one terminal recommendation. |
| `baplie_parse_runs.active_key` | `bigint unsigned`, `IF(status IN ('QUEUED','RUNNING'), baplie_file_id, NULL)` | `uq_bpr_active` | Two open parse runs for one BAPLIE file. |
| `container_size_types.is_reefer` | `boolean`, `type_group = 'REEFER'` | none | Calculation: the reefer flag follows `type_group`. |
| `storage_facilities.available_capacity` | `decimal(14,2)`, `capacity - current_occupancy - reserved_capacity - blocked_capacity` | none | Calculation (it is NULL when `capacity` is NULL, as for container yards). |
| `equipment.available_quantity` | `int`, `quantity - maintenance_quantity - blocked_quantity` | none | Calculation of usable units. |
| `drift_measurements.is_drifted` | `boolean`, `metric_value > threshold` | none | Calculation: the drift flag cannot disagree with the numbers. |
| `retraining_runs.review_pending` | `boolean`, `decision IN ('KEPT_CHAMPION','FAILED') AND reviewed_at IS NULL` | none | Calculation: true until staff review a failed or unchanged retraining. |

### 3.6 Foreign keys, and why one is deferred

> **To see the tables and their foreign keys as diagrams, group by group, read [F05b](F05b_database_diagrams.md).** It draws the 92 tables in 16 business groups (2 to 10 tables each) with an overview of how the groups depend on each other, and it is generated from the schema so it cannot drift.

- **Naming.** `fk_<table abbreviation>_<target>`, for example `fk_ura_user`, `fk_ewp_window`, `fk_device_user`, `fk_p1r_request`, `fk_rrv_rec`. The names come from `PortSync.sql`, Report A and the edit sheet (CT-17 §2: names unchanged). Where the SQL source had no name, the first generator used Laravel's `<table>_<column>_foreign`; P1-B2 (M1: `user_devices`) and P1-B3 (M1, M2: `part1_runs`, `recommendation_reviews`) renamed the ones Report A names, and `SchemaTablesTest.php:101` now fails if any database FK still ends in `_foreign`.
- **Composite foreign keys (38 in the create files).** They tie child rows to the same parent in two columns so the database enforces consistency. Example: `user_role_assignments (organization_unit_id, unit_type) → organization_units (id, unit_type)` (`fk_ura_unit`) keeps the role's `unit_type` equal to its unit's. Another: `entry_window_proposals (confirmed_window_id, id) → feasible_windows (id, proposal_id)` (`fk_ewp_window`) forces a confirmed window to belong to the same proposal.
- **No cascades.** Every FK is `NO ACTION`: deleting a parent that has children is refused.
- **Why one FK is deferred.** `feasible_windows.proposal_id` points to `entry_window_proposals`, and `entry_window_proposals.confirmed_window_id` points back to `feasible_windows`. Each table needs the other to exist, so neither `create` file can carry both keys. `entry_window_proposals` (migration `100037`) is created after `feasible_windows` (`100036`) with its key to the windows, and the key from `feasible_windows` to proposals is added last in `2026_10_28_200000_add_deferred_foreign_keys` (the only cycle in the design, P1-B1 progress log step 1). So the database has 155 FKs from the create files + 1 deferred = 156.

### 3.7 Strict ENUMs

103 ENUM columns hold the status vocabularies (CT-21). `config/database.php:60` sets `'strict' => true` for the `mysql` connection, so an insert with a value outside the list raises `Data truncated for column …` instead of storing an empty value. CT-17 §2: adding a value is a minor version, removing or renaming one is a major version. Each ENUM has one PHP enum with the same values (F06); `ModelLayerTest` fails if they drift.

### 3.8 The version token

`updated_at DATETIME(6)` with default and on-update `CURRENT_TIMESTAMP(6)` on 24 tables: `navigation_channels`, `license_states`, `port_profiles`, `port_settings`, `users`, `terminals`, `berths`, `tugboats`, `safety_constraints`, `port_shifts`, `equipment`, `storage_facilities` (P1-B1 answer 3, 12 tables); `pilot_profiles`, `tug_master_profiles`, `pilot_rank_limits`, `yard_slots` (P1-B2 answer 4); `berthing_requests`, `vessels`, `baplie_files`, `entry_window_proposals`, `recommendations`, `terminal_recommendations`, `operational_plans`, `plan_components` (P1-B3 answer 4). `terminal_productivity_profiles` and `equipment_capabilities` will get one when a screen edits them. Because the column updates itself in MySQL, **a write by an AI worker also moves the token** (for example the BAPLIE worker setting `vessels.call_sign`): the staff screen then gets a 409, which is the intended "someone changed it" signal. How the API compares tokens is F12.

### 3.9 `database/grants/workers.sql` (DEC-08)

592 lines of plain SQL, no secrets. It creates six **roles** with `CREATE ROLE IF NOT EXISTS` (`workers.sql:13-18`). Each role section starts with `REVOKE ALL PRIVILEGES, GRANT OPTION FROM '<role>'` (so the file can be run again and always ends in the same state) and then lists grants. Rules (header, `workers.sql:9-11`): SELECT on every port table except `audit_logs`, `notification_logs`, `user_devices`, `recommendation_reviews`; `users` and `license_states` by column only; no DELETE; no DDL; workers insert new rows and the Backend retires old ones. Each role gets SELECT on 86 tables (checked by script) plus `SELECT (id, name, is_active) ON users` and `SELECT (mode, read_only_reason) ON license_states`; `users.password_hash` and the licence token are not readable.

| Role | Account | SELECT | INSERT | UPDATE (columns) |
| --- | --- | --- | --- | --- |
| `r_part1` | `w_part1` | 86 tables + column grants on `users`, `license_states` | `entry_window_proposals`, `feasible_windows`, `blocked_periods`, `forecast_runs`, `environment_forecast_points`, `tide_prediction_runs`, `tide_prediction_points`, `demand_forecast_slots` | `part1_runs`: `status`, `outcome`, `retry_count`, `entry_window_proposal_id`, `started_at`, `finished_at` |
| `r_part2` | `w_part2` | 86 tables + column grants on `users`, `license_states` | `solver_run_model_versions`, `recommendations`, `recommendation_options`, `recommendation_option_tugs`, `recommendation_rationale_factors` | `solver_runs`: `status`, `objective_value`, `solve_time_ms`, `solver_version`, `result_detail`, `started_at`, `finished_at` |
| `r_yard` | `w_yard` | 86 tables + column grants on `users`, `license_states` | `yard_placement_items` | `yard_runs`: `status`, `outcome`, `error_category`, `retry_count`, `result_detail`, `worker_version`, `started_at`, `finished_at`; `terminal_recommendations`: `lifecycle_status`, `confidence`, `rationale_template_id`, `rationale_params`, `failure_reason` |
| `r_edi` | `w_edi` | 86 tables + column grants on `users`, `license_states` | `baplie_parse_issues`, `containers`, `container_manifest_entries` | `baplie_parse_runs`: `status`, `outcome`, `error_category`, `retry_count`, `container_count`, `result_detail`, `worker_version`, `started_at`, `finished_at`; `baplie_files`: `parse_status`, `staged_payload`; `vessels`: `call_sign`, `flag_state` |
| `r_validate` | `w_validate` | 86 tables + column grants on `users`, `license_states` | none | none |
| `r_part3` | `w_part3` | 86 tables + column grants on `users`, `license_states` | `evaluation_metrics`, `drift_measurements` | none |

Notes on the matrix: `r_part2` does not insert `solver_runs`; the Backend creates the run row and the worker updates it. `r_yard` updates `yard_runs.retry_count` since P1-B3 (A1, lead's answer 3; the AI lead approved it after the merge). `result_handled_at` (run tables) and `drift_measurements.notified_at` are granted to nobody: they are Backend-only. `r_validate` can only read. Line ranges: `r_part1` 20-118, `r_part2` 120-215, `r_yard` 217-309, `r_edi` 311-406, `r_validate` 408-497, `r_part3` 499-590.

### 3.10 `AiBridge/Console/CreateWorkerAccounts.php` — `php artisan atlantech:worker-accounts`

Registered by `AiBridgeServiceProvider` (`AiBridgeServiceProvider.php:14-17`, console only). Signature `atlantech:worker-accounts {--host=% : Host the worker accounts connect from}`.

| Member | What it does |
| --- | --- |
| `ACCOUNTS` (`:30-37`) | Map account → role: `w_part1→r_part1`, `w_part2→r_part2`, `w_yard→r_yard`, `w_edi→r_edi`, `w_validate→r_validate`, `w_part3→r_part3`. `WorkerGrantsTest` also reads it. |
| `handle()` (`:39`) | (1) Reads the six passwords from `config('services.ai_workers.db_passwords.<account>')` (`config/services.php`, from `WORKER_PART1_PASSWORD` … `WORKER_PART3_PASSWORD` in `.env`). (2) If any is empty it prints `Missing passwords: WORKER_…_PASSWORD, …` and returns failure **before touching the database** (`:46-51`). (3) Runs the whole `workers.sql` through `DB::unprepared` (`:53`) on the current connection: grants apply to **the database the command is connected to**. (4) For each account (`:58-68`): `CREATE USER IF NOT EXISTS`, `ALTER USER … IDENTIFIED BY` (so running again resets the password to the `.env` value), `GRANT <role> TO <user>`, `SET DEFAULT ROLE <role> TO <user>`. Passwords and host are quoted with PDO. Prints `account → role` lines and `Worker accounts are ready.` |
| `passwordVariable($account)` (`:78`) | Turns `w_part1` into `WORKER_PART1_PASSWORD` for the error message. |

Why roles plus accounts: secrets cannot be committed, so the grants (reviewable, versioned) are in the file as roles and the command attaches the passwords at install time (P1-B1 answer 5, lead 2026-10-28). The success path is proven by hand, not by a test, because a test would create the real `w_*` accounts on the server (P1-B1 review R7); `WorkerGrantsTest` loads the same file under `t_r_*` names instead.

### 3.11 `.github/labeler.yml` (the `ai-impact` link)

At the repository root (`project-backend/.github/labeler.yml`, so paths start with `ATLANTECH/`):

```yaml
ai-impact:
  - changed-files:
      - any-glob-to-any-file:
          - ATLANTECH/database/migrations/**
          - ATLANTECH/database/grants/**
```

The `labeler` workflow (`.github/workflows/labeler.yml`, F02) adds the label to any PR that changes a migration or a grant. CT-17 §6: a PR that touches a table a worker reads or writes carries `ai-impact` and needs one AI Team approval. The label is a prompt for the AI lead (Rashwan) to review; the rule is stricter than the glob (a migration on a table no worker reads still gets the label). Before 2026-10-29 the label did not exist, so PRs #6, #12 and #13 carry none; the AI lead approved them after the fact (P1-B1 to P1-B3 logs).

### 3.12 `AiBridge/Console/GenerateContractsSchema.php` — `php artisan atlantech:contracts-schema` (`composer contracts:schema`)

Signature `atlantech:contracts-schema {--output=} {--database=atlantech_schema}`. It writes the published copy of the schema (CT-17 §5; nobody edits `schema.sql` by hand).

| Step (`file:line`) | What it does |
| --- | --- |
| `handle()` `:30` | Output path defaults to `base_path('../../contracts/db/schema.sql')`, the PortHub `contracts/db` folder above the submodule (`:32`). If the folder does not exist it prints `Folder not found: … Run from the PortHub checkout or pass --output.` and fails (`:33-37`). |
| Guard `:42-48` | The scratch database is wiped and dropped, so the command refuses a `--database` that equals the app's own database or already exists (R2 of the P1-B1 review). Nothing is touched before this check. |
| Build `:50-53` | `CREATE DATABASE <scratch>` (utf8mb4, `_0900_ai_ci`), switch the `mysql` connection to it (`useDatabase()`, `:117`), `migrate:fresh --database=mysql --force`, read the last migration name. |
| Dump `:56-64` | `mysqldump --no-data --skip-comments --skip-dump-date --set-gtid-purged=OFF --single-transaction` with the password in `MYSQL_PWD`. `mysqldumpCommand()` (`:85`) uses a `mysqldump` found on the machine (CI) or, if there is none (Windows), `docker compose exec -T mysql mysqldump` (run from the folder with `docker-compose.yml`). A failed dump prints `mysqldump failed: …`. |
| Write `:66-69` | Header + dump with `AUTO_INCREMENT=n` stripped (no noise in diffs) + `-- Worker roles (database/grants/workers.sql, DEC-08)` + the whole `workers.sql`. `header()` (`:108`) reads the CT-17 version from `docs/reference/split/contracts/CT-17.md` ("Status \| Approved v1.0") and writes `-- ATLANTECH port database schema (CT-17 v1.0).`, `-- Generated by …`, `-- Last migration: <name>`. |
| `finally` `:70-72` | Always drops the scratch database and switches the connection back. |

Output on success: `Wrote <path> (last migration <name>).`

### 3.13 Test helpers that build the schema

`tests/Pest.php:64-78` `useSchemaDatabase()`: points the default connection at `env('DB_SCHEMA_TEST_DATABASE', 'atlantech_testing')`, purges the connection, and on the first call of a run executes `migrate:fresh --database=mysql --force` (a `static $migrated` flag). **It wipes that database every run.** `phpunit.xml` also sets `DB_DATABASE=atlantech_testing`. The schema tests all call it in `beforeEach` and carry the Pest group `schema`. `Pest.php:86-105` also holds `makeUserWithRole()` (used by F09 tests).

## 4. Why this design

| Choice | Reason | Source |
| --- | --- | --- |
| Migrations are the single source of truth; `schema.sql` is generated | Two sources drift. From the first migration PR the migrations replace `PortSync.sql` and the DDL files; the AI team builds its test database from the generated file. | CT-17 §1, §5; AIBD-038 (Backend owns all migrations, including tables the AI writes) |
| Plural table names, `BIGINT UNSIGNED` ids | Laravel convention, and the workers map ids as unsigned. The singletons were changed from `tinyint` to `BIGINT UNSIGNED` to obey one rule. | CT-17 §2; P1-B2 answers 3 and 8 |
| CHECKs and generated columns in the create migration, not a later task | The contract says they are created with the table; a "constraints task" would leave a window with an unprotected table. P1-B4 kept only the tests. | CT-17 §2; P1-B1 answer 1 |
| Raw SQL for CHECKs, generated keys for partial uniques | Laravel has no named CHECK; MySQL has no partial unique index. | P1-B1 step 2 |
| Database rules duplicated by code rules | The database is the last line of defence against a bug or against a worker that bypasses Laravel; code gives readable errors. | CT-17 §2; NFR data quality |
| Edit `create` migrations in place before release | There was no release and no data; `alter_` files would only add noise. After release only additive changes are allowed and `down()` must not drop or rename worker-used columns. | P1-B2 answer 1; CT-17 §4 |
| One migration per table in foreign-key order | A reviewer can read one table at a time; the order guarantees every FK target exists. | CT-17 §4; P1-B1 section 3 |
| FK names, CHECK names, index names unchanged from the design | Workers and docs refer to them (error messages carry the constraint name; tests assert it). | CT-17 §2 |
| `updated_at DATETIME(6)` as version token | Microseconds make a lost update detectable on fast edits; MySQL maintains it, so even a worker write moves it. | Coding Notes §4; DEC-57; P1-B1 answer 3 |
| Roles in `workers.sql`, accounts by command | No secret in git; roles are reviewable and testable. Rejected: passwords in the file. | DEC-08; P1-B1 answer 5 |
| No DELETE and no DDL for workers; column-level UPDATE | A worker can report its result but cannot rewrite history or secrets. The Backend alone writes audit rows and statuses. | DEC-08; CT-15 |
| Strict mode and UTC on the connection | The ENUM and time rules depend on them (F01). | CT-17 §2 |
| Tests prove a sample of CHECKs plus a name guard, not all 129 | Cost and value: one case per kind and per module, and any removed or renamed rule fails the guard. A test for `chk_yr_retry` was proposed to the AI team, not added. | P1-B4 answers 1, 2, 3 |
| Label `ai-impact` by file path | Cheap and automatic; the AI lead reviews anything that can change what workers read or write. | CT-17 §6 |

Rejected alternatives that appear in the plan files: keeping `tinyint` singletons (P1-B2 C2), making `single_key` signed as the DDL fixes file says (M2, kept unsigned to match the other seven id keys), adding `FAILED`/`TIMEOUT` to `solver_runs.status` (P1-B3 answer 5: use `ERROR` with the reason in `result_detail`).

## 5. How it connects

- **F06** builds one model per table and one enum per ENUM value set on this schema; `ModelLayerTest` reads `information_schema` and `workers.sql` to check models against it.
- **F09, F10, F12** depend on columns here: `user_role_assignments` and its generated key (roles), `audit_logs` (append-only audit), `updated_at` (version token).
- **F13** seeds these tables; the `port_settings` min and max columns exist for it.
- **F14** lists `GenerateContractsSchema` among the four publishing commands; **F02** owns the `labeler` workflow, CI and the test database variables; **F01** owns `config/database.php` and Docker.
- **Module layer rule.** Tables have no module in MySQL. The module of a table is decided by the model that maps it (F06, plan P1-B5 section 3), so a foreign key may point at a table whose model sits in a *later* module; the relation is then defined on the later side only.
- **AI team.** Reads `schema.sql`, the grants and `contracts/ai/table-ownership.md` (CT-15). Any change to a worker-visible table or column is a contract change.

## 6. Data

The feature **is** the data definition. Ownership (CT-15, `contracts/ai/table-ownership.md`): the **Backend owns every table** and every migration. Workers:

- read 86 tables completely and two by column;
- insert into 19 tables (see the INSERT column above: Part 1 writes proposals, windows, blocked periods, forecast and tide runs and points, demand slots; Part 2 writes solver model versions, recommendations, options, option tugs and rationale factors; the yard worker writes placement items; the BAPLIE worker writes parse issues, containers and manifest entries; Part 3 writes evaluation metrics and drift measurements);
- update only listed columns on 7 tables: the run tables `part1_runs`, `solver_runs`, `yard_runs`, `baplie_parse_runs`, plus `terminal_recommendations`, `baplie_files` and two columns of `vessels` (`call_sign`, `flag_state`);
- never read `audit_logs`, `notification_logs`, `user_devices`, `recommendation_reviews`, `users.password_hash`, the licence token.

Framework tables (`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `migrations`) are Backend-only; no worker has a grant on them.

## 7. Tests

All five files are in `tests/Feature/Database/`, group `schema`, and need **MySQL** (SQLite cannot run CHECK regex, generated columns or ENUM). Each calls `useSchemaDatabase()`.

| Test file | Proves | Key tests |
| --- | --- | --- |
| `SchemaTablesTest` (11 tests) | 92 plural tables from `table-names.md`, no singular name left; framework tables present; InnoDB and `utf8mb4_0900_ai_ci`; no signed `bigint`; every `id` is `bigint unsigned` including the singletons; `user_devices` FK is `fk_device_user`; no FK ends in `_foreign`, and `part1_runs`/`recommendation_reviews` keep their Report A names; 129 CHECKs and the 17 generated columns by name; no `email` column (DEC-35); 24 tables with `datetime(6)`, NOT NULL, default and on-update; session time zone `+00:00`. | `:39`, `:67`, `:101`, `:118`, `:164`, `:186` |
| `SchemaConstraintsTest` (8 tests, rolled back, FK checks off) | Username lowercase and length; role matches unit; one active role per user and allowed after the first ends (DEC-36); UN/LOCODE case; one active tide set; a generated column cannot be written; a revoked device keeps no push token. | `:35`, `:50`, `:63`, `:79`, `:90`, `:96` |
| `DatabaseRulesTest` | A valid base row of each of the 16 sampled tables is accepted; 17 sampled CHECKs refuse their broken row with `Check constraint '<name>' is violated` (range, singleton, state, mutually exclusive, cross-column, regex, sum, conditional); 11 generated values/columns compute as designed; 11 unique indexes refuse a second active row and allow it once the first is inactive; strict mode; 3 ENUM columns refuse `NOT_IN_THE_LIST`; **the 129 names guard**. | `:125`, `:129`, `:136`, `:184`, `:199`, `:203`, `:212` |
| `ContractsSchemaTest` (3 tests) | `schema.sql` starts with the CT-17 v1.0 header, ends with the worker roles, has no `AUTO_INCREMENT=n`, and loading its structure into an empty database rebuilds 100 tables and 129 CHECKs; the generator refuses an existing database and keeps its 100 tables; it fails when the output folder is missing. | `:13`, `:45`, `:53` |
| `WorkerGrantsTest` (7 tests) | Loads `workers.sql` under `t_r_*` role names, signs in as a test account and checks rights: reads port state but not secrets, audit or devices; yard worker writes only its results (and cannot DELETE or CREATE TABLE); BAPLIE and yard workers may update `retry_count`; a worker with column grants still moves the version token; `r_validate` has no write; the command refuses to run without every password. | `:48`, `:58`, `:70`, `:78`, `:89`, `:111`, `:118` |

How to run (needs Docker MySQL; use your **own empty database** when other sessions share the server):

```bash
# create once (root has no password in docker-compose)
docker exec atlantech-mysql-1 mysql -uroot -e "CREATE DATABASE atlantech_testing_mine CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
```

```powershell
$env:DB_DATABASE = "atlantech_testing_mine"; $env:DB_SCHEMA_TEST_DATABASE = "atlantech_testing_mine"
vendor/bin/pest tests/Feature/Database/SchemaTablesTest.php tests/Feature/Database/DatabaseRulesTest.php
vendor/bin/pest --group=schema
```

Measured while writing this guide (database `atlantech_testing_g2`, 2026-11-04): `SchemaTablesTest`, `SchemaConstraintsTest`, `DatabaseRulesTest` and `ModelLayerTest` together: **377 passed, 1156 assertions, about 60 s**. `ContractsSchemaTest` and `WorkerGrantsTest` were **not run** (see 10).

## 8. Gotchas and invariants

- **`useSchemaDatabase()` runs `migrate:fresh` on `DB_SCHEMA_TEST_DATABASE`.** Never set it to a database you care about. Two runs on the same database overwrite each other (A7). The default is `atlantech_testing`.
- **Adding a migration breaks `ContractsSchemaTest.php:13` (first test):** it hard-codes `-- Last migration: 2026_11_03_183535_alter_port_settings_add_min_max` (`:25`) and the totals 100 tables and 129 CHECKs (`:36-37`). Update those numbers in the same PR. The second test (`:45`, line 50) hard-codes the database name `atlantech_testing` and 100 tables: with your own database name it still passes only because `atlantech_testing` exists on the shared server (if it does not, the generator would create it, migrate and drop it).
- **Add or remove a CHECK:** edit the 129-name list in `DatabaseRulesTest.php:212` and the count in `SchemaTablesTest.php:118`, `ContractsSchemaTest.php`. **Add a generated column:** add it to the list in `SchemaTablesTest.php:118` and keep it out of the model's `#[Fillable]` (F06 guard). **Add a token table:** add it to the list in `SchemaTablesTest.php:164`.
- **Case-insensitive collation.** Any text CHECK must use a binary cast or the `'c'` regex flag, or it is always true or always false.
- **Never edit a released worker-used column in a way that drops or renames it** (CT-17 §4). Add a nullable column; increment the contract minor version; tell the AI team.
- **`workers.sql` applies to the current database and the roles are server-wide.** `REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'r_x'` is the first statement of each role. Per MySQL semantics a role has one set of privileges for the whole server, so running the file against a second database on the same server would remove the first database's grants. **Unverified here** (not run; to check, run it against two scratch databases and `SHOW GRANTS FOR r_yard`). Real installs have one port database per server.
- **`atlantech:worker-accounts` resets passwords every run** and creates accounts for host `%` unless `--host` is given. Do not run it against the database in `.env` unless you mean it.
- **`WorkerGrantsTest` creates server-wide objects** (`t_r_*` roles and the user `t_worker`@`%`) and commits rows in `vessel_types`/`vessels` for one test (deleted in `finally`). Two sessions running it at the same time on one MySQL server conflict.
- **Generated columns: write is refused.** Insert or update including `active_key` fails with "generated column". Factories and seeders must not set them.
- **Many token tables have no `created_at`** (for example `pilot_rank_limits`, `pilot_profiles`, `tug_master_profiles`, `yard_slots`, `plan_components`, `operational_plans`, `license_states`); the model must set `CREATED_AT = null` (F06 lists them).
- **A worker write moves the version token** of the row (3.8). A staff edit racing with a worker write gets 409 by design.
- **`schema.sql` is generated; never edit it.** If it is wrong, fix the migration and regenerate.

## 9. How to extend it

**Add a column (after release).** (1) New file `database/migrations/<date>_alter_<table>_<change>.php` with `Schema::table`; nullable or with a default; no drop or rename of a worker-used column. (2) If a worker reads or writes it, edit `workers.sql` (a new column is not visible to a role that has column-level grants until you list it). (3) Update the model `#[Fillable]`/casts and factory (F06). (4) Update the tests in section 8. (5) PR gets `ai-impact` automatically. (6) After merge: `composer contracts:schema` and the paired PortHub PR.

**Add a table.** Create `…_create_<plural>_table.php` after the last file, with FKs only to tables that exist, named FKs and indexes, CHECKs by `DB::statement`; add the table to `contracts/db/table-names.md` (so `SchemaTablesTest` expects it), the model/enum/factory (F06, including its module in the map), grants if a worker touches it, and the counts in the tests.

**Add a CHECK.** `DB::statement('ALTER TABLE ... ADD CONSTRAINT chk_<abbr>_<what> CHECK (...)')`, name in the guard list, and ideally a broken row in `rulesBrokenRows()` (`DatabaseRulesTest.php:100`) with a valid base row in `rulesValidRow()` (`:28`).

**Add an ENUM value.** Add to the `->enum([...])` list in a new additive migration (`ALTER TABLE … MODIFY`), add the case to the PHP enum (the guard compares them), regenerate `status-vocabulary.json` (F14).

**Add a worker.** A new role section in `workers.sql`, a new line in `CreateWorkerAccounts::ACCOUNTS` and `config/services.php`, `.env.example` key, a test in `WorkerGrantsTest`.

## 10. Try it

All of this was run from `project-backend/ATLANTECH` on 2026-11-04 against the throwaway database `atlantech_testing_g2` (never the `atlantech` database).

```bash
php artisan list atlantech
```
Real output (relevant lines):
```
  atlantech:contracts-openapi            Publish openapi/port-api.yaml to PortHub contracts/openapi (CT-02)
  atlantech:contracts-role-matrix        Generate contracts/role-matrix.md from the role-to-ability matrix (P2-D1)
  atlantech:contracts-schema             Generate contracts/db/schema.sql from the migrations (CT-17)
  atlantech:contracts-status-vocabulary  Generate contracts/status-vocabulary.json from the PHP enums (CT-21)
  atlantech:worker-accounts              Create the AI worker MySQL accounts and give each its DEC-08 role
```

```powershell
$env:DB_DATABASE = "atlantech_testing_g2"; php artisan migrate:status
```
Real output: `0001_01_01_000000_create_sessions_table .. [1] Ran` … `2026_10_28_200000_add_deferred_foreign_keys .. [1] Ran`, `2026_11_03_183535_alter_port_settings_add_min_max .. [1] Ran` (97 lines, all `Ran`).

```bash
docker exec atlantech-mysql-1 mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='atlantech_testing_g2'"
```
Real output on the migrated database: tables 100, CHECK constraints 129, stored generated columns 17, foreign keys 156, ENUM columns 103, `updated_at` of type `datetime(6)` 24. `SHOW CREATE TABLE user_role_assignments` shows `active_assignment_key tinyint GENERATED ALWAYS AS (if((active_to is null),1,NULL)) STORED`, `UNIQUE KEY uq_ura_active (user_id, active_assignment_key)` and the four constraints `fk_ura_unit`, `fk_ura_user`, `chk_ura_period`, `chk_ura_role_matches_unit`.

```bash
node docs/reference/lookup.mjs part1_run
```
Real output: `table:part1_run · table · part1_run`, `database: port`, `read: split/tables/part1_run.md`, `source: database/PortSync.sql:921-931`. (Note the **singular** PortSync name; the table in MySQL is `part1_runs`.)

```powershell
$env:DB_DATABASE = "atlantech_testing_g2"; php artisan model:show 'App\Modules\Access\Models\UserRoleAssignment'
```
Real output: table `user_role_assignments`, `active_assignment_key nullable .. tinyint` (not fillable), `role` and `unit_type` cast to the enums, relations `user` and `organizationUnit`. (`model:show` needs the full class name; `User` alone fails with `Target class [App\User] does not exist`.)

```powershell
$env:DB_DATABASE = "atlantech_testing_g2"; $env:DB_SCHEMA_TEST_DATABASE = "atlantech_testing_g2"
vendor/bin/pest tests/Feature/Database/SchemaTablesTest.php tests/Feature/Database/SchemaConstraintsTest.php tests/Feature/Database/DatabaseRulesTest.php
```
Real output: all pass (the four files together: 377 passed, 1156 assertions, 59.7 s).

Expected, **not run**:

- `php artisan atlantech:worker-accounts` — it would create accounts and roles on the MySQL server and reset passwords; `.env` may already hold the six `WORKER_*_PASSWORD` values. With a password missing it would print `Missing passwords: WORKER_YARD_PASSWORD, …` and exit 1, but that depends on `.env`.
- `composer contracts:schema --output=… --database=atlantech_schema_x` — creates and drops a scratch database and, without `--output`, overwrites `PortHub/contracts/db/schema.sql`. Expected last line `Wrote <path> (last migration 2026_11_03_183535_alter_port_settings_add_min_max).`
- `ContractsSchemaTest`, `WorkerGrantsTest` — they create and drop databases or server-wide roles and the second one assumes `atlantech_testing` exists.

## 11. Open points

Findings (not fixed; this guide is documentation only):

1. **`workers.sql:592` still contains `-- DEMO (do not merge): shows the ai-impact label and the AI lead review request. No grant changes.`** It is on `develop`. Harmless, but it will be copied into the next `schema.sql`. Remove in the next PR that touches grants.
2. **The published `PortHub/contracts/db/schema.sql` is stale.** On PortHub `main` (`0689c90`) its header says `-- Last migration: 2026_10_28_200000_add_deferred_foreign_keys`; it has no `min_value`/`max_value` on `port_settings` (the DEC-59 migration of 2026-11-03). The last regeneration commit is `b1695ea` (2026-10-28). It needs `composer contracts:schema` and a paired PortHub PR; the AI team builds on this file.
3. **`schema.sql` header says `CT-17 v1.0`** while additive changes (the 16, then 24 token tables, DEC-36 key, FK naming, `port_settings` min/max) are waiting for CT-17 v1.1 through `contract-change` (P1-B2 follow-ups F12 to F14). The test pins the string `v1.0` (`ContractsSchemaTest.php`).
4. **`ContractsSchemaTest` hard-codes the last migration name, the 100 tables and `atlantech_testing`** (section 8). Each new migration needs a test edit; the second test's database name should come from the same variable as `useSchemaDatabase()`.
5. **`password_reset_tokens` is keyed by `email`**, which users no longer have (DEC-35); there is no password-reset feature (P1-B1 R5). **`personal_access_tokens` (CT-17 §3, mobile Sanctum tokens with `revoked_at`) is not migrated yet**; planned with P1-C3 (F15).
6. **`AuditLog` (model docblock) says "the application DB user gets INSERT + SELECT only on this table (P21-03)"**, but no file in the repo grants or restricts an application user; `.env.example` connects as `root`. The append-only rule is enforced only in the model (F10). Unverified for production: check what the install script of the port server does (P6).
7. **`chk_yr_retry`** (a CHECK for `yard_runs.retry_count` 0..1, DEC-04 "others once") was proposed to the AI team and not added (P1-B4 answer 3). Only `chk_p1r_retry` (0..3) exists.
8. **Index wording:** `00_INDEX.md` says "8 generated unique keys". The code has 12 unique indexes over generated columns that use 8 distinct column names (3.5). The statement is right if read as column names.
9. **`REVOKE ALL` on server-wide roles** (section 8) is Unverified.
10. **Class diagrams and CT-17 text** still lag the migrations (P1-B2 F1 to F17, P1-B3 F18 to F42: owners' work, not code).
11. **`GenerateContractsSchema` falls back to `docker compose exec`** only when run from the folder that holds `docker-compose.yml`; elsewhere with no local `mysqldump` it fails with `mysqldump failed`.
