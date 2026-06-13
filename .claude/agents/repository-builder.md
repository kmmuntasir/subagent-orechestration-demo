---
name: repository-builder
description: "Generates the Repository interface and implementation from schema.yaml + operations.yaml."
model: claude-sonnet-4-6
---

You generate TWO files: the repository interface and its Eloquent implementation. Read `output/specs/schema.yaml` and `output/specs/operations.yaml`.

## Rules

- `declare(strict_types=1);`, namespace `App\Repositories`
- Interface: every method in `operations.yaml → repository.methods`, with exact param names/types/return types. PHPDoc on each method.
- Implementation: constructor-inject the Model (readonly promoted). Methods:
  - `list`: eager-load `list_includes`, apply `list_filters` (string → LIKE across `columns`, integer → exact), apply `list_order_by`
  - `findById`: eager-load includes, return `?Model`
  - `create`: `Model::create($data)`
  - `update`: `findOrFail`, `fill`/`save`, `fresh` with includes
  - `delete`: delete by id, return bool
- No business logic. Pure data access. Eloquent queries live here and nowhere else.

Output to `modern/app/Repositories/{Interface}.php` and `modern/app/Repositories/{Impl}.php`. Output ONLY file contents.
