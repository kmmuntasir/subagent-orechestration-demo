---
name: decompose-legacy
description: End-to-end modernization of a legacy PHP CRUD file. Extracts raw facts (script), designs a contract spec bundle (schema/dtos/operations/routes YAML), then fans out five builder subagents in PARALLEL to generate a full PHP 8.4 layered architecture (Model, DTOs, Repository, Service, Controller, Routes). Use when modernizing legacy PHP to a layered API in one shot, or when designing an API contract from a messy CRUD page.
---

You are a software architect and orchestrator. Modernize a legacy PHP CRUD file into a clean, layered PHP 8.4 API in ONE invocation. Three phases: **extract** (script) → **design** (you) → **fan out** (builder subagents).

## Input

The path to the legacy PHP file is provided when the skill is invoked.

---

## Phase 1 — Extract (run the script)

Run the deterministic extractor. It pulls raw facts — table names, columns, JOINs/foreign keys, `$_POST`/`$_GET` fields, form `required` attributes — with zero hallucination:

```
node scripts/extract_raw.js <source_file>
```

This writes `output/specs/_raw_extract.json` and prints it. Read it.

**You decide nothing in this phase.** The script is the source of truth for names that already exist in the code. Never invent a column or table name the script didn't report — if something looks missing, say so instead of guessing.

---

## Phase 2 — Design (your judgment)

Using `_raw_extract.json` as the factual basis, design the contract and write exactly four files to `output/specs/`:

| File | Drives | Format reference |
|------|--------|------------------|
| `schema.yaml` | Model + Repository | `references/schema-format.yaml` |
| `dtos.yaml` | DTOs (+ cross-read by Service/Controller) | `references/dtos-format.yaml` |
| `operations.yaml` | Repository + Service | `references/operations-format.yaml` |
| `routes.yaml` | Controller + Routes | `references/routes-format.yaml` |

**Before generating each file, read its format reference** to match the exact structure.

### The split — what the script gives you vs. what you decide

| Concern | Source | Notes |
|---------|--------|-------|
| Table name, columns | **script** (`tables[]`) | Authoritative. Do not rename. |
| Foreign keys | **script** (`relationships[]`, `*_id` cols) | JOINs + naming convention together. |
| Form fields, GET params | **script** (`post_fields`, `get_params`) | These become inputs/filters. |
| Required flags | **script** (`form_required_fields`) | From HTML `required` attrs. |
| Column **types** | **you** | Infer from name/semantics: `*_id`→integer FK, `price`→decimal, `*_at`→datetime, `stock`/`count`→integer, free text→string/text. |
| Entity/class/DTO **naming** | **you** | Apply the conventions below. |
| DTO **shapes** (base/create/update/response) | **you** | Split fields per DTO purpose. |
| Method **signatures**, return types | **you** | Contract design. |
| **Validation rules** | **you** | Sensible rules from field semantics (e.g. `min:0` on price/stock, `exists` on FKs). |

## Naming Conventions (apply everywhere, stay consistent)

- Entity: singular PascalCase (Product)
- Table: snake_case plural (products) — taken from the script
- Model class: `{Entity}` (Product)
- Repository: `{Entity}Repository` + `{Entity}RepositoryInterface`
- Service: `{Entity}Service` + `{Entity}ServiceInterface`
- Controller: `{Entity}Controller`
- DTOs: `{Entity}DTO`, `Create{Entity}DTO`, `Update{Entity}DTO`, `{Entity}ResponseDTO`
- Service methods: `list{Entity}s`, `get{Entity}`, `create{Entity}`, `update{Entity}`, `delete{Entity}`

---

## Phase 3 — Fan out (invoke builder subagents in parallel)

Once all four spec files are written, generate the code by launching the five builder subagents via the Task tool.

**CRITICAL — run them concurrently:** Issue all five Task tool calls in a **single assistant turn** so they execute in parallel. Do NOT run them one at a time. Parallelism is the point: every builder reads the shared spec bundle (not each other's output), so all five are parallel-safe.

Each subagent's own definition holds its generation rules — you only need to trigger it with a short task prompt telling it to read its spec input(s) and produce its output. Example prompt for each: *"Read your spec input(s) under `output/specs/` and generate your output file(s) per your rules."*

| `subagent_type` | Reads | Produces |
|-----------------|-------|----------|
| `model-builder` | `output/specs/schema.yaml` | `modern/app/Models/{Entity}.php` |
| `dto-builder` | `output/specs/dtos.yaml` | `modern/app/DTO/{Entity}/*.php` |
| `repository-builder` | `output/specs/schema.yaml`, `operations.yaml` | `modern/app/Repositories/{Entity}RepositoryInterface.php` + `{Entity}Repository.php` |
| `service-builder` | `output/specs/operations.yaml`, `dtos.yaml` | `modern/app/Services/{Entity}ServiceInterface.php` + `{Entity}Service.php` |
| `controller-builder` | `output/specs/routes.yaml`, `dtos.yaml` | `modern/app/Controllers/{Entity}Controller.php` + `modern/routes/api.php` |

---

## Rules

- Run all three phases in sequence for a single invocation.
- Use the exact formats in `references/`. The builder subagents parse mechanically.
- Be consistent: a class/method/DTO name in one spec must appear identically in every spec that references it.
- Never fabricate a column or table. If the design needs data the script didn't extract, flag it rather than inventing.

## When done — report

- `_raw_extract.json` written
- the four spec files written
- the five builders completed, with the list of generated files (Model, DTOs, Repository, Service, Controller, Routes)
