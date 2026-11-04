# F06 · The model layer (pattern once, then the catalogue)

Task origin: P1-B5 (models, enums, factories, `ModelLayerTest`), PR #17 (`523a0cb`); the access fields added later by P2-D1. Facts checked against `develop` at `106b40e`. Paths are inside `project-backend/ATLANTECH/` unless stated. Read F05 first: the models are written to match that schema, and `ModelLayerTest` compares them with it.

## 1. What it does

This feature is the **Eloquent layer** over the 92 port tables:

- **92 models**, one per table, each in the module that owns the table (`app/Modules/<Module>/Models/`, 13 modules hold models; `AiBridge` and `Realtime` hold none).
- **80 PHP enums** (`app/Modules/<Module>/Enums/`): 77 are backed by the exact values of a MySQL ENUM column and cast on the models, 3 are not database columns (`Ability`, `AccessRefusalReason`, `PlanComponentDerivedStatus`).
- **92 factories** (`database/factories/<Module>/`), one per model, with 5 named states in total.
- **One guard test**, `tests/Feature/Models/ModelLayerTest.php`, that reads the real database and fails if a model, an enum, a relation, a `Fillable` list or a factory drifts from the schema or from the AI ownership rules (CT-15).

It does **not** contain business rules, policies, services, observers, soft deletes, or API resources. A model only describes the table: casts, relations, which columns may be mass-assigned. Services (sign-in, audit, version token) are separate classes in later features.

## 2. Request or data flow

There is no HTTP request. The flow for a normal read or write is:

1. A service or controller calls `User::query()->…`, `Berth::query()->…` or a factory (tests, seeders).
2. Eloquent resolves the table and key from the `#[Table(...)]` attribute (3.2), casts attributes through `casts()` (dates to Carbon, ENUM columns to PHP enums, decimals to strings, JSON to arrays) and, on `create()`/`update()`, accepts only the columns listed in `#[Fillable([...])]`.
3. Columns that MySQL owns are never written by the model: generated columns (F05 3.5) and, for AI-owned columns, a service that must set them calls `forceFill()` explicitly (3.7).
4. MySQL applies its own rules (CHECKs, unique keys, ENUM strictness, F05). A violation reaches PHP as `QueryException`.
5. Model events and timestamps: `updated_at` is maintained by MySQL on token tables (F05 3.8) and is also set by Eloquent, written with microseconds (3.3).

## 3. Every piece of code

### 3.1 The layout and how a model is found

`app/Modules/<Module>/Models/<Class>.php` for models, `…/Enums/<Name>.php` for enums, `database/factories/<Module>/<Class>Factory.php` for factories (namespace `Database\Factories\<Module>`). Nothing is registered: `ModelLayerTest::domainModels()` globs `app/Modules/*/Models/*.php` and keeps every subclass of `Illuminate\Database\Eloquent\Model` (`ModelLayerTest.php:58-73`), so a new model is checked automatically. The non-model files in those folders (`Access/Models/Concerns/RestrictsToOwnTasks.php`, `Shared/Models/AuditLogBuilder.php`) are skipped because they are not `Model` subclasses.

Which module owns which table was decided in P1-B5 (answer 1) with one rule: a table lives in the earliest module that owns its meaning and no later than every table it points to allows (the full map is the catalogue in 3.9). Placements that follow from the layer rule (`A_orientation.md` A4):

- `organization_units` is in **Access** (with roles); its `terminal_id` is a plain column there; `Terminal::organizationUnit()` (MasterData may use Access) gives the link (`Terminal.php:124`).
- `ml_models` and `ml_model_versions` are in **MasterData**, because Recommendation tables point at them and Learning comes after Recommendation.
- The Part 1 result tables (`entry_window_proposals`, `feasible_windows`, …) are in **Recommendation**, because they point at both Environment and Berthing, which are peers.
- Enums used by more than one module live in **Shared/Enums** (`RunStatus`, `TerminalType`, `ComponentType`, `StorageDetailKind`, `CapacityUnit`, and `AuditLogAction` because `audit_logs` is a Shared table), so no module depends on another one only for an enum (P1-B5 deviation 1).

### 3.2 The model pattern, in depth: `User` and `UserRoleAssignment`

`app/Modules/Access/Models/User.php`:

| Line | Code | What it does and why |
| --- | --- | --- |
| 23 | `#[Table(name: 'users')]` | Laravel 13 attribute: the table name is explicit (never inferred). The same attribute carries `key:`, `incrementing:`, `keyType:` and `timestamps:` when a table differs from the default (3.3). |
| 24 | `#[DateFormat('Y-m-d H:i:s.u')]` | Dates are written to MySQL with microseconds. Needed on the 24 token tables because `updated_at` is the version token (F12); without it Eloquent would write seconds. |
| 25 | `#[Fillable(['name','username','password_hash','phone','is_active','must_change_password'])]` | The columns that mass assignment may set. `id`, `created_at` and `updated_at` are not listed. An AI-owned column or a generated column never appears here (3.7). |
| 26 | `#[Hidden(['password_hash'])]` | The hash never appears in `toArray()`/JSON (`ModelLayerTest.php:257`). |
| 27 | `#[UseFactory(UserFactory::class)]` | Binds the factory by attribute (no `newFactory()` method, and the factory does not need the `Database\Factories\<Model>Factory` naming convention). Every model has this. |
| 28 | `class User extends Authenticatable` | The only model that extends `Authenticatable` (sign-in, F07). All others extend `Model`, or `Pivot` for link tables (3.5). |
| 31 | `use HasFactory, Notifiable;` | `HasFactory` with the docblock `@use HasFactory<UserFactory>` is on every model. |
| 40 | `protected $authPasswordName = 'password_hash';` | DEC-35: the password column is `password_hash`, not `password`. `getAuthPassword()` then reads it. |
| 47 | `protected $rememberTokenName = '';` | There is no remember-token column; web sign-in is a Sanctum session. |
| 52-61 | `casts()` | `password_hash` is `hashed` (assigning a plain string stores a hash; `ModelLayerTest.php:257`), `is_active` and `must_change_password` are `boolean`, `created_at`/`updated_at` are `datetime`. |
| 66, 74 | `devices()`, `roleAssignments()` | `hasMany(UserDevice::class)`, `hasMany(UserRoleAssignment::class)`: Access to Access, allowed. |
| 84 | `activeRoleAssignment()` | `hasOne(UserRoleAssignment::class)->whereNull('active_to')`: the one active assignment of DEC-36. |
| 92 | `accessScope()` | Memoised `AccessScope::for($this)` (F09). The only non-persistence behaviour on a model; kept here so `RestrictsToOwnTasks` can ask the user for scope. |
| 102 | `auditLogs()` | `hasMany(AuditLog::class, 'actor_id')`. `audit_logs.actor_id` points at `users`, but Shared (layer 1) may not know Access (layer 2), so `AuditLog` has **no** `belongsTo(User)`; the link is defined on the later module (3.6). |

`app/Modules/Access/Models/UserRoleAssignment.php` (a table with **no timestamps**):

- `:19` `#[Table(name: 'user_role_assignments', timestamps: false)]` — the table has neither `created_at` nor `updated_at` (`active_from`/`active_to` carry the history).
- `:20` `#[Fillable(['user_id','role','organization_unit_id','unit_type','active_from','active_to'])]` — `active_assignment_key` (generated, F05 3.5) is left out on purpose, so a mass-assignment can never try to write it.
- `:30-38` `casts()` — `'role' => Role::class`, `'unit_type' => UnitType::class`, `'active_from'`/`'active_to' => 'datetime'`. Assigning `Role::PortGm` stores `PORT_GM`; reading returns the enum.
- `:43`, `:51` `user()`, `organizationUnit()` — `belongsTo`. Both FKs of `fk_ura_unit` (a composite key) are modelled by the single-column relation `organizationUnit()`; the second column (`unit_type`) is kept equal by the database.

### 3.3 Shared conventions: keys, timestamps, casts

| Convention | Rule in the code | Count |
| --- | --- | --- |
| Table | `#[Table(name: '<plural>')]` on every model | 92 |
| Timestamps | The attribute decides from the table's columns. **Neither column** → `timestamps: false`. **Only `updated_at`** (most token tables) → `public const CREATED_AT = null;` plus a docblock "The table has no `created_at` column". **Both** → default | 68 no timestamps, 20 `updated_at` only, 4 both (`User`, `BerthingRequest`, `NavigationChannel`, `PortProfile`) |
| Version token | `#[DateFormat('Y-m-d H:i:s.u')]` + `'updated_at' => 'datetime'` in `casts()` | 24 (exactly the 24 token tables of F05 3.8, checked by script) |
| Key | `id` auto-increment by default. A table whose key is not `id` sets `key:`, `incrementing: false`, `keyType:`: `ImdgClass` (`code`, string), `PilotRankLimit` (`pilot_rank`, string), `PilotProfile` and `TugMasterProfile` (`user_id`), `TankDetail` and `WarehouseDetail` (`storage_facility_id`), `OogZoneDetail` and `ReeferZoneDetail` (`yard_zone_id`) | 8 |
| Singletons | `PortProfile` and `LicenseState` set `incrementing: false` and list `id` in `#[Fillable]` (the id is 1, set by hand; `chk_*_singleton`) | 2 |
| Enum cast | `'col' => EnumName::class` | 103 casts = the 103 ENUM columns |
| Decimal | `'col' => 'decimal:n'` with the column's scale; the value stays a string so precision is not lost | many |
| JSON | `'col' => 'array'` | 11 columns |
| Boolean | `'col' => 'boolean'` | many |
| Dates | `'datetime'`; all values are UTC (`config/app.php` timezone UTC, F01) | many |
| Hidden | `User.password_hash`, `UserDevice.push_token`, `LicenseState.token` | 3 |
| Factory | `#[UseFactory(...)]` + `use HasFactory` | 92 |

### 3.4 The enum pattern, in depth: `Role`

`app/Modules/Access/Enums/Role.php`:

- `:8` `enum Role: string` — a string-backed enum.
- `:10-21` twelve cases whose **names are TitleCase of the SQL value** and whose **values are the exact SQL strings**: `PortGm = 'PORT_GM'`, `PortStaff`, `MarineSafetyOfficer`, `PilotageManager`, `PilotageOfficer`, `MarinePilot`, `TugManager`, `TugOfficer`, `TugMaster`, `TerminalAdmin`, `TerminalOfficer`, `TerminalSupervisor`. This is the lead's rule (P1-B5 answer 3): CLAUDE.md asks for TitleCase keys, the contract asks for SQL names, so the key is TitleCase and the backing value is the SQL name. Keep the list in the same order as the MySQL ENUM; the guard compares the arrays in order.
- `:26` `isTerminalScoped()` — true for `TerminalAdmin`, `TerminalOfficer`, `TerminalSupervisor` (FR-AC-15). `:37` `isFieldRole()` — true for `MarinePilot`, `TugMaster`, `TerminalSupervisor` (FR-AC-16, DEC-48). These two helpers are the only methods on any of the 80 enums (checked by script); F09 uses them for scope.
- Used by one column: `user_role_assignments.role` (`casts()` of `UserRoleAssignment`). Its values equal `user_role_assignments.role` in the migration (`…100011…php:18`), proven by `ModelLayerTest.php:140`.

