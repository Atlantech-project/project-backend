# ATLANTECH Class Diagrams: Index

## 1. Purpose

This index ties together the six class-diagram views of ATLANTECH (Intelligent Port Operations). Together they model all **98 tables**: 8 in the central SaaS database and 90 in the Port database (`license_state` is a Port table drawn in View 1). The index also records the consistency check across views and the status of every open issue.

**Patched on 19 October 2026** with the team's final decisions. The companion files are now:

- `Report_A_Accepted_SQL_Changes.md`: the SQL changes the team accepted.
- `Report_B_Tier3_Naming_Changes.md`: the final status names.
- `Coding_Notes_and_Mapping.md`: the SQL ↔ FRD/UML name mapping, derived values and coding rules.
- `ATLANTECH_DB_Edit_Sheet.md`: the exact SQL and DBML edits.

The earlier `ATLANTECH_SQL_Change_List.md` and `ATLANTECH_Vocabulary_Mapping.md` are superseded. The change list for this patch is `ATLANTECH_ClassDiagram_Patch_Log.md`.

## 2. Views

| View | Scope | Tables (full classes) | FK relationships | Markdown | Rendered diagram |
|---|---|---|---|---|---|
| 1 | Central SaaS, licensing and billing (plus `license_state` in a Port DB package) | 9 | 8 | [ATLANTECH_ClassDiagram_1_SaaS.md](ATLANTECH_ClassDiagram_1_SaaS.md) | [HTML](ATLANTECH_ClassDiagram_1_SaaS.html) |
| 2A | Access, organisation and marine resources | 21 | 26 | [ATLANTECH_ClassDiagram_2A_Access_Marine.md](ATLANTECH_ClassDiagram_2A_Access_Marine.md) | [HTML](ATLANTECH_ClassDiagram_2A_Access_Marine.html) |
| 2B | Terminal reference, storage, yard and equipment | 24 | 30 | [ATLANTECH_ClassDiagram_2B_Terminal_Yard.md](ATLANTECH_ClassDiagram_2B_Terminal_Yard.md) | [HTML](ATLANTECH_ClassDiagram_2B_Terminal_Yard.html) |
| 2C | Berthing request, BAPLIE and Part 1 | 20 | 36 | [ATLANTECH_ClassDiagram_2C_Request_BAPLIE_Part1.md](ATLANTECH_ClassDiagram_2C_Request_BAPLIE_Part1.md) | [HTML](ATLANTECH_ClassDiagram_2C_Request_BAPLIE_Part1.html) |
| 2D | Part 2: recommendation, plan, dispatch and yard recommendation | 14 + 2 join tables | 45 (43 lines + 2 join associations covering 4 FKs) | [ATLANTECH_ClassDiagram_2D_Part2_Plan_Dispatch.md](ATLANTECH_ClassDiagram_2D_Part2_Plan_Dispatch.md) | [HTML](ATLANTECH_ClassDiagram_2D_Part2_Plan_Dispatch.html) |
| 3 | Execution feedback and model learning (plus the `<<mobile-local>>` Outbox) | 8 | 13 | [ATLANTECH_ClassDiagram_3_Execution_Learning.md](ATLANTECH_ClassDiagram_3_Execution_Learning.md) | [HTML](ATLANTECH_ClassDiagram_3_Execution_Learning.html) |
| **Total** | | **96 classes + 2 join tables = 98 tables** | **160 (SaaS 8, Port 152)** | | |

## 3. Consistency check results

| # | Check | Result |
|---|---|---|
| 1 | Coverage: every table exactly once as a full class | Pass. 98 of 98 tables. 96 are full classes; `recommendation_option_tug` and `solver_run_model_version` are pure join tables drawn as many-to-many associations in View 2D. No table appears as a full class in two views |
| 2 | Ghosts (`<<ref>>`): name and PK match the owning view | Pass. Every ghost uses the owner's class name and PK. View 1 PortProfile also shows `public_id`, which the logical link needs (allowed) |
| 3 | Enumerations identical across views | Pass. 81 enumerations. Shared: TerminalType (2A, 2B, 2D), CapacityUnit (2B, 2D), ComponentType (2D, 3), all with identical values |
| 4 | One relationship per FK | Pass. Port total 152 FKs = the PortSync.md count (143) + 9 from the accepted changes; SaaS 8. Composite FKs drawn once with a note |
| 5 | Terminology | Pass. ATLANTECH, CT, NCT and Port Admin = PORT_GM used throughout; no PortMind or PortSync AI names; no logo |

### 3.1 Defects found (diagram files not changed)

| ID | File | Line | Defect | One-line fix |
|---|---|---|---|---|
| F-01 | `ATLANTECH_ClassDiagram_2D_Part2_Plan_Dispatch.md` (same line in the .html) | 481 | `Recommendation "1" *-- "0..*" TerminalRecommendation` ignores the unique index on (recommendation_id, rec_type), so at most one per type | **Fixed** in this patch: "0..3" |
| F-02 | `ATLANTECH_ClassDiagram_2C_Request_BAPLIE_Part1.md` | 489 | The DataSource row cites Master Context 7.4 for REGISTRY, but Master Context names only EGMSW_FEED | **Fixed** in this patch |
| F-03 | `PortSync.md` (source, not a diagram) | DDL export | CHECK constraints and GENERATED helper columns appear only as COMMENTs, so they are not enforced as written | **Decided**: `PortSync_base_ddl_fixes.sql` makes them real |
| F-04 | `ATLANTECH_ClassDiagrams_Index.md` | 7 | Said 9 SaaS + 86 Port tables | **Fixed**: 8 SaaS + 90 Port = 98 |

