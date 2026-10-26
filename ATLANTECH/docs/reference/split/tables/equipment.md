<!-- Generated from docs/reference/database/PortSync.sql lines 356-364 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `equipment` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `terminal_id` bigint NOT NULL,
  `equipment_category_id` bigint NOT NULL,
  `quantity` int NOT NULL COMMENT 'How many units of this type the terminal has',
  `maintenance_quantity` int NOT NULL DEFAULT 0,
  `blocked_quantity` int NOT NULL DEFAULT 0,
  `available_quantity` int COMMENT 'GENERATED (stored): ((quantity - maintenance_quantity) - blocked_quantity) | Usable units; units busy in a plan are derived from plans (Module 18)'
);

<!-- Generated from docs/reference/database/PortSync.md lines 1214-1249 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `equipment`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `terminal_id` | `bigint` | NO |  | FK → `terminal.id` |  |
| `equipment_category_id` | `bigint` | NO |  | FK → `equipment_category.id` |  |
| `quantity` | `int` | NO |  |  | How many units of this type the terminal has |
| `maintenance_quantity` | `int` | NO | `0` |  |  |
| `blocked_quantity` | `int` | NO | `0` |  |  |
| `available_quantity` | `int` | YES |  |  | GENERATED (stored): ((quantity - maintenance_quantity) - blocked_quantity) \| Usable units; units busy in a plan are derived from plans (Module 18) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_eq_quantity` | `((quantity > 0) and (maintenance_quantity >= 0) and (blocked_quantity >= 0) and ((maintenance_quantity + blocked_quantity) <= quantity))` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `idx_eq_category` | No | equipment_category_id |
| `uq_eq_id_terminal` | Yes | id, terminal_id |
| `uq_eq_terminal_category` | Yes | terminal_id, equipment_category_id |

**Foreign keys**

| Constraint | Column(s) | References | Rules |
|---|---|---|---|
| `fk_eq_category` | equipment_category_id | [`equipment_category`](#equipment_category) (id) |  |
| `fk_eq_terminal` | terminal_id | [`terminal`](#terminal) (id) |  |

**Referenced by:** [`equipment_capability`](#equipment_capability).equipment_id, [`equipment_cargo_compatibility`](#equipment_cargo_compatibility).equipment_id, terminal_id

---