A DB-backed enum has no logic by default: it exists so that code writes `Role::MarinePilot` and not `'MARINE_PILOT'`, and so that CT-21 (`status-vocabulary.json`, F14) can be generated from the enums.

### 3.5 The factory pattern, in depth: `UserFactory` and friends

`database/factories/Access/UserFactory.php`:

- `:25` `definition()` returns a **valid row**: `name` (Faker), `username` = `'user.'.Str::lower(Str::random(10))` (lower case, 3+ chars, so `chk_user_username_lower` passes), `password_hash` = `static::$passwordHash ??= Hash::make('password')` (one hash for the whole run; bcrypt is slow), `phone` null, `is_active` true, `must_change_password` false.
- `:40` `inactive()` — state: `is_active => false` (the `ACCOUNT_DISABLED` case, F08).
- A factory never sets a generated or AI-owned column, and every default satisfies the table's CHECKs. Parent rows are created with `Model::factory()` (`'user_id' => User::factory()`).

`UserRoleAssignmentFactory.php:23-31` builds an active **terminal** role: `user_id => User::factory()`, `role => Role::TerminalOfficer`, `organization_unit_id => OrganizationUnit::factory()`, `unit_type => UnitType::Terminal`, `active_from => now()`, `active_to => null`. `:38` `ended()` gives a history row (`active_from` a month ago, `active_to` yesterday). `OrganizationUnitFactory.php:21-47`: default a terminal unit (`terminal_id => Terminal::factory()`), `harbourAuthority()` and `marineServices()` states (`terminal_id` null; only one such unit may exist because of `uq_org_unit_type_key`). The test helper `makeUserWithRole(?Role, …)` (`tests/Pest.php:86`) wraps these three factories for F09 tests.

Because factories are plain data, **the foreign key may not exist**: `ModelLayerTest` switches FK checks off (`beforeEach`, `:41-45`). With FK checks on, 59 of the 92 factories create a row on an empty database; 33 fail with MySQL error 1452 because they depend on a composite FK that needs coordinated parents (measured 2026-11-04 on `atlantech_testing_g2`; the list is in section 11).

### 3.6 Relation rules

All 234 relations are typed methods with a generic docblock (`@return BelongsTo<User, $this>`): 151 `belongsTo`, 73 `hasMany`, 2 `hasOne`, 8 `belongsToMany`.

1. **`belongsTo` for every foreign key whose target is in the same or an earlier module.** The method is named after the column (`confirmedBy`, `triggeredBy`, `rejectedBy`, `decidedBy`, `exportedBy`) when the target is `User` or the column name is not the model name; plain (`user`, `terminal`, `berth`) otherwise.
2. **`hasMany`/`hasOne` only inside a module, or from a later module to an earlier one.** A `hasMany` that would be ambiguous (a table with two FKs to the same parent) is suffixed with the column: `tideConstituentsByConstituentSet`, `deviationLogsByStatusUpdate`, `recommendationOptionTugsByOption`.
3. **No relation may point at a later module or a peer.** A script over the 234 relations (2026-11-04) confirms every target is in the same or an earlier layer. Consequences: three single-column foreign keys have no `belongsTo`: `organization_units.terminal_id` (Access→MasterData; replaced by `Terminal::organizationUnit()`), `audit_logs.actor_id` (Shared→Access; replaced by `User::auditLogs()`), `forecast_runs.berthing_request_id` (Environment→Berthing, peers; no inverse exists, query from the Berthing side). A rule of thumb from the plan: "all berthing requests of a terminal" is queried from `BerthingRequest`, never `Terminal::berthingRequests()` (P1-B5 section 6).
4. **Composite foreign keys get a relation on the entity only.** 38 FKs span two columns (F05 3.6). A `belongsTo` that matched only the first column would return the wrong row; so `StorageMovement` relates to `StorageFacility` by `storage_facility_id` alone, not to the compatibility row, and no composite-key model has a `hasMany` (P1-B5 review, must-fix 1 and 2).
5. **Many-to-many through pivot models.** Eight `belongsToMany(...)->using(PivotClass::class)`: `Berth::terminals`, `Terminal::berths` (via `BerthTerminal`); `Terminal::cargoTypes` (`TerminalCargoType`); `StorageFacility::cargoTypes` (`StorageFacilityCargoCompatibility`); `Equipment::cargoTypes` (`EquipmentCargoCompatibility`); `YardZone::imdgClasses` (`DgZoneAllowedClass`); `RecommendationOption::tugboats` (`RecommendationOptionTug`); `SolverRun::modelVersions` (`SolverRunModelVersion`). Link tables whose primary key is composite extend `Illuminate\Database\Eloquent\Relations\Pivot` and set `incrementing: false`. Three Pivot models are not many-to-many links (`StatusType`, `RecommendationReview`, `BerthingRequestCargo`); they extend `Pivot` only because their key is composite. `Equipment::cargoTypes()` and `StorageFacility::cargoTypes()` use `withPivot('terminal_id')` because that pivot column is NOT NULL: attach with the owner's `terminal_id`.
6. **One special relation:** `ActualStatusUpdate::statusType()` (`ActualStatusUpdate.php:64-74`) joins `status_types` on `status = code` **and** the row's own `component_type`. Because the join needs a per-row value it cannot be eager-loaded: it throws `LogicException` without a component type, and `has()`/`whereHas()` are unsupported. Query `StatusType` directly for lists.

### 3.7 The AI-ownership rule on `#[Fillable]` (CT-15)