Accepted as drawn (each already an Open Issue): View 2C status history "1..*" from the creation rule (OI-49); View 2A BerthTerminal "1..*" per berth from a backend rule (OI-32).

## 4. How to read the diagrams

- Each table is one class with the `<<entity>>` stereotype, named in PascalCase singular (`berthing_request` = BerthingRequest).
- Attribute lines are `+type name` followed by tags: PK, FK, UQ, GEN (generated). Lengths and precision are in each file's inventory table, not in the diagram.
- Helper columns that exist only to enforce uniqueness (`active_key` and similar) are not drawn; a note states the rule, for example "at most one ACTIVE per port".
- Columns typed with an enumeration show the **UML target values**. Where the SQL differs, both companion files say how.
- Operations are key behaviour only, each traced to an activity diagram (AD), state machine (SM), sequence step or FR ID in section 5 of each file. Operations marked FW are future work.
- Solid line = FK inside the view; `*--` = composition (child lives and dies with the parent). A dashed arrow to a `<<ref>>` ghost = FK to a class drawn in another view. A dashed line labelled "logical, no FK" = a rule or link with no FK.
- `<<mobile-local>>` (Outbox) lives on the phone only and is not a database table.
- Multiplicity comes from nullability and unique indexes: a nullable FK gives 0..1 on the parent side.
- Open the .html file for a zoomable view with a "Download SVG" button.

## 5. Decisions taken

The team decided on 19 October 2026. The diagrams use the **final SQL names**: the UML names where the SQL was renamed (request, BAPLIE file, proposal and plan status), and the SQL names where they were kept (BAPLIE parse status, recommendation status, `client_event_id`, `reported_at`). `Report_A_Accepted_SQL_Changes.md` lists the accepted SQL changes, `Report_B_Tier3_Naming_Changes.md` the naming decisions, and `Coding_Notes_and_Mapping.md` the name mapping, derived values and coding rules. Nothing has been run on a database yet.

## 6. Coverage table (98 tables)

