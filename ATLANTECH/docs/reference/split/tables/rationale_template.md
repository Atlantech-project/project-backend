<!-- Generated from docs/reference/database/PortSync.sql lines 651-658 by build-index.mjs. Do not edit: edit the source and rebuild. -->
CREATE TABLE `rationale_template` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `version` int NOT NULL,
  `template_text` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `active_code` varchar(60) UNIQUE COMMENT 'GENERATED (stored): if(is_active,code,NULL)'
);

<!-- Generated from docs/reference/database/PortSync.md lines 2121-2148 by build-index.mjs. Do not edit: edit the source and rebuild. -->
## `rationale_template`

> Fixed, versioned templates; never LLM (D11).

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `bigint` | NO |  | PK · AI |  |
| `code` | `varchar(60)` | NO |  |  |  |
| `version` | `int` | NO |  |  |  |
| `template_text` | `text` | NO |  |  |  |
| `is_active` | `tinyint(1)` | NO | `1` |  |  |
| `active_code` | `varchar(60)` | YES |  | UQ | GENERATED (stored): if(is_active,code,NULL) |

**Check constraints**

| Name | Rule |
|---|---|
| `chk_rt_version` | `(version > 0)` |

**Indexes**

| Name | Unique | Columns |
|---|---|---|
| `uq_rt_code_version` | Yes | code, version |

**Referenced by:** [`recommendation_option`](#recommendation_option).rationale_template_id, [`terminal_recommendation`](#terminal_recommendation).rationale_template_id

---