- **Generated columns** are never fillable (F05 3.5). `ModelLayerTest.php:238` reads `information_schema` for every `STORED GENERATED` column and asserts it is not fillable.
- **Columns an AI worker UPDATEs** (the `GRANT UPDATE (...)` lists in `workers.sql`, F05 3.9) are not fillable either. The test reads `workers.sql` itself, so it moves with the grants. Examples: `SolverRun` lists only `trigger_type`, `triggered_by`, `horizon_start`, `horizon_end`, `queued_at`, `result_handled_at`; `Vessel` omits `call_sign` and `flag_state`; `BaplieFile` omits `parse_status` and `staged_payload`.
- **Tables a worker only inserts** (the 19 rows of `contracts/ai/table-ownership.md` with `INSERT:`): where the Backend may change "Nothing" (13 rows) the model has `#[Fillable([])]` (`ModelLayerTest.php:224`). Fourteen models have an empty list in total.
- **Backend-only columns** (`result_handled_at`, `drift_measurements.notified_at`) are fillable: the Backend sets them.
- When the Backend must set an AI-updatable column itself (the run row's `QUEUED` status and `solver_version` at creation; `vessels.call_sign` while NULL; `baplie_files.staged_payload` on a staff correction) it uses `forceFill([...])` explicitly. The docblocks of `Vessel`, `BaplieFile` and `SolverRun` say so (lead, 2026-10-29, P1-B5 section 7).

### 3.8 `RestrictsToOwnTasks` (only mentioned here; F09 owns it)

`PlanComponent` uses the trait `App\Modules\Access\Models\Concerns\RestrictsToOwnTasks` (`use HasFactory, RestrictsToOwnTasks;`). It adds `scopeVisibleTo(Builder, User)` (`:24`) and `authorizeTask(Request, User)` (`:46`): field roles see only rows with their own `assigned_user_id`. It lives in Access and is used by a Plan model because Plan may use Access but not the reverse (`A_orientation.md` A4). Apart from `Notifiable` on `User`, it is the only behavioural trait on any model.

### 3.9 The catalogue of all 92 models

How to read the columns. **Key and timestamps** follows 3.3 (`id` auto-increment unless stated). **Enum casts** are `column→Enum`. **Relations**: `→` belongsTo, `⇒many`/`⇒one` hasMany/hasOne, `↔` belongsToMany (through the pivot named in 3.6). **Special columns**: `RestrictsToOwnTasks`; generated columns (not fillable); AI-updated columns (not fillable, from `workers.sql`); `Fillable([])` (nothing mass-assignable); JSON casts; hidden attributes. The table is generated from the model files by a script and checked against the source (17 rows by hand, section 7).

**Shared** (1 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `AuditLog` | `audit_logs` | `id` auto-increment; no timestamps | `action`→AuditLogAction |  | JSON: `before_state`, `after_state` |

**Access** (4 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `OrganizationUnit` | `organization_units` | `id` auto-increment; no timestamps | `unit_type`→UnitType | `roleAssignments` ⇒many UserRoleAssignment | generated, not fillable: `single_key` |
| `User` | `users` | `id` auto-increment; both timestamps; extends `Authenticatable`; version token (microseconds) |  | `devices` ⇒many UserDevice<br>`roleAssignments` ⇒many UserRoleAssignment<br>`activeRoleAssignment` ⇒one UserRoleAssignment<br>`auditLogs` ⇒many AuditLog | hidden: `password_hash` |
| `UserDevice` | `user_devices` | `id` auto-increment; no timestamps | `platform`→DevicePlatform | `user` → User | hidden: `push_token` |
| `UserRoleAssignment` | `user_role_assignments` | `id` auto-increment; no timestamps | `role`→Role, `unit_type`→UnitType | `user` → User<br>`organizationUnit` → OrganizationUnit | generated, not fillable: `active_assignment_key` |

**License** (1 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `LicenseState` | `license_states` | composite or assigned key, not auto-increment; `updated_at` only; version token (microseconds) | `port_status`→PortStatus, `license_status`→LicenseStatus, `mode`→LicenseMode, `read_only_reason`→ReadOnlyReason |  | hidden: `token` |

**MasterData** (38 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `Berth` | `berths` | `id` auto-increment; `updated_at` only; version token (microseconds) | `status`→FacilityStatus | `berthTerminals` ⇒many BerthTerminal<br>`safetyConstraints` ⇒many SafetyConstraint<br>`terminals` ↔ Terminal |  |
| `BerthTerminal` | `berth_terminals` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` |  | `berth` → Berth<br>`terminal` → Terminal |  |
| `CapabilityType` | `capability_types` | `id` auto-increment; no timestamps | `unit`→CapabilityTypeUnit | `equipmentCapabilities` ⇒many EquipmentCapability |  |
| `CargoType` | `cargo_types` | `id` auto-increment; no timestamps | `category`→CargoTypeCategory | `imdgClass` → ImdgClass<br>`terminalCargoTypes` ⇒many TerminalCargoType |  |
| `ChannelWaypoint` | `channel_waypoints` | `id` auto-increment; no timestamps |  | `channel` → NavigationChannel |  |
| `ConstraintType` | `constraint_types` | `id` auto-increment; no timestamps | `category`→ConstraintTypeCategory, `unit`→ConstraintTypeUnit, `comparator`→ConstraintTypeComparator | `safetyConstraints` ⇒many SafetyConstraint |  |
| `Container` | `containers` | `id` auto-increment; no timestamps |  | `sizeType` → ContainerSizeType | `Fillable([])` (insert-only by workers/Backend services) |
| `ContainerSizeType` | `container_size_types` | `id` auto-increment; no timestamps | `type_group`→ContainerSizeTypeGroup | `containersBySizeType` ⇒many Container | generated, not fillable: `is_reefer` |
| `Equipment` | `equipment` | `id` auto-increment; `updated_at` only; version token (microseconds) |  | `equipmentCategory` → EquipmentCategory<br>`terminal` → Terminal<br>`equipmentCapabilities` ⇒many EquipmentCapability<br>`equipmentCargoCompatibilities` ⇒many EquipmentCargoCompatibility<br>`cargoTypes` ↔ CargoType | generated, not fillable: `available_quantity` |
| `EquipmentCapability` | `equipment_capabilities` | `id` auto-increment; no timestamps |  | `equipment` → Equipment<br>`capabilityType` → CapabilityType |  |
| `EquipmentCargoCompatibility` | `equipment_cargo_compatibilities` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` |  | `equipment` → Equipment<br>`terminal` → Terminal |  |
| `EquipmentCategory` | `equipment_categories` | `id` auto-increment; no timestamps | `equipment_role`→EquipmentRole | `safetyConstraints` ⇒many SafetyConstraint<br>`equipment` ⇒many Equipment |  |
| `ExternalSystem` | `external_systems` | `id` auto-increment; no timestamps |  |  |  |
| `ImdgClass` | `imdg_classes` | key `code` (not `id`), set by hand; no timestamps |  | `safetyConstraintsByImdgClass` ⇒many SafetyConstraint<br>`cargoTypesByImdgClass` ⇒many CargoType |  |
| `MlModel` | `ml_models` | `id` auto-increment; no timestamps | `model_key`→MlModelKey | `mlModelVersions` ⇒many MlModelVersion |  |
| `MlModelVersion` | `ml_model_versions` | `id` auto-increment; no timestamps | `status`→MlModelVersionStatus | `mlModel` → MlModel | generated, not fillable: `champion_key`; JSON: `hyperparameters` |
| `NavigationChannel` | `navigation_channels` | `id` auto-increment; both timestamps; version token (microseconds) |  | `channelWaypointsByChannel` ⇒many ChannelWaypoint |  |
| `PilotProfile` | `pilot_profiles` | key `user_id` (not `id`), set by hand; `updated_at` only; version token (microseconds) | `pilot_rank`→PilotRank | `user` → User<br>`pilotSchedulesByPilotUser` ⇒many PilotSchedule |  |
| `PilotRankLimit` | `pilot_rank_limits` | key `pilot_rank` (not `id`), set by hand; `updated_at` only; version token (microseconds) | `pilot_rank`→PilotRank |  |  |
| `PilotSchedule` | `pilot_schedules` | `id` auto-increment; no timestamps |  | `createdBy` → User<br>`pilotUser` → PilotProfile<br>`portShift` → PortShift |  |
| `PortProfile` | `port_profiles` | composite or assigned key, not auto-increment; both timestamps; version token (microseconds) |  |  | JSON: `boundary_coordinates` |
| `PortSetting` | `port_settings` | `id` auto-increment; `updated_at` only; version token (microseconds) | `value_type`→PortSettingValueType | `updatedBy` → User |  |
| `PortShift` | `port_shifts` | `id` auto-increment; `updated_at` only; version token (microseconds) |  | `pilotSchedules` ⇒many PilotSchedule<br>`tugMasterSchedules` ⇒many TugMasterSchedule |  |
| `SafetyConstraint` | `safety_constraints` | `id` auto-increment; `updated_at` only; version token (microseconds) |  | `berth` → Berth<br>`equipmentCategory` → EquipmentCategory<br>`imdgClass` → ImdgClass<br>`terminal` → Terminal<br>`constraintType` → ConstraintType<br>`createdBy` → User<br>`vesselType` → VesselType |  |
| `StatusType` | `status_types` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` | `component_type`→ComponentType |  |  |
| `StorageFacility` | `storage_facilities` | `id` auto-increment; `updated_at` only; version token (microseconds) | `terminal_type`→TerminalType, `detail_kind`→StorageDetailKind, `capacity_unit`→CapacityUnit, `status`→FacilityStatus | `storageType` → StorageType<br>`terminal` → Terminal<br>`tankDetails` ⇒many TankDetail<br>`warehouseDetails` ⇒many WarehouseDetail<br>`storageFacilityCargoCompatibilities` ⇒many StorageFacilityCargoCompatibility<br>`cargoTypes` ↔ CargoType | generated, not fillable: `available_capacity` |
| `StorageFacilityCargoCompatibility` | `storage_facility_cargo_compatibilities` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` |  | `storageFacility` → StorageFacility<br>`terminal` → Terminal |  |
| `StorageType` | `storage_types` | `id` auto-increment; no timestamps | `detail_kind`→StorageDetailKind | `storageFacilities` ⇒many StorageFacility |  |
| `TankDetail` | `tank_details` | key `storage_facility_id` (not `id`), set by hand; no timestamps | `detail_kind`→StorageDetailKind | `storageFacility` → StorageFacility |  |
| `Terminal` | `terminals` | `id` auto-increment; `updated_at` only; version token (microseconds) | `terminal_type`→TerminalType | `berthTerminals` ⇒many BerthTerminal<br>`safetyConstraints` ⇒many SafetyConstraint<br>`terminalCargoTypes` ⇒many TerminalCargoType<br>`storageFacilities` ⇒many StorageFacility<br>`equipment` ⇒many Equipment<br>`terminalProductivityProfiles` ⇒many TerminalProductivityProfile<br>`berths` ↔ Berth<br>`cargoTypes` ↔ CargoType<br>`organizationUnit` ⇒one OrganizationUnit | JSON: `boundary_coordinates` |
| `TerminalCargoType` | `terminal_cargo_types` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` |  | `cargoType` → CargoType<br>`terminal` → Terminal |  |
| `TerminalProductivityProfile` | `terminal_productivity_profiles` | `id` auto-increment; no timestamps | `terminal_type`→TerminalType, `rate_unit`→TerminalProductivityProfileRateUnit | `terminal` → Terminal<br>`vesselType` → VesselType |  |
| `TugMasterProfile` | `tug_master_profiles` | key `user_id` (not `id`), set by hand; `updated_at` only; version token (microseconds) |  | `assignedTugboat` → Tugboat<br>`user` → User<br>`tugMasterSchedulesByTugMasterUser` ⇒many TugMasterSchedule |  |
| `TugMasterSchedule` | `tug_master_schedules` | `id` auto-increment; no timestamps |  | `createdBy` → User<br>`tugMasterUser` → TugMasterProfile<br>`portShift` → PortShift |  |
| `Tugboat` | `tugboats` | `id` auto-increment; `updated_at` only; version token (microseconds) | `tug_power_unit`→TugPowerUnit, `status`→TugboatStatus | `tugMasterProfilesByAssignedTugboat` ⇒many TugMasterProfile |  |
| `Vessel` | `vessels` | `id` auto-increment; `updated_at` only; version token (microseconds) |  | `vesselType` → VesselType | AI-updated, not fillable: `call_sign`, `flag_state` |
| `VesselType` | `vessel_types` | `id` auto-increment; no timestamps |  | `safetyConstraints` ⇒many SafetyConstraint<br>`vessels` ⇒many Vessel<br>`terminalProductivityProfiles` ⇒many TerminalProductivityProfile |  |
| `WarehouseDetail` | `warehouse_details` | key `storage_facility_id` (not `id`), set by hand; no timestamps | `detail_kind`→StorageDetailKind | `storageFacility` → StorageFacility |  |

**Environment** (6 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `EnvironmentForecastPoint` | `environment_forecast_points` | `id` auto-increment; no timestamps |  | `forecastRun` → ForecastRun |  |
| `ForecastRun` | `forecast_runs` | `id` auto-increment; no timestamps | `pull_type`→ForecastRunPullType, `provider_rank`→ForecastRunProviderRank, `status`→ForecastRunStatus, `wind_speed_unit`→SpeedUnit, `current_speed_unit`→SpeedUnit, `visibility_unit`→ForecastRunVisibilityUnit | `submittedBy` → User<br>`environmentForecastPoints` ⇒many EnvironmentForecastPoint |  |
| `TideConstituent` | `tide_constituents` | `id` auto-increment; no timestamps |  | `constituentSet` → TideConstituentSet |  |
| `TideConstituentSet` | `tide_constituent_sets` | `id` auto-increment; no timestamps |  | `tideConstituentsByConstituentSet` ⇒many TideConstituent<br>`tidePredictionRunsByConstituentSet` ⇒many TidePredictionRun | generated, not fillable: `active_key` |
| `TidePredictionPoint` | `tide_prediction_points` | `id` auto-increment; no timestamps |  | `tidePredictionRun` → TidePredictionRun | `Fillable([])` (insert-only by workers/Backend services) |
| `TidePredictionRun` | `tide_prediction_runs` | `id` auto-increment; no timestamps |  | `constituentSet` → TideConstituentSet<br>`tidePredictionPoints` ⇒many TidePredictionPoint | `Fillable([])` (insert-only by workers/Backend services) |

**Berthing** (3 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `BerthingRequest` | `berthing_requests` | `id` auto-increment; both timestamps; version token (microseconds) | `source`→BerthingRequestSource, `draft_source`→DataSource, `eta_source`→DataSource, `ports_source`→DataSource, `status`→BerthingRequestStatus | `externalSystem` → ExternalSystem<br>`requestedTerminal` → Terminal<br>`submittedBy` → User<br>`vessel` → Vessel<br>`berthingRequestCargos` ⇒many BerthingRequestCargo<br>`berthingRequestStatusHistories` ⇒many BerthingRequestStatusHistory |  |
| `BerthingRequestCargo` | `berthing_request_cargos` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` | `operation`→BerthingRequestCargoOperation, `quantity_unit`→BerthingRequestCargoQuantityUnit | `berthingRequest` → BerthingRequest<br>`terminal` → Terminal |  |
| `BerthingRequestStatusHistory` | `berthing_request_status_histories` | `id` auto-increment; no timestamps | `from_status`→BerthingRequestStatus, `to_status`→BerthingRequestStatus | `berthingRequest` → BerthingRequest<br>`changedBy` → User |  |

**Baplie** (4 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `BaplieFile` | `baplie_files` | `id` auto-increment; `updated_at` only; version token (microseconds) | `parse_status`→BaplieFileParseStatus, `status`→BaplieFileStatus | `berthingRequest` → BerthingRequest<br>`uploadedBy` → User<br>`vessel` → Vessel<br>`correctedBy` → User<br>`baplieParseIssues` ⇒many BaplieParseIssue<br>`containerManifestEntries` ⇒many ContainerManifestEntry<br>`baplieParseRuns` ⇒many BaplieParseRun | generated, not fillable: `live_hash_key`, `active_request_key`; AI-updated, not fillable: `parse_status`, `staged_payload`; JSON: `staged_payload` |
| `BaplieParseIssue` | `baplie_parse_issues` | `id` auto-increment; no timestamps | `severity`→BaplieParseIssueSeverity | `baplieFile` → BaplieFile | `Fillable([])` (insert-only by workers/Backend services) |
| `BaplieParseRun` | `baplie_parse_runs` | `id` auto-increment; no timestamps | `source`→BaplieParseRunSource, `status`→RunStatus, `outcome`→BaplieParseRunOutcome, `error_category`→BaplieParseRunErrorCategory | `baplieFile` → BaplieFile<br>`triggeredBy` → User | generated, not fillable: `active_key`; AI-updated, not fillable: `container_count`, `error_category`, `finished_at`, `outcome`, `result_detail`, `retry_count`, `started_at`, `status`, `worker_version`; JSON: `result_detail` |
| `ContainerManifestEntry` | `container_manifest_entries` | `id` auto-increment; no timestamps | `full_empty`→ContainerManifestEntryFullEmpty | `container` → Container<br>`baplieFile` → BaplieFile<br>`imdgClass` → ImdgClass | `Fillable([])` (insert-only by workers/Backend services) |

**Recommendation** (15 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `BlockedPeriod` | `blocked_periods` | `id` auto-increment; no timestamps |  | `limitingConstraintType` → ConstraintType<br>`proposal` → EntryWindowProposal | `Fillable([])` (insert-only by workers/Backend services) |
| `DemandForecastSlot` | `demand_forecast_slots` | `id` auto-increment; no timestamps | `method`→DemandForecastSlotMethod | `modelVersion` → MlModelVersion | `Fillable([])` (insert-only by workers/Backend services) |
| `EntryWindowProposal` | `entry_window_proposals` | `id` auto-increment; `updated_at` only; version token (microseconds) | `status`→EntryWindowProposalStatus | `biasCorrectionVersion` → MlModelVersion<br>`forecastRun` → ForecastRun<br>`rejectedBy` → User<br>`berthingRequest` → BerthingRequest<br>`tidePredictionRun` → TidePredictionRun<br>`confirmedBy` → User<br>`confirmedWindow` → FeasibleWindow<br>`part1Runs` ⇒many Part1Run<br>`feasibleWindowsByProposal` ⇒many FeasibleWindow<br>`blockedPeriodsByProposal` ⇒many BlockedPeriod<br>`recommendations` ⇒many Recommendation | generated, not fillable: `confirmed_key` |
| `FeasibleWindow` | `feasible_windows` | `id` auto-increment; no timestamps |  | `proposal` → EntryWindowProposal<br>`entryWindowProposalsByConfirmedWindow` ⇒many EntryWindowProposal | `Fillable([])` (insert-only by workers/Backend services) |
| `ObjectiveTerm` | `objective_terms` | `id` auto-increment; no timestamps |  | `recommendationRationaleFactors` ⇒many RecommendationRationaleFactor |  |
| `Part1Run` | `part1_runs` | `id` auto-increment; no timestamps | `status`→RunStatus, `outcome`→Part1RunOutcome | `entryWindowProposal` → EntryWindowProposal<br>`berthingRequest` → BerthingRequest | AI-updated, not fillable: `entry_window_proposal_id`, `finished_at`, `outcome`, `retry_count`, `started_at`, `status` |
| `RationaleTemplate` | `rationale_templates` | `id` auto-increment; no timestamps |  | `recommendationOptions` ⇒many RecommendationOption | generated, not fillable: `active_code` |
| `Recommendation` | `recommendations` | `id` auto-increment; `updated_at` only; version token (microseconds) | `status`→RecommendationStatus, `source`→RecommendationSource, `purpose`→RecommendationPurpose | `entryWindowProposal` → EntryWindowProposal<br>`berthingRequest` → BerthingRequest<br>`solverRun` → SolverRun<br>`recommendationOptions` ⇒many RecommendationOption<br>`recommendationReviews` ⇒many RecommendationReview | generated, not fillable: `active_key` |
| `RecommendationOption` | `recommendation_options` | `id` auto-increment; no timestamps | `service_time_method`→RecommendationOptionServiceTimeMethod | `berth` → Berth<br>`pilotUser` → PilotProfile<br>`recommendation` → Recommendation<br>`rationaleTemplate` → RationaleTemplate<br>`recommendationOptionTugsByOption` ⇒many RecommendationOptionTug<br>`recommendationOverrides` ⇒many RecommendationOverride<br>`recommendationRationaleFactorsByOption` ⇒many RecommendationRationaleFactor<br>`tugboats` ↔ Tugboat | `Fillable([])` (insert-only by workers/Backend services); JSON: `rationale_params` |
| `RecommendationOptionTug` | `recommendation_option_tugs` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` |  | `option` → RecommendationOption<br>`tugboat` → Tugboat | `Fillable([])` (insert-only by workers/Backend services) |
| `RecommendationOverride` | `recommendation_overrides` | `id` auto-increment; no timestamps | `component_type`→RecommendationOverrideComponentType | `overrideBerth` → Berth<br>`overridePilotUser` → PilotProfile<br>`overrideTugboat` → Tugboat<br>`recommendationOption` → RecommendationOption<br>`originalBerth` → Berth<br>`originalPilotUser` → PilotProfile<br>`originalTugboat` → Tugboat<br>`overriddenBy` → User |  |
| `RecommendationRationaleFactor` | `recommendation_rationale_factors` | `id` auto-increment; no timestamps | `factor_type`→RationaleFactorType | `constraintType` → ConstraintType<br>`objectiveTerm` → ObjectiveTerm<br>`option` → RecommendationOption | `Fillable([])` (insert-only by workers/Backend services) |
| `RecommendationReview` | `recommendation_reviews` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` |  | `recommendation` → Recommendation<br>`user` → User |  |
| `SolverRun` | `solver_runs` | `id` auto-increment; no timestamps | `trigger_type`→SolverRunTriggerType, `status`→SolverRunStatus | `triggeredBy` → User<br>`solverRunModelVersions` ⇒many SolverRunModelVersion<br>`recommendations` ⇒many Recommendation<br>`modelVersions` ↔ MlModelVersion | AI-updated, not fillable: `finished_at`, `objective_value`, `result_detail`, `solve_time_ms`, `solver_version`, `started_at`, `status`; JSON: `result_detail` |
| `SolverRunModelVersion` | `solver_run_model_versions` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` |  | `modelVersion` → MlModelVersion<br>`solverRun` → SolverRun | `Fillable([])` (insert-only by workers/Backend services) |

**Plan** (2 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `OperationalPlan` | `operational_plans` | `id` auto-increment; `updated_at` only; version token (microseconds) | `status`→OperationalPlanStatus | `recommendationOption` → RecommendationOption<br>`recommendation` → Recommendation<br>`confirmedBy` → User<br>`planComponents` ⇒many PlanComponent | generated, not fillable: `active_key` |
| `PlanComponent` | `plan_components` | `id` auto-increment; `updated_at` only; version token (microseconds) | `component_type`→ComponentType, `reservation_state`→PlanComponentReservationState | `assignedUser` → User<br>`assignedBy` → User<br>`berth` → Berth<br>`operationalPlan` → OperationalPlan<br>`terminal` → Terminal<br>`tugboat` → Tugboat | `RestrictsToOwnTasks` |

**Yard** (12 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `DgZoneAllowedClass` | `dg_zone_allowed_classes` | composite or assigned key, not auto-increment; no timestamps; extends `Pivot` | `zone_detail`→ZoneDetail | `imdgClass` → ImdgClass<br>`yardZone` → YardZone |  |
| `OogZoneDetail` | `oog_zone_details` | key `yard_zone_id` (not `id`), set by hand; no timestamps | `zone_detail`→ZoneDetail | `yardZone` → YardZone |  |
| `ReeferZoneDetail` | `reefer_zone_details` | key `yard_zone_id` (not `id`), set by hand; no timestamps | `zone_detail`→ZoneDetail | `yardZone` → YardZone |  |
| `StorageAllocationItem` | `storage_allocation_items` | `id` auto-increment; no timestamps | `rec_type`→TerminalRecommendationType, `quantity_unit`→CapacityUnit | `terminalRecommendation` → TerminalRecommendation<br>`storageFacility` → StorageFacility |  |
| `StorageMovement` | `storage_movements` | `id` auto-increment; no timestamps | `direction`→StorageMovementDirection, `source`→StorageMovementSource | `storageFacility` → StorageFacility<br>`planComponent` → PlanComponent<br>`recordedBy` → User |  |
| `TerminalRecommendation` | `terminal_recommendations` | `id` auto-increment; `updated_at` only; version token (microseconds) | `terminal_type`→TerminalType, `rec_type`→TerminalRecommendationType, `decision`→TerminalRecommendationDecision, `lifecycle_status`→TerminalRecommendationLifecycleStatus | `exportedBy` → User<br>`recommendation` → Recommendation<br>`rationaleTemplate` → RationaleTemplate<br>`terminal` → Terminal<br>`decidedBy` → User<br>`yardPlacementItems` ⇒many YardPlacementItem<br>`storageAllocationItems` ⇒many StorageAllocationItem<br>`yardRuns` ⇒many YardRun | AI-updated, not fillable: `confidence`, `failure_reason`, `lifecycle_status`, `rationale_params`, `rationale_template_id`; JSON: `rationale_params` |
| `YardBlock` | `yard_blocks` | `id` auto-increment; no timestamps |  | `yardZone` → YardZone<br>`yardSlots` ⇒many YardSlot<br>`yardPlacementItems` ⇒many YardPlacementItem |  |
| `YardPlacementItem` | `yard_placement_items` | `id` auto-increment; no timestamps | `rec_type`→TerminalRecommendationType | `yardBlock` → YardBlock<br>`manifestEntry` → ContainerManifestEntry<br>`yardSlot` → YardSlot<br>`terminalRecommendation` → TerminalRecommendation | `Fillable([])` (insert-only by workers/Backend services) |
| `YardRun` | `yard_runs` | `id` auto-increment; no timestamps | `status`→RunStatus, `outcome`→YardRunOutcome, `error_category`→YardRunErrorCategory | `terminalRecommendation` → TerminalRecommendation<br>`triggeredBy` → User | generated, not fillable: `active_key`; AI-updated, not fillable: `error_category`, `finished_at`, `outcome`, `result_detail`, `retry_count`, `started_at`, `status`, `worker_version`; JSON: `result_detail` |
| `YardSlot` | `yard_slots` | `id` auto-increment; `updated_at` only; version token (microseconds) | `status`→YardSlotStatus | `yardBlock` → YardBlock<br>`container` → Container<br>`yardPlacementItems` ⇒many YardPlacementItem |  |
| `YardZone` | `yard_zones` | `id` auto-increment; no timestamps | `detail_kind`→StorageDetailKind, `zone_detail`→ZoneDetail | `yardZoneType` → YardZoneType<br>`containerYard` → StorageFacility<br>`dgZoneAllowedClasses` ⇒many DgZoneAllowedClass<br>`oogZoneDetails` ⇒many OogZoneDetail<br>`reeferZoneDetails` ⇒many ReeferZoneDetail<br>`yardBlocks` ⇒many YardBlock<br>`imdgClasses` ↔ ImdgClass |  |
| `YardZoneType` | `yard_zone_types` | `id` auto-increment; no timestamps | `zone_detail`→ZoneDetail | `yardZones` ⇒many YardZone |  |

**Dispatch** (1 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `NotificationLog` | `notification_logs` | `id` auto-increment; no timestamps | `related_entity_type`→NotificationLogRelatedEntityType, `type`→NotificationLogType, `channel`→NotificationLogChannel, `status`→NotificationLogStatus | `user` → User |  |

**Execution** (2 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `ActualStatusUpdate` | `actual_status_updates` | `id` auto-increment; no timestamps | `component_type`→ComponentType, `sync_source`→ActualStatusUpdateSyncSource | `planComponent` → PlanComponent<br>`statusType` → StatusType<br>`reportedBy` → User<br>`deviationLogsByStatusUpdate` ⇒many DeviationLog |  |
| `DeviationLog` | `deviation_logs` | `id` auto-increment; no timestamps | `deviation_type`→DeviationType | `planComponent` → PlanComponent<br>`statusUpdate` → ActualStatusUpdate |  |

**Learning** (3 models)

| Model | Table | Key and timestamps | Enum casts | Relations | Special columns |
| --- | --- | --- | --- | --- | --- |
| `DriftMeasurement` | `drift_measurements` | `id` auto-increment; no timestamps | `metric_type`→DriftMetricType | `modelVersion` → MlModelVersion | generated, not fillable: `is_drifted` |
| `EvaluationMetric` | `evaluation_metrics` | `id` auto-increment; no timestamps | `metric_name`→EvaluationMetricName, `dataset_type`→EvaluationMetricDatasetType | `retrainingRun` → RetrainingRun<br>`modelVersion` → MlModelVersion | `Fillable([])` (insert-only by workers/Backend services) |
| `RetrainingRun` | `retraining_runs` | `id` auto-increment; no timestamps | `decision`→RetrainingRunDecision | `challengerVersion` → MlModelVersion<br>`championVersion` → MlModelVersion<br>`mlModel` → MlModel<br>`reviewedBy` → User<br>`evaluationMetrics` ⇒many EvaluationMetric | generated, not fillable: `review_pending` |

### 3.10 The catalogue of all 80 enums

All enums are string-backed. **Backed values** lists the case values in order (case names are the TitleCase of the value, 3.4). **Source column(s)** shows where the enum is cast. Only `Role` has helper methods. The three enums that are not database columns have no source column; their purpose is in the text.

**Shared** (6 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `AuditLogAction` | 6: `CREATE`, `UPDATE`, `DELETE`, `STATUS_CHANGE`, `SAFETY_EXCEPTION`, `REJECTED_ATTEMPT` | `audit_logs.action` |  |
| `CapacityUnit` | 5: `TONNES`, `CUBIC_METRES`, `SQUARE_METRES`, `TEU`, `UNITS` | `storage_facilities.capacity_unit`, `storage_allocation_items.quantity_unit` |  |
| `ComponentType` | 4: `BERTH`, `PILOT`, `TUG`, `TERMINAL_OPS` | `actual_status_updates.component_type`, `status_types.component_type`, `plan_components.component_type` |  |
| `RunStatus` | 5: `QUEUED`, `RUNNING`, `DONE`, `FAILED`, `REPLACED` | `baplie_parse_runs.status`, `part1_runs.status`, `yard_runs.status` |  |
| `StorageDetailKind` | 4: `NONE`, `WAREHOUSE`, `CONTAINER_YARD`, `TANK` | `storage_facilities.detail_kind`, `storage_types.detail_kind`, `tank_details.detail_kind`, `warehouse_details.detail_kind`, `yard_zones.detail_kind` |  |
| `TerminalType` | 2: `CONTAINER`, `NON_CONTAINER` | `storage_facilities.terminal_type`, `terminals.terminal_type`, `terminal_productivity_profiles.terminal_type`, `terminal_recommendations.terminal_type` |  |

**Access** (5 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `Ability` | 27: `port-state.read`, `users.manage`, `devices.manage`, `audit.read`, `license.manage`, `ai-model.review`, `plans.safety-exception`, `port-setup.manage`, `pilot-roster.manage`, `pilotage.assign`, `tug-fleet.manage`, `tug.assign`, `berthing-requests.manage`, `baplie.manage`, `entry-window.decide`, `environment.manual-entry`, `recommendations.review`, `plans.confirm`, `plans.cancel`, `deviations.review`, `equipment.manage`, `yard.manage`, `yard-recommendations.decide`, `tos-export.record`, `tasks.own.read`, `tasks.own.update`, `terminal-status.update` | not a database column (What a role may do, grouped by function area. Each route declares the one it needs) |  |
| `AccessRefusalReason` | 4: `no_active_role`, `role_not_allowed`, `terminal_out_of_scope`, `not_own_task` | not a database column (Why a role, terminal or own-task check refused a request; written to the audit row's `after_state.reason` (FR-GL-02)) |  |
| `DevicePlatform` | 2: `ANDROID`, `IOS` | `user_devices.platform` |  |
| `Role` | 12: `PORT_GM`, `PORT_STAFF`, `MARINE_SAFETY_OFFICER`, `PILOTAGE_MANAGER`, `PILOTAGE_OFFICER`, `MARINE_PILOT`, `TUG_MANAGER`, `TUG_OFFICER`, `TUG_MASTER`, `TERMINAL_ADMIN`, `TERMINAL_OFFICER`, `TERMINAL_SUPERVISOR` | `user_role_assignments.role` | `isTerminalScoped()`, `isFieldRole()` |
| `UnitType` | 3: `HARBOUR_AUTHORITY`, `MARINE_SERVICES`, `TERMINAL` | `organization_units.unit_type`, `user_role_assignments.unit_type` |  |

**License** (4 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `LicenseMode` | 3: `SETUP`, `FULL`, `READ_ONLY` | `license_states.mode` |  |
| `LicenseStatus` | 5: `PENDING`, `ACTIVE`, `SUSPENDED`, `EXPIRED`, `REVOKED` | `license_states.license_status` |  |
| `PortStatus` | 4: `ONBOARDING`, `ACTIVE`, `SUSPENDED`, `OFFBOARDED` | `license_states.port_status` |  |
| `ReadOnlyReason` | 10: `NONE`, `NO_LICENSE`, `TOKEN_EXPIRED`, `SUSPENDED`, `REVOKED`, `INVALID_SIGNATURE`, `CLOCK_ROLLBACK`, `PORT_SUSPENDED`, `PORT_OFFBOARDED`, `LICENSE_NOT_YET_IN_EFFECT` | `license_states.read_only_reason` |  |

**MasterData** (15 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `CapabilityTypeUnit` | 7: `TONNES`, `METRES`, `TONNES_PER_HOUR`, `CUBIC_METRES_PER_HOUR`, `MOVES_PER_HOUR`, `COUNT`, `BAR` | `capability_types.unit` |  |
| `CargoTypeCategory` | 7: `GENERAL_CARGO`, `BREAK_BULK`, `DRY_BULK`, `LIQUID_BULK`, `GAS`, `RO_RO`, `CONTAINERIZED` | `cargo_types.category` |  |
| `ConstraintTypeCategory` | 3: `ENVIRONMENTAL`, `UKC`, `VESSEL_SAFETY` | `constraint_types.category` |  |
| `ConstraintTypeComparator` | 2: `MAX_ALLOWED`, `MIN_REQUIRED` | `constraint_types.comparator` |  |
| `ConstraintTypeUnit` | 8: `KNOTS`, `METRES_PER_SECOND`, `METRES`, `NAUTICAL_MILES`, `PERCENT`, `DEGREES`, `MINUTES`, `TONNES` | `constraint_types.unit` |  |
| `ContainerSizeTypeGroup` | 7: `GENERAL`, `REEFER`, `TANK`, `OPEN_TOP`, `FLAT_RACK`, `VENTILATED`, `BULK` | `container_size_types.type_group` |  |
| `EquipmentRole` | 3: `VESSEL_HANDLING`, `CARGO_TRANSPORT`, `TRANSFER_SYSTEM` | `equipment_categories.equipment_role` |  |
| `FacilityStatus` | 3: `ACTIVE`, `MAINTENANCE`, `INACTIVE` | `berths.status`, `storage_facilities.status` |  |
| `MlModelKey` | 4: `M2_SERVICE_TIME`, `M3_DEMAND_FORECAST`, `M4_DELAY_RISK`, `FORECAST_BIAS_CORRECTION` | `ml_models.model_key` |  |
| `MlModelVersionStatus` | 4: `CHALLENGER`, `CHAMPION`, `ARCHIVED`, `REJECTED` | `ml_model_versions.status` |  |
| `PilotRank` | 3: `FIRST`, `SECOND`, `THIRD` | `pilot_profiles.pilot_rank`, `pilot_rank_limits.pilot_rank` |  |
| `PortSettingValueType` | 5: `INT`, `DECIMAL`, `BOOL`, `STRING`, `JSON` | `port_settings.value_type` |  |
| `TerminalProductivityProfileRateUnit` | 4: `MOVES_PER_HOUR`, `TONNES_PER_HOUR`, `CUBIC_METRES_PER_HOUR`, `UNITS_PER_HOUR` | `terminal_productivity_profiles.rate_unit` |  |
| `TugPowerUnit` | 3: `BOLLARD_PULL_TONNES`, `HP`, `KW` | `tugboats.tug_power_unit` |  |
| `TugboatStatus` | 4: `AVAILABLE`, `BUSY`, `MAINTENANCE`, `INACTIVE` | `tugboats.status` |  |

**Environment** (5 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `ForecastRunProviderRank` | 3: `PRIMARY`, `SECONDARY`, `MANUAL` | `forecast_runs.provider_rank` |  |
| `ForecastRunPullType` | 3: `SCHEDULED`, `ON_DEMAND`, `MANUAL` | `forecast_runs.pull_type` |  |
| `ForecastRunStatus` | 2: `SUCCESS`, `FAILED` | `forecast_runs.status` |  |
| `ForecastRunVisibilityUnit` | 3: `NAUTICAL_MILES`, `KILOMETRES`, `METRES` | `forecast_runs.visibility_unit` |  |
| `SpeedUnit` | 3: `KNOTS`, `METRES_PER_SECOND`, `KM_PER_HOUR` | `forecast_runs.wind_speed_unit`, `forecast_runs.current_speed_unit` |  |

**Berthing** (5 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `BerthingRequestCargoOperation` | 2: `DISCHARGE`, `LOAD` | `berthing_request_cargos.operation` |  |
| `BerthingRequestCargoQuantityUnit` | 3: `TONNES`, `CUBIC_METRES`, `UNITS` | `berthing_request_cargos.quantity_unit` |  |
| `BerthingRequestSource` | 2: `MANUAL`, `EXTERNAL` | `berthing_requests.source` |  |
| `BerthingRequestStatus` | 11: `SUBMITTED`, `NEEDS_REVISION`, `PRELIMINARY_APPROVED`, `CUSTOMS_APPROVED`, `ENTRY_WINDOW_CONFIRMED`, `RECOMMENDATION_GENERATED`, `PLAN_CONFIRMED`, `PLAN_CANCELLED`, `COMPLETED`, `REJECTED`, `CANCELLED` | `berthing_requests.status`, `berthing_request_status_histories.from_status`, `berthing_request_status_histories.to_status` |  |
| `DataSource` | 3: `MANUAL`, `EGMSW_FEED`, `REGISTRY` | `berthing_requests.draft_source`, `berthing_requests.eta_source`, `berthing_requests.ports_source` |  |

**Baplie** (7 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `BaplieFileParseStatus` | 4: `PENDING`, `PARSED`, `FAILED`, `QUARANTINED` | `baplie_files.parse_status` |  |
| `BaplieFileStatus` | 4: `STAGED`, `ACTIVE`, `REPLACED`, `WITHDRAWN` | `baplie_files.status` |  |
| `BaplieParseIssueSeverity` | 2: `ERROR`, `WARNING` | `baplie_parse_issues.severity` |  |
| `BaplieParseRunErrorCategory` | 6: `INVALID_ENVELOPE`, `PRECONDITION_FAILED`, `DATA_UNAVAILABLE`, `CONFIGURATION_ERROR`, `TIMEOUT`, `INTERNAL_ERROR` | `baplie_parse_runs.error_category` |  |
| `BaplieParseRunOutcome` | 3: `PARSED`, `QUARANTINED`, `REJECTED` | `baplie_parse_runs.outcome` |  |
| `BaplieParseRunSource` | 2: `FILE`, `STAGED_PAYLOAD` | `baplie_parse_runs.source` |  |
| `ContainerManifestEntryFullEmpty` | 2: `FULL`, `EMPTY` | `container_manifest_entries.full_empty` |  |

**Recommendation** (11 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `DemandForecastSlotMethod` | 2: `AGGREGATION`, `LIGHTGBM` | `demand_forecast_slots.method` |  |
| `EntryWindowProposalStatus` | 4: `PROPOSED`, `CONFIRMED`, `REPLACED`, `REJECTED` | `entry_window_proposals.status` |  |
| `Part1RunOutcome` | 3: `WINDOWS_FOUND`, `NO_FEASIBLE_WINDOW`, `ENV_DATA_UNAVAILABLE` | `part1_runs.outcome` |  |
| `RationaleFactorType` | 2: `CONSTRAINT`, `OBJECTIVE_TERM` | `recommendation_rationale_factors.factor_type` |  |
| `RecommendationOptionServiceTimeMethod` | 2: `FORMULA`, `ML` | `recommendation_options.service_time_method` |  |
| `RecommendationOverrideComponentType` | 4: `BERTH`, `PILOT`, `TUG`, `START_TIME` | `recommendation_overrides.component_type` |  |
| `RecommendationPurpose` | 2: `INITIAL`, `DELAY_REPLAN` | `recommendations.purpose` |  |
| `RecommendationSource` | 2: `AI`, `FIFO` | `recommendations.source` |  |
| `RecommendationStatus` | 6: `PENDING_REVIEW`, `CONFIRMED`, `REJECTED`, `INVALIDATED`, `REPLACED`, `MODIFIED_BY_STAFF` | `recommendations.status` |  |
| `SolverRunStatus` | 6: `QUEUED`, `RUNNING`, `OPTIMAL`, `FEASIBLE`, `INFEASIBLE`, `ERROR` | `solver_runs.status` |  |
| `SolverRunTriggerType` | 4: `NEW_REQUEST`, `REPLAN`, `RESOURCE_CHANGE`, `MANUAL` | `solver_runs.trigger_type` |  |

**Plan** (3 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `OperationalPlanStatus` | 3: `CONFIRMED`, `CANCELLED`, `COMPLETED` | `operational_plans.status` |  |
| `PlanComponentDerivedStatus` | 4: `UNASSIGNED`, `ASSIGNED`, `STARTED`, `DONE` | not a database column (`plan_components.derived_status`: not a column. The value is read from the assignment and the latest) |  |
| `PlanComponentReservationState` | 3: `RESERVED`, `RELEASED`, `CONSUMED` | `plan_components.reservation_state` |  |

**Yard** (9 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `StorageMovementDirection` | 2: `IN`, `OUT` | `storage_movements.direction` |  |
| `StorageMovementSource` | 3: `VESSEL_DISCHARGE`, `VESSEL_LOADING`, `MANUAL` | `storage_movements.source` |  |
| `TerminalRecommendationDecision` | 4: `PENDING`, `ACCEPTED`, `REJECTED`, `ADJUSTED` | `terminal_recommendations.decision` |  |
| `TerminalRecommendationLifecycleStatus` | 6: `YARD_REC_REQUESTED`, `YARD_REC_GENERATED`, `GENERATION_FAILED`, `YARD_REC_ACCEPTED`, `YARD_REC_REJECTED`, `EXPORTED_TO_TOS` | `terminal_recommendations.lifecycle_status` |  |
| `TerminalRecommendationType` | 3: `TIMING`, `YARD_PLACEMENT`, `STORAGE_ALLOCATION` | `storage_allocation_items.rec_type`, `terminal_recommendations.rec_type`, `yard_placement_items.rec_type` |  |
| `YardRunErrorCategory` | 7: `INVALID_ENVELOPE`, `PRECONDITION_FAILED`, `DATA_UNAVAILABLE`, `CONFIGURATION_ERROR`, `INFEASIBLE`, `TIMEOUT`, `INTERNAL_ERROR` | `yard_runs.error_category` |  |
| `YardRunOutcome` | 2: `YARD_REC_GENERATED`, `GENERATION_FAILED` | `yard_runs.outcome` |  |
| `YardSlotStatus` | 2: `ACTIVE`, `BLOCKED` | `yard_slots.status` |  |
| `ZoneDetail` | 4: `NONE`, `REEFER`, `DANGEROUS_GOODS`, `OOG` | `dg_zone_allowed_classes.zone_detail`, `oog_zone_details.zone_detail`, `reefer_zone_details.zone_detail`, `yard_zones.zone_detail`, `yard_zone_types.zone_detail` |  |

**Dispatch** (4 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `NotificationLogChannel` | 3: `WEB`, `MOBILE`, `PUSH` | `notification_logs.channel` |  |
| `NotificationLogRelatedEntityType` | 7: `PLAN_COMPONENT`, `BERTHING_REQUEST`, `RECOMMENDATION`, `ENTRY_WINDOW_PROPOSAL`, `TUGBOAT`, `RETRAINING_RUN`, `LICENSE` | `notification_logs.related_entity_type` |  |
| `NotificationLogStatus` | 3: `SENT`, `FAILED`, `ACKNOWLEDGED` | `notification_logs.status` |  |
| `NotificationLogType` | 12: `PLAN_ASSIGNMENT`, `PRE_EXECUTION_REMINDER`, `TUG_STILL_BUSY`, `ENTRY_WINDOW_READY`, `RECOMMENDATION_READY`, `PLAN_CANCELLED`, `RETRAINING_REVIEW_NEEDED`, `LICENSE_EXPIRY_WARNING`, `ACK_ESCALATION`, `YARD_RECOMMENDATION_READY`, `YARD_RECOMMENDATION_FAILED`, `DRIFT_ALERT` | `notification_logs.type` |  |

**Execution** (2 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `ActualStatusUpdateSyncSource` | 4: `MOBILE`, `TABLET`, `WEB`, `EXTERNAL` | `actual_status_updates.sync_source` |  |
| `DeviationType` | 3: `DELAY`, `RESOURCE_CHANGED`, `STATE_MISMATCH` | `deviation_logs.deviation_type` |  |

**Learning** (4 enums)

| Enum | Backed values (cases) | Source column(s) | Helpers |
| --- | --- | --- | --- |
| `DriftMetricType` | 2: `PSI`, `ROLLING_MAE` | `drift_measurements.metric_type` |  |
| `EvaluationMetricDatasetType` | 3: `HOLDOUT`, `ROLLING`, `PRODUCTION` | `evaluation_metrics.dataset_type` |  |
| `EvaluationMetricName` | 4: `MAE`, `OVERRIDE_RATE`, `WAIT_TIME`, `WINDOW_ACCURACY` | `evaluation_metrics.metric_name` |  |
| `RetrainingRunDecision` | 3: `PROMOTED`, `KEPT_CHAMPION`, `FAILED` | `retraining_runs.decision` |  |

### 3.11 The catalogue of all 92 factories

**Parents created by default** lists the `Model::factory()` calls inside `definition()` (other foreign keys are hard-coded ids or null). **States** are the named states (5 in total, all on Access). Factories listed with `none` and a required parent use fixed ids or nullable keys; see section 11 for the 33 that fail with foreign keys on.

**Shared** (1 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `AuditLogFactory` | `AuditLog` | none |  |

**Access** (4 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `OrganizationUnitFactory` | `OrganizationUnit` | Terminal | `harbourAuthority()`, `marineServices()` |
| `UserDeviceFactory` | `UserDevice` | User | `revoked()` |
| `UserFactory` | `User` | none | `inactive()` |
| `UserRoleAssignmentFactory` | `UserRoleAssignment` | OrganizationUnit, User | `ended()` |

**License** (1 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `LicenseStateFactory` | `LicenseState` | none |  |

**MasterData** (38 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `BerthFactory` | `Berth` | none |  |
| `BerthTerminalFactory` | `BerthTerminal` | Berth, Terminal |  |
| `CapabilityTypeFactory` | `CapabilityType` | none |  |
| `CargoTypeFactory` | `CargoType` | none |  |
| `ChannelWaypointFactory` | `ChannelWaypoint` | NavigationChannel |  |
| `ConstraintTypeFactory` | `ConstraintType` | none |  |
| `ContainerFactory` | `Container` | ContainerSizeType |  |
| `ContainerSizeTypeFactory` | `ContainerSizeType` | none |  |
| `EquipmentCapabilityFactory` | `EquipmentCapability` | CapabilityType, Equipment |  |
| `EquipmentCargoCompatibilityFactory` | `EquipmentCargoCompatibility` | none |  |
| `EquipmentCategoryFactory` | `EquipmentCategory` | none |  |
| `EquipmentFactory` | `Equipment` | EquipmentCategory, Terminal |  |
| `ExternalSystemFactory` | `ExternalSystem` | none |  |
| `ImdgClassFactory` | `ImdgClass` | none |  |
| `MlModelFactory` | `MlModel` | none |  |
| `MlModelVersionFactory` | `MlModelVersion` | MlModel |  |
| `NavigationChannelFactory` | `NavigationChannel` | none |  |
| `PilotProfileFactory` | `PilotProfile` | User |  |
| `PilotRankLimitFactory` | `PilotRankLimit` | none |  |
| `PilotScheduleFactory` | `PilotSchedule` | PilotProfile, PortShift, User |  |
| `PortProfileFactory` | `PortProfile` | none |  |
| `PortSettingFactory` | `PortSetting` | none |  |
| `PortShiftFactory` | `PortShift` | none |  |
| `SafetyConstraintFactory` | `SafetyConstraint` | ConstraintType, User |  |
| `StatusTypeFactory` | `StatusType` | none |  |
| `StorageFacilityCargoCompatibilityFactory` | `StorageFacilityCargoCompatibility` | none |  |
| `StorageFacilityFactory` | `StorageFacility` | none |  |
| `StorageTypeFactory` | `StorageType` | none |  |
| `TankDetailFactory` | `TankDetail` | none |  |
| `TerminalCargoTypeFactory` | `TerminalCargoType` | CargoType, Terminal |  |
| `TerminalFactory` | `Terminal` | none |  |
| `TerminalProductivityProfileFactory` | `TerminalProductivityProfile` | none |  |
| `TugMasterProfileFactory` | `TugMasterProfile` | User |  |
| `TugMasterScheduleFactory` | `TugMasterSchedule` | PortShift, TugMasterProfile, User |  |
| `TugboatFactory` | `Tugboat` | none |  |
| `VesselFactory` | `Vessel` | VesselType |  |
| `VesselTypeFactory` | `VesselType` | none |  |
| `WarehouseDetailFactory` | `WarehouseDetail` | none |  |

**Environment** (6 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `EnvironmentForecastPointFactory` | `EnvironmentForecastPoint` | ForecastRun |  |
| `ForecastRunFactory` | `ForecastRun` | none |  |
| `TideConstituentFactory` | `TideConstituent` | TideConstituentSet |  |
| `TideConstituentSetFactory` | `TideConstituentSet` | none |  |
| `TidePredictionPointFactory` | `TidePredictionPoint` | TidePredictionRun |  |
| `TidePredictionRunFactory` | `TidePredictionRun` | TideConstituentSet |  |

**Berthing** (3 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `BerthingRequestCargoFactory` | `BerthingRequestCargo` | none |  |
| `BerthingRequestFactory` | `BerthingRequest` | Terminal, User, Vessel |  |
| `BerthingRequestStatusHistoryFactory` | `BerthingRequestStatusHistory` | BerthingRequest |  |

**Baplie** (4 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `BaplieFileFactory` | `BaplieFile` | User, Vessel |  |
| `BaplieParseIssueFactory` | `BaplieParseIssue` | BaplieFile |  |
| `BaplieParseRunFactory` | `BaplieParseRun` | BaplieFile |  |
| `ContainerManifestEntryFactory` | `ContainerManifestEntry` | BaplieFile, Container |  |

**Recommendation** (15 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `BlockedPeriodFactory` | `BlockedPeriod` | ConstraintType, EntryWindowProposal |  |
| `DemandForecastSlotFactory` | `DemandForecastSlot` | none |  |
| `EntryWindowProposalFactory` | `EntryWindowProposal` | BerthingRequest, ForecastRun, TidePredictionRun |  |
| `FeasibleWindowFactory` | `FeasibleWindow` | EntryWindowProposal |  |
| `ObjectiveTermFactory` | `ObjectiveTerm` | none |  |
| `Part1RunFactory` | `Part1Run` | BerthingRequest |  |
| `RationaleTemplateFactory` | `RationaleTemplate` | none |  |
| `RecommendationFactory` | `Recommendation` | SolverRun |  |
| `RecommendationOptionFactory` | `RecommendationOption` | none |  |
| `RecommendationOptionTugFactory` | `RecommendationOptionTug` | RecommendationOption, Tugboat |  |
| `RecommendationOverrideFactory` | `RecommendationOverride` | Berth, RecommendationOption, User |  |
| `RecommendationRationaleFactorFactory` | `RecommendationRationaleFactor` | ConstraintType, RecommendationOption |  |
| `RecommendationReviewFactory` | `RecommendationReview` | Recommendation, User |  |
| `SolverRunFactory` | `SolverRun` | none |  |
| `SolverRunModelVersionFactory` | `SolverRunModelVersion` | MlModelVersion, SolverRun |  |

**Plan** (2 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `OperationalPlanFactory` | `OperationalPlan` | User |  |
| `PlanComponentFactory` | `PlanComponent` | Berth, OperationalPlan |  |

**Yard** (12 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `DgZoneAllowedClassFactory` | `DgZoneAllowedClass` | ImdgClass |  |
| `OogZoneDetailFactory` | `OogZoneDetail` | none |  |
| `ReeferZoneDetailFactory` | `ReeferZoneDetail` | none |  |
| `StorageAllocationItemFactory` | `StorageAllocationItem` | none |  |
| `StorageMovementFactory` | `StorageMovement` | none |  |
| `TerminalRecommendationFactory` | `TerminalRecommendation` | none |  |
| `YardBlockFactory` | `YardBlock` | YardZone |  |
| `YardPlacementItemFactory` | `YardPlacementItem` | ContainerManifestEntry, YardBlock |  |
| `YardRunFactory` | `YardRun` | TerminalRecommendation |  |
| `YardSlotFactory` | `YardSlot` | YardBlock |  |
| `YardZoneFactory` | `YardZone` | none |  |
| `YardZoneTypeFactory` | `YardZoneType` | none |  |

**Dispatch** (1 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `NotificationLogFactory` | `NotificationLog` | User |  |

**Execution** (2 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `ActualStatusUpdateFactory` | `ActualStatusUpdate` | User |  |
| `DeviationLogFactory` | `DeviationLog` | PlanComponent |  |

**Learning** (3 factories)

| Factory | Model | Parents created by default | States |
| --- | --- | --- | --- |
| `DriftMeasurementFactory` | `DriftMeasurement` | MlModelVersion |  |
| `EvaluationMetricFactory` | `EvaluationMetric` | none |  |
| `RetrainingRunFactory` | `RetrainingRun` | MlModel |  |

## 4. Why this design

| Choice | Reason | Source |
| --- | --- | --- |
| One model per table, in the owning module | The module layer rule lets later code use earlier modules only; a table-to-module map was approved before coding | P1-B5 answer 1; `ModuleArchitectureTest` |
| Relations only in the allowed direction | Eloquent habit is to add inverse relations; they would break the layer rule | P1-B5 answer 2 |
| One enum per ENUM value set (77), TitleCase case, SQL value | Same list in database and code; status vocabulary can be generated; adding a value means a migration and an enum case together | P1-B5 answer 3; CT-17 §2; CT-21 |
| Pivot models with `belongsToMany()->using()`; single non-`id` keys stay plain models | Composite keys fit pivots; one-key tables are normal models with `key:` | P1-B5 answer 4 |
| `User` rebuilt for DEC-35 | Laravel's default model assumed `email` and `password` | P1-B5 answer 5 |
| AI-written and generated columns not fillable | A mass-assignment bug must not overwrite a worker's result, or try to write a column MySQL refuses; the Backend writes the few it may with `forceFill()` | P1-B5 answer 6; CT-15 |
| One factory per model now | Tests and seeders need valid rows; P1-B6 builds on them | P1-B5 answer 7 |
| The CT-21 generator is a separate task | P1-B5 delivers enums and the guard; `PublishStatusVocabulary` does the file | P1-B5 answer 8 |
| Attributes (`#[Table]`, `#[Fillable]`, `#[UseFactory]`…) instead of properties | Laravel 13 style; fewer properties, one place to read | `CLAUDE.md` (Boost); P1-B5 log |
| Models carry no rules | Rules live in MySQL (F05) and in services (F07 onward); a model is data plus relations | `CLAUDE.md` |
| Decimals as strings, dates as UTC Carbon | No float rounding for money and sizes; one time zone | P1-B5 section 3; CT-01 |

## 5. How it connects

- **Depends on** F05 (the tables) and F01 (module providers; the factories use `database/factories`).
- **Used by** everything that touches data: F07 (`User`, `UserDevice`, `OrganizationUnit`, `UserRoleAssignment`), F08 (`User`), F09 (`Role`, `UnitType`, `RestrictsToOwnTasks`, `PlanComponent`), F10 (`AuditLog`), F12 (`updated_at` as token), F13 (factories and seeders), F14 (`PublishStatusVocabulary` reads the enums).
- **Module layer rules.** Models import only their own module and earlier ones (`ModuleArchitectureTest`). Factories live outside `app/` and may reference any model; that is allowed and untested (P1-B5 deviation 3).
- **Other tests that use the models:** `RolePermissionsTest` (`tests/Unit/Modules/Access/RolePermissionsTest.php`) uses `Role`; see F09.

## 6. Data

The models read and write the 92 tables of F05. Ownership (CT-15): the Backend owns every table; the AI workers' insert and update rights are in `workers.sql` and reflected in the **Special columns** of the model catalogue. Models never touch the framework tables (`sessions`, `jobs`, …).

## 7. Tests

`tests/Feature/Models/ModelLayerTest.php` (group `schema`, MySQL, FK checks off and a rolled-back transaction per test, `DB::disconnect()` after each so the suite does not hit MySQL's `max_connections`):

| Test (line) | What it proves | Cases |
| --- | --- | --- |
| every model points at its own table and no two share one (`:95`) | Every `getTable()` exists; no duplicates | 1 |
| every contract table has a model (`:105`) | The 92 names of `table-names.md` are all modelled; exactly 92 models | 1 |
| a row is written and read back through its model (`:118`) | `Model::factory()->create()` works, then the row is read by the table's PRIMARY columns; every `getFillable()` name is a real column; every enum cast returns the enum | 92 |
| every enum cast holds exactly the values of its database ENUM (`:140`) | The set of enum-cast columns equals the set of ENUM columns of the table, and each enum's values equal the column's ENUM list, **in the same order** | 92 |
| every declared relation loads (`:159`) | Each public method with a `Relation` return type returns a relation and `getResults()` does not throw | models with relations |
| a belongsTo returns its own parent, not a neighbour (`:171`) | A decoy parent proves 8 composite-key-affected relations pick the right row | 8 |
| a status update finds its status type within its component type (`:188`), eager loading is refused (`:198`) | The special relation of 3.6 | 2 |
| a belongsToMany through a pivot returns exactly the linked rows (`:204`) | The 8 many-to-many relations | 8 |
| tables only an AI worker inserts give the Backend nothing to fill (`:224`) | 19 AI-insert rows in `table-ownership.md`, 13 "Nothing" → `Fillable([])` | 1 |
| columns a worker updates are not fillable; generated columns never are (`:238`) | Reads `workers.sql` and `information_schema` | 1 |
| a user signs in with username and a hashed `password_hash`, never shown (`:257`) | DEC-35 | 1 |
| the version token `updated_at` is written and read with microseconds (`:265`) | `#[DateFormat]` on `User` | 1 |

Measured 2026-11-04 on `atlantech_testing_g2`: `ModelLayerTest` together with the three F05 files passed (377 tests, 1156 assertions, 59.7 s). Run:

```powershell
$env:DB_DATABASE = "atlantech_testing_mine"; $env:DB_SCHEMA_TEST_DATABASE = "atlantech_testing_mine"
vendor/bin/pest tests/Feature/Models/ModelLayerTest.php
```

**How to add a model so the guards pass.**

1. The table already exists (F05) and is in `docs/reference/contracts/db/table-names.md`.
2. Create `app/Modules/<Module>/Models/<Singular>.php` extending `Model` (or `Pivot` for a composite-key link table) with `#[Table(name: …)]`, `#[Fillable([...])]`, `#[UseFactory(...)]`, `use HasFactory`; choose `timestamps: false` / `CREATED_AT = null` from the table's columns; set `key:`/`incrementing:`/`keyType:` if the key is not `id`.
3. Cast **every** ENUM column to an enum whose cases follow the column's list in order, and cast no enum to a non-ENUM column. Create the enum in `Enums/` (the module that first needs it; `Shared/Enums` if two modules do).
4. Keep generated columns and AI-updated columns out of `#[Fillable]`; if the table is insert-only for a worker and the Backend may change nothing, use `Fillable([])`.
5. Write `belongsTo` for foreign keys whose target is in the same or an earlier module, with a typed return and generic docblock; no relation to later modules or peers; no relation that matches only part of a composite key.
6. Create `database/factories/<Module>/<Singular>Factory.php` whose `definition()` returns a valid row (CHECKs satisfied; unique values from Faker).
7. Update the two **92** constants in `ModelLayerTest.php:113,115` (and the AI-insert counts `:228-229` if the table is a worker's).
8. Run `ModelLayerTest` and `tests/Unit/Modules/ModuleArchitectureTest.php`.

## 8. Gotchas and invariants

- **Case order is part of the contract.** Re-ordering an enum's cases or the ENUM list fails the guard, even if the sets are equal.
- **Never list a generated column or an AI-updated column in `#[Fillable]`.** The guard reads `workers.sql`; adding a grant makes an existing `Fillable` entry fail until you remove it.
- **`Fillable([])` is intentional** on 14 models. To create such a row in a test use the factory; to create it in a service use `forceFill()` or a query-builder insert.
- **`updated_at` precision.** Without `#[DateFormat('Y-m-d H:i:s.u')]` the token loses microseconds. `created_at` is a second-precision `datetime`; Eloquent writes microseconds into it and MySQL rounds (harmless, noted in the P1-B5 review).
- **Relations never inverse across modules.** If you need the inverse, query from the later module.
- **`ActualStatusUpdate::statusType()` cannot be eager-loaded, nor used in `has()`.**
- **`AuditLog` is append-only** (updates, deletes, increments throw `LogicException`; F10). Do not "fix" it.
- **Factories are not FK-valid for 33 models** (section 11): a test that needs a related row must create the parents with explicit ids or states.
- **`DB::disconnect()` after each test** prevents MySQL `max_connections` exhaustion; keep it in new model-layer tests.
- **Do not put business rules in models.** Keep services in the module (F07 onward).
- **`php artisan model:show` needs the full class name** (`'App\Modules\Access\Models\User'`).

## 9. How to extend it

- **A new column on an existing table.** Migration (F05 9), then add it to `#[Fillable]` (unless generated/AI-updated), `casts()` (date, decimal scale, boolean, JSON, enum) and the factory's `definition()`.
- **A new ENUM value.** Migration with `ALTER TABLE … MODIFY` (F05 9), then the case in the enum **at the same position**; run `ModelLayerTest`; regenerate the vocabulary (F14).
- **A new state in a factory.** `public function name(): static { return $this->state(fn (array $attributes) => [...]); }` (see `UserFactory::inactive`, `:40`). Prefer a state over passing raw arrays in tests.
- **A new many-to-many.** Create the pivot model (composite key, `incrementing: false`, `extends Pivot`), then `belongsToMany(Target::class, 'pivot_table', 'owner_key', 'target_key')->using(Pivot::class)` on both sides if the module rule allows, and add a row to the dataset at `ModelLayerTest.php:213`.
- **A new relation.** Typed method with the generic docblock; check the target's module layer first; add a decoy test like `:171` when the key is not the obvious one.
- **A new module with models.** Follow F01 9 and add the module to `ModuleArchitectureTest`.

## 10. Try it

Real output, from `project-backend/ATLANTECH`, database `atlantech_testing_g2` (never the `atlantech` database):

```powershell
$env:DB_DATABASE = "atlantech_testing_g2"
php artisan model:show 'App\Modules\Access\Models\UserRoleAssignment'
```
Real output: `Table .. user_role_assignments`; `id increments, unique .. bigint unsigned / int`; `role fillable enum('PORT_GM',…,'TERMINAL_SUPERVISOR') / App\Modules\Access\Enums\Role`; `unit_type … / App\Modules\Access\Enums\UnitType`; `active_assignment_key nullable .. tinyint` (no `fillable`); relations `user BelongsTo … User` and `organizationUnit BelongsTo … OrganizationUnit`.

```powershell
$env:DB_DATABASE = "atlantech_testing_g2"; $env:DB_SCHEMA_TEST_DATABASE = "atlantech_testing_g2"
vendor/bin/pest tests/Feature/Models/ModelLayerTest.php
```
Real output: passed (part of the 377-test run in F05, 59.7 s).

```bash
ls app/Modules/*/Models/*.php | wc -l ; ls app/Modules/*/Enums/*.php | wc -l ; ls database/factories/*/*.php | wc -l
```
Real output: `93`, `80`, `92` (93 files under `Models/` = 92 models + `AuditLogBuilder`).

Expected, **not run**: `php artisan tinker --execute 'App\Modules\Access\Models\User::factory()->make()->username;'` prints a username like `user.k3x9a0pqzv` (no database write, `make` only).

## 11. Open points

Findings (documentation only; nothing was changed):

1. **33 factories are not FK-valid on an empty database** (measured: 59 of 92 succeed with FK checks on): `BaplieFile`, `BaplieParseIssue`, `BaplieParseRun`, `ContainerManifestEntry`, `BerthingRequestCargo`, `ActualStatusUpdate`, `DeviationLog`, `RetrainingRun`, `EquipmentCargoCompatibility`, `StorageFacility`, `StorageFacilityCargoCompatibility`, `TankDetail`, `TerminalProductivityProfile`, `WarehouseDetail`, `OperationalPlan`, `PlanComponent`, `Recommendation`, `RecommendationOption`, `RecommendationOptionTug`, `RecommendationOverride`, `RecommendationRationaleFactor`, `RecommendationReview`, `DgZoneAllowedClass`, `OogZoneDetail`, `ReeferZoneDetail`, `StorageAllocationItem`, `StorageMovement`, `TerminalRecommendation`, `YardBlock`, `YardPlacementItem`, `YardRun`, `YardSlot`, `YardZone`. P1-B5 recorded 57 and 35 on 2026-10-29; the difference is not explained in the plan files. Tests that need related rows must build the chain by hand; coordinated states are not written yet.
2. **`TerminalRecommendation` docblock contains a broken character** (`Visible to that terminal only (�42)`); a UTF-8 encoding slip from the SQL source.
3. **46 of the 92 models have only the placeholder docblock "The `x` table."**; their purpose is written only in F05 3.3 of this guide and in the table comments of the migrations.
4. **Counts match the index** (92 models, 80 enums, 92 factories; 77 enums back ENUM columns, 3 do not). One detail to know when counting files: `app/Modules/*/Models/*.php` holds **93** files, because `Shared/Models/AuditLogBuilder.php` is the query builder of `AuditLog`, not a model. `Access/Models/Concerns/RestrictsToOwnTasks.php` is in a subfolder and is not counted.
5. **Relations that exist only on one side** (`forecast_runs.berthing_request_id`, `organization_units.terminal_id`, `audit_logs.actor_id`) are consequences of the layer rule (3.6); no change needed, but a developer will look for them.
6. **`ModelLayerTest` pins the totals 92, 19 and 13** (`:113,115,228-229`); every new table needs those edits.
7. **`PilotRankLimit` keyType** is `string` while `PilotProfile`/`TugMasterProfile` use `int`; both correct for their columns, noted because the naming looks inconsistent.
8. The DEC-aware helper `Role::isFieldRole()` includes `TerminalSupervisor`; F09 explains the scope consequences.
9. **Not built:** observers, policies, API resources and the status-vocabulary generator beyond `PublishStatusVocabulary` (F14); the factory states needed by P1-B6/P1-B7 and by the demo seeders are in F13.