| # | Table | Database | Class | View |
|---|---|---|---|---|
| 1 | `billing_transaction` | SaaS | BillingTransaction | 1 |
| 2 | `governorate` | SaaS | Governorate | 1 |
| 3 | `license` | SaaS | License | 1 |
| 4 | `license_plan` | SaaS | LicensePlan | 1 |
| 5 | `license_state` | Port | LicenseState | 1 |
| 6 | `license_token` | SaaS | LicenseToken | 1 |
| 7 | `platform_audit_log` | SaaS | PlatformAuditLog | 1 |
| 8 | `platform_port_registry` | SaaS | PlatformPortRegistry | 1 |
| 9 | `saas_admin` | SaaS | SaasAdmin | 1 |
| 10 | `audit_log` | Port | AuditLog | 2A |
| 11 | `berth` | Port | Berth | 2A |
| 12 | `berth_terminal` | Port | BerthTerminal | 2A |
| 13 | `channel_waypoint` | Port | ChannelWaypoint | 2A |
| 14 | `constraint_type` | Port | ConstraintType | 2A |
| 15 | `navigation_channel` | Port | NavigationChannel | 2A |
| 16 | `organization_unit` | Port | OrganizationUnit | 2A |
| 17 | `pilot_profile` | Port | PilotProfile | 2A |
| 18 | `pilot_rank_limit` | Port | PilotRankLimit | 2A |
| 19 | `pilot_schedule` | Port | PilotSchedule | 2A |
| 20 | `port_profile` | Port | PortProfile | 2A |
| 21 | `port_setting` | Port | PortSetting | 2A |
| 22 | `port_shift` | Port | PortShift | 2A |
| 23 | `safety_constraint` | Port | SafetyConstraint | 2A |
| 24 | `terminal` | Port | Terminal | 2A |
| 25 | `tug_master_profile` | Port | TugMasterProfile | 2A |
| 26 | `tug_master_schedule` | Port | TugMasterSchedule | 2A |
| 27 | `tugboat` | Port | Tugboat | 2A |
| 28 | `user` | Port | User | 2A |
| 29 | `user_role_assignment` | Port | UserRoleAssignment | 2A |
| 30 | `capability_type` | Port | CapabilityType | 2B |
| 31 | `cargo_type` | Port | CargoType | 2B |
| 32 | `container` | Port | Container | 2B |
| 33 | `container_size_type` | Port | ContainerSizeType | 2B |
| 34 | `dg_zone_allowed_class` | Port | DgZoneAllowedClass | 2B |
| 35 | `equipment` | Port | Equipment | 2B |
| 36 | `equipment_capability` | Port | EquipmentCapability | 2B |
| 37 | `equipment_cargo_compatibility` | Port | EquipmentCargoCompatibility | 2B |
| 38 | `equipment_category` | Port | EquipmentCategory | 2B |
| 39 | `imdg_class` | Port | ImdgClass | 2B |
| 40 | `oog_zone_detail` | Port | OogZoneDetail | 2B |
| 41 | `reefer_zone_detail` | Port | ReeferZoneDetail | 2B |
| 42 | `storage_facility` | Port | StorageFacility | 2B |
| 43 | `storage_facility_cargo_compatibility` | Port | StorageFacilityCargoCompatibility | 2B |
| 44 | `storage_movement` | Port | StorageMovement | 2B |
| 45 | `storage_type` | Port | StorageType | 2B |
| 46 | `tank_detail` | Port | TankDetail | 2B |
| 47 | `terminal_cargo_type` | Port | TerminalCargoType | 2B |
| 48 | `terminal_productivity_profile` | Port | TerminalProductivityProfile | 2B |
| 49 | `warehouse_detail` | Port | WarehouseDetail | 2B |
| 50 | `yard_block` | Port | YardBlock | 2B |
| 51 | `yard_slot` | Port | YardSlot | 2B |
| 52 | `yard_zone` | Port | YardZone | 2B |
| 53 | `yard_zone_type` | Port | YardZoneType | 2B |
| 54 | `baplie_file` | Port | BaplieFile | 2C |
| 55 | `baplie_parse_issue` | Port | BaplieParseIssue | 2C |
| 56 | `berthing_request` | Port | BerthingRequest | 2C |
| 57 | `berthing_request_cargo` | Port | BerthingRequestCargo | 2C |
| 58 | `berthing_request_status_history` | Port | BerthingRequestStatusHistory | 2C |
| 59 | `blocked_period` | Port | BlockedPeriod | 2C |
| 60 | `container_manifest_entry` | Port | ContainerManifestEntry | 2C |
| 61 | `demand_forecast_slot` | Port | DemandForecastSlot | 2C |
| 62 | `entry_window_proposal` | Port | EntryWindowProposal | 2C |
| 63 | `environment_forecast_point` | Port | EnvironmentForecastPoint | 2C |
| 64 | `external_system` | Port | ExternalSystem | 2C |
| 65 | `feasible_window` | Port | FeasibleWindow | 2C |
| 66 | `forecast_run` | Port | ForecastRun | 2C |
| 67 | `tide_constituent` | Port | TideConstituent | 2C |
| 68 | `tide_constituent_set` | Port | TideConstituentSet | 2C |
| 69 | `tide_prediction_point` | Port | TidePredictionPoint | 2C |
| 70 | `tide_prediction_run` | Port | TidePredictionRun | 2C |
| 71 | `vessel` | Port | Vessel | 2C |
| 72 | `vessel_type` | Port | VesselType | 2C |
| 73 | `notification_log` | Port | NotificationLog | 2D |
| 74 | `objective_term` | Port | ObjectiveTerm | 2D |
| 75 | `operational_plan` | Port | OperationalPlan | 2D |
| 76 | `plan_component` | Port | PlanComponent | 2D |
| 77 | `rationale_template` | Port | RationaleTemplate | 2D |
| 78 | `recommendation` | Port | Recommendation | 2D |
| 79 | `recommendation_option` | Port | RecommendationOption | 2D |
| 80 | `recommendation_option_tug` | Port | many-to-many association (pure join table) | 2D |
| 81 | `recommendation_override` | Port | RecommendationOverride | 2D |
| 82 | `recommendation_rationale_factor` | Port | RecommendationRationaleFactor | 2D |
| 83 | `solver_run` | Port | SolverRun | 2D |
| 84 | `solver_run_model_version` | Port | many-to-many association (pure join table) | 2D |
| 85 | `storage_allocation_item` | Port | StorageAllocationItem | 2D |
| 86 | `terminal_recommendation` | Port | TerminalRecommendation | 2D |
| 87 | `yard_placement_item` | Port | YardPlacementItem | 2D |
| 88 | `actual_status_update` | Port | ActualStatusUpdate | 3 |
| 89 | `deviation_log` | Port | DeviationLog | 3 |
| 90 | `drift_measurement` | Port | DriftMeasurement | 3 |
| 91 | `evaluation_metric` | Port | EvaluationMetric | 3 |
| 92 | `ml_model` | Port | MlModel | 3 |
| 93 | `ml_model_version` | Port | MlModelVersion | 3 |
| 94 | `retraining_run` | Port | RetrainingRun | 3 |
| 95 | `status_type` | Port | StatusType | 3 |
| 96 | `user_device` | Port | UserDevice | 2A |
| 97 | `part1_run` | Port | Part1Run | 2C |
| 98 | `recommendation_review` | Port | RecommendationReview | 2D |
## 7. Enumeration summary (81 enumerations, final values)

Values are identical in every view that uses the enumeration, and match the SQL after the accepted changes (rows added at the end are new).

