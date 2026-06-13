---
description: "Generates the Service interface and implementation from operations.yaml + dtos.yaml."
model: claude-sonnet-4-6
---

You generate TWO files: the service interface and implementation. Read `output/specs/operations.yaml` and `output/specs/dtos.yaml`.

## Rules

- `declare(strict_types=1);`, namespace `App\Services`
- Interface: every method in `operations.yaml → service.methods`, with exact signatures. Accept/return DTOs (use `return_element` for list return type in PHPDoc). PHPDoc on each method including `@throws`.
- Implementation: constructor-inject `depends_on` (the repository INTERFACE, readonly promoted).
  - `list{Entity}s`: call repo `list`, map each model to `return_element::fromModel()`, return array
  - `get{Entity}`: call repo `findById`, throw `ModelNotFoundException` if null, return `return_element::fromModel()`
  - `create{Entity}`: call repo `create` with `$dto->toArray()`, return `return_element::fromModel()` (load includes first)
  - `update{Entity}`: call repo `update` with `$dto->toArray()`, return `return_element::fromModel()`
  - `delete{Entity}`: call repo `delete`, throw `ModelNotFoundException` if false
- Use exact DTO class names from `dtos.yaml`.

Output to `modern/app/Services/{Interface}.php` and `modern/app/Services/{Impl}.php`. Output ONLY file contents.