| Enumeration | Views | Values |
|---|---|---|
| AuditAction | 2A | CREATE, UPDATE, DELETE, STATUS_CHANGE, SAFETY_EXCEPTION, REJECTED_ATTEMPT |
| AuditActor | 1 | ADMIN, SYSTEM |
| BaplieFileStatus | 2C | STAGED, ACTIVE, REPLACED, WITHDRAWN |
| BerthStatus | 2A | ACTIVE, MAINTENANCE, INACTIVE |
| BillingStatus | 1 | PENDING, PAID, FAILED, REFUNDED, CANCELLED |
| CapabilityUnit | 2B | TONNES, METRES, TONNES_PER_HOUR, CUBIC_METRES_PER_HOUR, MOVES_PER_HOUR, COUNT, BAR |
| CapacityUnit | 2B, 2D | TONNES, CUBIC_METRES, SQUARE_METRES, TEU, UNITS |
| CargoCategory | 2B | GENERAL_CARGO, BREAK_BULK, DRY_BULK, LIQUID_BULK, GAS, RO_RO, CONTAINERIZED |
| CargoOperation | 2C | DISCHARGE, LOAD |
| CargoQuantityUnit | 2C | TONNES, CUBIC_METRES, UNITS |
| ComponentAssignmentState | 2D | UNASSIGNED, ASSIGNED, STARTED, DONE |
| ComponentType | 2D, 3 | BERTH, PILOT, TUG, TERMINAL_OPS |
| ConstraintCategory | 2A | ENVIRONMENTAL, UKC, VESSEL_SAFETY |
| ConstraintComparator | 2A | MAX_ALLOWED, MIN_REQUIRED |
| ConstraintUnit | 2A | KNOTS, METRES_PER_SECOND, METRES, NAUTICAL_MILES, PERCENT, DEGREES, MINUTES, TONNES |
| ContainerTypeGroup | 2B | GENERAL, REEFER, TANK, OPEN_TOP, FLAT_RACK, VENTILATED, BULK |
| DataSource | 2C | MANUAL, EGMSW_FEED, REGISTRY |
| DatasetType | 3 | HOLDOUT, ROLLING, PRODUCTION |
| DetailKind | 2B | NONE, WAREHOUSE, CONTAINER_YARD, TANK |
| DeviationType | 3 | DELAY, RESOURCE_CHANGED, STATE_MISMATCH |
| DriftMetricType | 3 | PSI, ROLLING_MAE |
| EquipmentRole | 2B | VESSEL_HANDLING, CARGO_TRANSPORT, TRANSFER_SYSTEM |
| EvaluationMetricName | 3 | MAE, OVERRIDE_RATE, WAIT_TIME, WINDOW_ACCURACY |
| FacilityStatus | 2B | ACTIVE, MAINTENANCE, INACTIVE |
| FactorType | 2D | CONSTRAINT, OBJECTIVE_TERM |
| FieldStatusCode | 3 | STARTED, COMPLETED |
| ForecastMethod | 2C | AGGREGATION, LIGHTGBM |
| ForecastPullType | 2C | SCHEDULED, ON_DEMAND, MANUAL |
| ForecastRunStatus | 2C | SUCCESS, FAILED |
| FullEmpty | 2C | FULL, EMPTY |
| IssueSeverity | 2C | ERROR, WARNING |
| LicenseMode | 1 | SETUP, FULL, READ_ONLY |
| LicenseStatus | 1 | PENDING, ACTIVE, SUSPENDED, EXPIRED, REVOKED, SUPERSEDED |
| ModelKey | 3 | M2_SERVICE_TIME, M3_DEMAND_FORECAST, M4_DELAY_RISK, FORECAST_BIAS_CORRECTION |
| ModelVersionStatus | 3 | CHALLENGER, CHAMPION, ARCHIVED, REJECTED |
| MovementDirection | 2B | IN, OUT |
| MovementSource | 2B | VESSEL_DISCHARGE, VESSEL_LOADING, MANUAL |
| NotificationChannel | 2D | WEB, MOBILE |
| NotificationEntityType | 2D | PLAN_COMPONENT, BERTHING_REQUEST, RECOMMENDATION, ENTRY_WINDOW_PROPOSAL, TUGBOAT, RETRAINING_RUN, LICENSE |
| NotificationStatus | 2D | SENT, FAILED, ACKNOWLEDGED |
| NotificationType | 2D | PLAN_ASSIGNMENT, PRE_EXECUTION_REMINDER, TUG_STILL_BUSY, ENTRY_WINDOW_READY, RECOMMENDATION_READY, PLAN_CANCELLED, RETRAINING_REVIEW_NEEDED, LICENSE_EXPIRY_WARNING, ACK_ESCALATION, YARD_RECOMMENDATION_READY, YARD_RECOMMENDATION_FAILED, DRIFT_ALERT |
| OperationalPlanStatus | 2D | CONFIRMED, COMPLETED, CANCELLED |
| OutboxState | 3 | PENDING, SENDING, DONE, REJECTED |
| OverrideComponentType | 2D | BERTH, PILOT, TUG, START_TIME |
| ParseStatus | 2C | PENDING, PARSED, FAILED, QUARANTINED |
| PilotRank | 2A | FIRST, SECOND, THIRD |
| PortStatus | 1 | ONBOARDING, ACTIVE, SUSPENDED, OFFBOARDED |
| ProposalStatus | 2C | PROPOSED, CONFIRMED, REPLACED, REJECTED |
| RateUnit | 2B | MOVES_PER_HOUR, TONNES_PER_HOUR, CUBIC_METRES_PER_HOUR, UNITS_PER_HOUR |
| ReadOnlyReason | 1 | NONE, NO_LICENSE, TOKEN_EXPIRED, SUSPENDED, REVOKED, INVALID_SIGNATURE, CLOCK_ROLLBACK, PORT_SUSPENDED, PORT_OFFBOARDED, LICENSE_NOT_YET_IN_EFFECT |
| RecommendationSource | 2D | AI, FIFO |
| RecommendationVersionStatus | 2D | PENDING_REVIEW, MODIFIED_BY_STAFF, CONFIRMED, REJECTED, INVALIDATED, REPLACED |
| RequestSource | 2C | MANUAL, EXTERNAL |
| RequestStatus | 2C | SUBMITTED, NEEDS_REVISION, PRELIMINARY_APPROVED, CUSTOMS_APPROVED, ENTRY_WINDOW_CONFIRMED, RECOMMENDATION_GENERATED, PLAN_CONFIRMED, PLAN_CANCELLED, COMPLETED, REJECTED, CANCELLED |
| ReservationState | 2D | RESERVED, RELEASED, CONSUMED |
| RetrainingDecision | 3 | PROMOTED, KEPT_CHAMPION, FAILED |
| Role | 2A | PORT_GM, PORT_STAFF, MARINE_SAFETY_OFFICER, PILOTAGE_MANAGER, PILOTAGE_OFFICER, MARINE_PILOT, TUG_MANAGER, TUG_OFFICER, TUG_MASTER, TERMINAL_ADMIN, TERMINAL_OFFICER, TERMINAL_SUPERVISOR |
| ServiceTimeMethod | 2D | FORMULA, ML |
| SettingValueType | 2A | INT, DECIMAL, BOOL, STRING, JSON |
| SlotStatus | 2B | ACTIVE, BLOCKED |
| SolverStatus | 2D | QUEUED, RUNNING, OPTIMAL, FEASIBLE, INFEASIBLE, ERROR |
| SolverTrigger | 2D | NEW_REQUEST, REPLAN, RESOURCE_CHANGE, MANUAL |
| SpeedUnit | 2C | KNOTS, METRES_PER_SECOND, KM_PER_HOUR |
| SyncSource | 3 | MOBILE, TABLET, WEB, EXTERNAL |
| TerminalRecDecision | 2D | PENDING, ACCEPTED, REJECTED, ADJUSTED |
| TerminalRecType | 2D | TIMING, YARD_PLACEMENT, STORAGE_ALLOCATION |
| TerminalType | 2A, 2B, 2D | CONTAINER, NON_CONTAINER |
| TokenDelivery | 1 | PULL, MANUAL |
| TugPowerUnit | 2A | BOLLARD_PULL_TONNES, HP, KW |
| TugStatus | 2A | AVAILABLE, BUSY, MAINTENANCE, INACTIVE |
| UnitType | 2A | HARBOUR_AUTHORITY, MARINE_SERVICES, TERMINAL |
| VisibilityUnit | 2C | NAUTICAL_MILES, KILOMETRES, METRES |
| YardComponentState | 2D | YARD_PENDING, YARD_ACCEPTED, YARD_DONE, YARD_NOT_APPLICABLE |
| YardRecommendationState | 2D | YARD_REC_REQUESTED, YARD_REC_GENERATED, GENERATION_FAILED, YARD_REC_ACCEPTED, YARD_REC_REJECTED, EXPORTED_TO_TOS |
| ZoneDetail | 2B | NONE, REEFER, DANGEROUS_GOODS, OOG |
| DevicePlatform | 2A | ANDROID, IOS |
| Part1Outcome | 2C | WINDOWS_FOUND, NO_FEASIBLE_WINDOW, ENV_DATA_UNAVAILABLE |
| Part1RunStatus | 2C | QUEUED, RUNNING, DONE, FAILED, REPLACED |
| ProviderRank | 2C | PRIMARY, SECONDARY, MANUAL |
| RecommendationPurpose | 2D | INITIAL, DELAY_REPLAN |
| SaasAdminRole | 1 | PLATFORM, LICENSE, BILLING |
## 8. Consolidated Open Issues

All issues from section 8 of the six views are merged here, with duplicates removed. **Status as of 19 October 2026:** Decided, Closed (by FRD v3), Accepted as drawn, Deferred (future work), Fixed, or Open. "Option A" = change the SQL (SQL Change List row given), "Option B" = keep the SQL and map, "Docs" = correct a source document, "Accept" = keep the diagram as drawn. The view-local IDs in brackets (for example 2C OI-05) are the original numbers.

| ID | Issue | Sources | Views (original IDs) | Recommended option | Status |
|---|---|---|---|---|---|
| OI-01 | FRD area IDs cited in the prompts (SAAS, LIC, AC, MD, YD, RC, BQ, BR, EW, ENV, PL, DP, EX, MB, LM) do not exist in `Functional Requirements.md`; `ATLANTECH_FRD_v3` cited by Master Context is not in the project. Operations are traced to FR-0.x to FR-11.x and the UML instead | Functional Requirements; Master Context 0 | 1 (OI-01), 2A (OI-01), 2B (OI-01), 2C (OI-01), 2D (OI-01), 3 (OI-01) | Docs: add the FRD v3 file or a cross-reference table from old to current FR IDs | Closed: FRD v3 attached; section 5 of each view retraced |
| OI-02 | The PortSync DDL export keeps CHECK constraints and GENERATED helper columns only as COMMENTs, so the rules they describe are not enforced as written | PortSync.md DDL | All Port views (new in P7) | Either option: regenerate the DDL with real constraints | Decided: `PortSync_base_ddl_fixes.sql` |
| OI-03 | `berthing_request.status`, `.source` and `baplie_file.status` use types `request_status`, `request_source`, `baplie_file_status` that are never defined; the DDL is not valid MySQL | PortSync DDL | 2C (OI-04) | Either option: declare inline ENUMs (P-15, P-16, P-19) | Decided: Report A 0.1 |
| OI-04 | `tinyint(1)` flag columns are shown as boolean | PortSync | 2A (OI-15), 2B (OI-13), 2C (OI-16) | Accept | Accepted as drawn |
| OI-05 | Compositions are inferred from lifecycle and composite FKs, not stated in a source (LicenseToken, detail tables, yard zone/block/slot, options, factors, plan components, MlModelVersion) | SM-1; PortSync | 1 (OI-15), 2B (OI-10), 2D (OI-17), 3 (OI-16) | Accept | Accepted as drawn |
| OI-06 | Ghost choices: ghosts added beyond the prompt lists (User in 2B, TerminalCargoType in 2C, StorageFacilityCargoCompatibility in 2D) and ghosts omitted where only incoming FKs exist (PlanComponent in 2A, manifest and recommendation in 2B, Recommendation in 2C, StatusType in 2D, SolverRun in 3) | PortSync | 2A (OI-19), 2B (OI-02, OI-03), 2C (OI-02, OI-03), 2D (OI-02, OI-03), 3 (OI-15) | Accept: the FK count check passes | Accepted as drawn |
| OI-07 | Three extra read-only reasons are required but never named; PORT_SUSPENDED, PORT_OFFBOARDED, LICENSE_NOT_YET_IN_EFFECT are proposals. Open whether SQL SUSPENDED becomes LICENSE_SUSPENDED | AD-5 A25, A27, A29; AD-3 N3; Seq 1.13 | 1 (OI-02) | Option A (P-03) with the proposed names; keep SUSPENDED | Decided: Report A, Tier 2 item 4 (names as proposed) |
| OI-08 | SETUP mode: which read-only reason is stored; the current check forces a non-NONE reason when mode is not FULL | SM-3; `chk_license_state_reason` | 1 (OI-03) | Option A (P-01, P-02): NONE in SETUP | Decided: NONE in SETUP |
| OI-09 | Licence EXPIRED vs token expired share one reason (TOKEN_EXPIRED) | AD-5 A14, D6; Seq 1.13 | 1 (OI-04) | Option B: keep TOKEN_EXPIRED, word the message from the licence status (needs P-05) | Decided: keep TOKEN_EXPIRED, word the message from license_status |
| OI-10 | FR-0.2 monthly price vs annual price elsewhere; FR-0.1 and Seq 1.3 "country" with no column; FR-0.3 admin "expire" vs scheduler-only expiry | FR-0.1 to FR-0.3; Seq 1.3; Master Context 4, 7.1; SM-1 | 1 (OI-05) | Docs: align FR-0.x and Seq 1.3 with the SQL and SM-1 | Closed by v3; Seq 1.3 "country" wording still to fix |
| OI-11 | "One subscription package only" (AD-2 N3) vs many plans in `license_plan`, FR-0.2 and Seq 1.7 | AD-2 N3; Seq 1.7; FR-0.2; SaaS.md | 1 (OI-06) | Docs: correct AD-2 N3 | Open (docs): fix AD-2 N3 |
| OI-12 | The SaaS "checks the readiness checklist" (Seq 1.4) but holds none of the port-side facts | SM-2; AD-1 R4, D8; Seq 1.4; Master Context 5.2 | 1 (OI-07) | Docs: record an out-of-band confirmation in `platform_audit_log` | Closed by v3 (FR-SAAS-06) |
| OI-13 | `license.active_port_key` named in the table comment but absent from the DDL; the one-ACTIVE rule is application-only | SaaS.md `license`; SM-1 | 1 (OI-08) | Either option: add it (S-06) | Decided: Report A, Tier 1 item 7 |
| OI-14 | Port suspension reason has no column | SM-2; AD-3 A10; AD-5 A22 | 1 (OI-09) | Option B: keep it in `platform_audit_log.details` (S-10 not needed) | Decided: audit details, no column |
| OI-15 | TLS certificate ID, signer, date and the Port IT contact have no table or column | AD-1 E4; Seq 1.5; AD-20 | 1 (OI-10) | Option A (S-11) | Decided: not built (FW-07) |
| OI-16 | Security reason for revoking one token has no column | AD-3 A5 | 1 (OI-11) | Option B: audit details (S-08 not needed) | Decided: audit details, no column |
| OI-17 | Signing keys and kid live in the KMS; kid only inside the token payload; undecided whether admin sessions and one-time codes go in the SaaS DB or a framework store | Seq 1.1, 1.9; AD-2 R1 to R7 | 1 (OI-12) | Option A (S-02, S-03, S-07) | Decided: framework store, no tables |
| OI-18 | `uq_billing_license_period` blocks a new transaction after REFUNDED or CANCELLED, contrary to AD-4 N4 | AD-4 N4; Seq 1.12 | 1 (OI-13) | Option A (S-09) | Decided: Report A, Tier 2 item 1 |
| OI-19 | The licence hold can only be derived without `on_hold`, and the derivation fails once the suspended licence is EXPIRED | SM-1; AD-3 A2, A3; Seq 1.8, 1.11 | 1 (OI-16) | Option A (S-05) | Decided: Report A, Tier 2 item 2 |
| OI-20 | PortSetting has one operation, below the 3-operation guideline | FR-7.2 | 2A (OI-02) | Accept | Accepted (FR-MD-18 now exists) |
| OI-21 | Devices, API tokens and web sessions are used by the UML but no table exists in PortSync.md | AD-17; AD-18; Seq 2.1 to 2.3 | 2A (OI-03) | Option A (P-10, and document the framework tables, P-11) | Decided: Report A, Tier 1 item 2 |
| OI-22 | Ending a role sets `active_to`, which does not exist; the unique index blocks re-assignment; the last-Port-Admin check cannot tell ended roles apart | AD-18 S9, D8; Seq 2.3 | 2A (OI-04, OI-11) | Option A (P-09) | Decided: Report A, Tier 1 item 1 |
| OI-23 | Forced password change on first login has no flag | AD-17; AD-18; Seq 2.1 | 2A (OI-05) | Option A (P-08) | Decided: Report A, Tier 2 item 5 |
| OI-24 | 409 on a changed record version, but no version column (and no `updated_at` on several tables) | AD-18 D7, S12 | 2A (OI-06) | Option A (P-12) | Decided: in code (`updated_at` as version) |
| OI-25 | "Master-data history" and "audit entry" named separately; only `audit_log` exists | AD-18 S10; Seq 2.6; FR-1.9 | 2A (OI-07) | Docs: one store, `audit_log` | Decided: one store, `audit_log` |
| OI-26 | `audit_log.action` lacks SAFETY_EXCEPTION and a value for rejected attempts (name not given) | AD-13 C5; Seq 2.14; AD-18 S15 | 2A (OI-08), 2D | Option A (P-13, P-14) with the name REJECTED_ATTEMPT | Decided: Report A, Tier 1 item 6 |
| OI-27 | Seq 2.3 creates a user from name, phone and one-time password, but `username` is NOT NULL and UNIQUE | Seq 2.3; PortSync `user` | 2A (OI-09) | Docs: add username to Seq 2.3 | Open (docs): add username to FR-AC-09 and Seq 2.3 |
| OI-28 | `tug_master_schedule` exists but no flow or FR sets Tug Master shifts | Seq 2.5; FR-1.6 | 2A (OI-10) | Docs: extend FR-1.6 and Seq 2.5 to tug masters | Open (docs): mirror pilot shifts; extend FR-MD-08 |
| OI-29 | FR-1.1 berth "location" and FR-1.2 tugs "removed" vs lat/lon and deactivation in SQL and Seq 2.4, 2.5 | FR-1.1, FR-1.2 | 2A (OI-12) | Docs: correct FR-1.1, FR-1.2 | Closed by v3 |
| OI-30 | Port IT Administrator and a generic "Department Manager" have no role value | Users & Actors; AD-18 | 2A (OI-13) | Docs: confirm they are not application users | Closed by v3 |
| OI-31 | CT and NCT Administrators share TERMINAL_ADMIN, told apart by the unit's terminal type | Master Context 7.3; Use Case 2 | 2A (OI-14) | Option B (merge) | Decided (Master Context 7.3) |
| OI-32 | Rules drawn from SQL comments or backend rules only: TugStatus BUSY/AVAILABLE, BerthTerminal 1..* per berth, plan-limit links to LicenseState | PortSync; Seq 2.3, 2.5; AD-18 S8 | 2A (OI-16, OI-17, OI-18) | Accept | Accepted as drawn |
| OI-33 | No UML flow writes `yard_slot.container_id`; who updates slot occupancy is undefined | PortSync `yard_slot`; AD-14 AC, N2 | 2B (OI-04) | Docs: define the writer (later TOS feedback) | Open: who writes `yard_slot.container_id` (manual or FW-08) |
| OI-34 | `storage_facility.blocked_capacity` has no flow or FR | PortSync; FR-9.1 | 2B (OI-05) | Accept: keep the column, no operation | Accepted as drawn |
| OI-35 | NCT capacity reservation (project decision D-46) while NCT is master data only; release of reserved capacity undefined | Master Context 3, 7.3 | 2B (OI-06) | Docs: mark as later phase | Closed by v3 (FW-05) |
| OI-36 | Whether a storage movement updates `current_occupancy` | PortSync `storage_movement`; FR-9.4 | 2B (OI-07) | Docs: state the backend rule | Deferred (FW-05) |
| OI-37 | Seq 2.6 "saves" zone capacity and equipment available quantity, which are derived or generated | Seq 2.6; PortSync | 2B (OI-08) | Docs: correct Seq 2.6 | Open (docs): Seq 2.6 and FR-MD-11 wording |
| OI-38 | FR-9.1 silos and FR-9.5 pipelines and pumps have no table; mapped to storage_type and TRANSFER_SYSTEM as an assumption | FR-9.1, FR-9.5 | 2B (OI-11) | Option B (merge), confirm | Deferred (FW-05) |
| OI-39 | Service-time inputs: Moves not stored; NCT quantity from `berthing_request_cargo`; result unit not stated | Master Context 6.3 B3 | 2B (OI-14) | Docs: state minutes as the unit | Open (docs): state minutes as the unit |
| OI-40 | TerminalType added as an extra enumeration; YardZone.checkYardConstraints needs manifest data from View 2C | PortSync; AD-14 V1 | 2B (OI-12, OI-15) | Accept | Accepted as drawn |
| OI-41 | The pre-ACTIVE BAPLIE state (SQL STAGED) has no UML name | Master Context 7.4; Seq 2.7 | 2C (OI-05) | Docs: add STAGED to the UML vocabulary, keep it in the SQL | Decided: STAGED kept and drawn |
| OI-42 | HAS_ERRORS merges SQL FAILED and QUARANTINED; QUARANTINED keeps the payload needed for correction | AD-8 A9; Seq 2.8 | 2C (OI-06) | Option B (split): keep both SQL values | Decided: SQL names kept (Report B, B-4) |
| OI-43 | Part 1 runs, retries and ENV_DATA_UNAVAILABLE cannot be stored (proposal run FKs are NOT NULL) | AD-10 A5, A24; Seq 2.10; SM-4 | 2C (OI-07, OI-08) | Option A (P-22) | Decided: Report A, Tier 1 item 3 |
| OI-44 | FR-3.9, FR-3.10 say "received" and "originating system or agent resubmits"; SM-4 and AD-9 say SUBMITTED and the creator | FR-3.9, FR-3.10; SM-4; AD-9 | 2C (OI-09) | Docs: correct FR-3.9, FR-3.10 | Closed by v3 |
| OI-45 | The `berthing_request` comment allows return to NEEDS_REVISION from later states; SM-4 and AD-9 only from SUBMITTED | PortSync comment; SM-4; AD-9 N2 | 2C (OI-10) | Option A (P-17, comment only) | Decided: Report B, B-9 |
| OI-46 | Global UNIQUE on `file_hash_sha256` blocks re-upload of a withdrawn file | AD-8 D2, N1 | 2C (OI-11) | Option A (P-21) | Decided: Report A, Tier 1 item 5 |
| OI-47 | Provider failure "raise an alert" has no storage | Seq 2.10, 2.12; AD-10 S8 | 2C (OI-12) | Option B: application log only; confirm | Decided: application log |
| OI-48 | DemandForecastSlot has 2 operations, TideConstituentSet none | Master Context 6.2 A7 | 2C (OI-13) | Accept | Accepted as drawn |
| OI-49 | Status history 1..* from the "history row on creation" rule, not the schema | AD-9 A7 | 2C (OI-14) | Accept | Accepted as drawn |
| OI-50 | Window decisions drawn on EntryWindowProposal though SM-4 shows them as request transitions | SM-4; AD-10 | 2C (OI-15) | Accept | Accepted as drawn |
| OI-51 | `terminal_recommendation.decision` is generic for three types; AD-14 yard states are yard-only; meaning of ADJUSTED | AD-14; Seq 2.16; FR-10.6 | 2B (OI-09), 2D (OI-04) | Option A (P-39): `lifecycle_status` beside `decision` | Decided: Report A, Tier 2 item 7 |
| OI-52 | No solver TIMEOUT value; a timeout is recorded as ERROR | AD-11 D1 | 2D (OI-05) | Accept | Accepted as drawn |
| OI-53 | NO_FEASIBLE_PLAN binding constraints have no place (rationale factors need an option) | AD-11 A7; Seq 2.13 | 2D (OI-06) | Option A (P-33) | Decided: `solver_run.result_detail` |
| OI-54 | A delay re-plan proposal exists while the plan stays CONFIRMED but has no status name and conflicts with `active_key` | AD-12; Seq 2.14; SM-5 | 2D (OI-07) | Option A (P-31) | Decided: Report A, Tier 1 item 4 |
| OI-55 | A FIFO plan is assumed to be a GENERATED version with source FIFO | AD-11 A9, A30, A31 | 2D (OI-08) | Option A (P-29) | Decided: Report A, Tier 2 item 9 |
| OI-56 | SM-5 yard region per plan, but no YARD component type; TERMINAL_OPS assumed | SM-5; PortSync `plan_component` | 2D (OI-09) | Option B: derive the yard state (P-38 not needed) | Decided: derived (DERIVED attributes) |
| OI-57 | Reservation state CONSUMED has no flow | PortSync; SM-5 | 2D (OI-10) | Docs: define when it is set | Decided: set in code when the component completes |
| OI-58 | Reminder and retry scheduling has no table; Seq 2.15 "reminders at T+10, T+20" vs AD-13 N5 retries | AD-13 A24, A25, N5; Seq 2.15 | 2D (OI-11, OI-12) | Docs: correct Seq 2.15; Option A (P-41) for attempt and escalation | Decided: one row per retry; Seq 2.15 wording still to fix |
| OI-59 | Missing notification types (escalation, retry, yard recommendation ready or failed, drift alert); whether SSE-only notices are logged | AD-13; AD-14; FR-7.3; Seq 3.4 | 2D (OI-13), 3 (OI-14) | Option A (P-42), names to confirm | Decided: Report A, Tier 2 item 14 |
| OI-60 | The override row points to the original option, while SM-4 says the override creates a new version | SM-4; AD-11 A16 | 2D (OI-15) | Docs: define the link in AD-11 | Open (docs): define the link in AD-11 |
| OI-61 | Drawing choices: RecommendationOption has no operations; NotificationLog polymorphic link drawn to PlanComponent only; enumerations grouped into packages | Master Context 6.3 B6; PortSync | 2D (OI-14, OI-16, OI-18) | Accept | Accepted as drawn |
| OI-62 | `status_type` has no seed rows; codes for BERTH and TERMINAL_OPS not defined | PortSync; SM-5 | 3 (OI-02) | Either option: seed rows (P-51) | Decided: seed rows (Coding Notes 7) |
| OI-63 | Outbox attribute types assumed; the 30-day purge needs a DONE/REJECTED time no source names | SM-6; AD-15; Seq 3.1 | 3 (OI-03) | Docs: complete the mobile spec | Open: mobile spec |
| OI-64 | Task cache and sync token are named but not defined, so not drawn | Seq 3.1, 3.2; AD-15 N6 | 3 (OI-04) | Docs: complete the mobile spec | Open: mobile spec |
| OI-65 | Which record carries the trust marker; AD-16 N6 vs D2 on whether review makes a record trusted | AD-15 S5; AD-16 A7, D2, N6 | 3 (OI-05, OI-11) | Option A (P-46) on `actual_status_update` | Decided: option B (Report A, Tier 2 item 13) |
| OI-66 | Clock-skew rule differs between AD-15, Seq 3.2 and AD-16 A2 | AD-15; Seq 3.2; AD-16 | 3 (OI-06) | Option A (P-43, P-44) with the AD-15 rule | Decided: `client_sent_at` + `clock_skew_suspect`; reword FR-EX-08 |
| OI-67 | Rollback and "retraining skipped" have no value (FW) | AD-16 P10; Seq 3.4 | 3 (OI-07) | Defer: P-50 when retraining leaves FW | Deferred (FW-01) |
| OI-68 | Master Context 6.4 marks retraining and bias correction FW; AD-16 and Seq 3.4 draw them as normal flows | Master Context 6.4; AD-16; Seq 3.4 | 3 (OI-08) | Docs: Master Context prevails, label the flows FW | Closed by v3 |
| OI-69 | `deviation_log` "P19-03 deferred"; significance threshold and storage undefined | PortSync; AD-16 N1 | 3 (OI-09) | Option A (P-48) with `port_setting` thresholds | Decided: thresholds in `port_setting`, significance in code |
| OI-70 | Rejected sync events exist only on the phone | SM-6; AD-15 A12 | 3 (OI-10) | Option B: phone only (P-49 optional) | Decided: phone only |
| OI-71 | Which events count as "creation events" rejected in READ_ONLY is not defined for the field app | AD-15 DR, N3; SM-6 | 3 (OI-13) | Docs: define them in AD-15 | Open (docs): define creation events in AD-15 |
| OI-72 | Override-rate and safety-limit links are rules, not FKs | AD-16 N3, N4; Master Context 6.4 | 3 (OI-12) | Accept | Accepted as drawn |
| OI-73 | Defects F-01 and F-02 (section 3.1) | This index | 2C, 2D | Fix the two lines in the next diagram revision | Fixed in this patch |
