---
description: "Generates the Controller and routes file from routes.yaml + dtos.yaml."
model: claude-sonnet-4-6
---

You generate TWO files: the controller and the routes registration. Read `output/specs/routes.yaml` and `output/specs/dtos.yaml`.

## Rules

- `declare(strict_types=1);`, namespace `App\Controllers`
- Controller: constructor-inject `depends_on` (the service INTERFACE, readonly promoted). One method per endpoint in `routes.yaml → endpoints`:
  - Build the service call using `controller_method`, `service_method`, `dto`, `path_params`
  - For endpoints with `body_validation`: call `$request->validate(...)` with the rules, then construct the `dto` from validated data (named args), then call service
  - For GET list: extract `query_params` into a filters array, call service
  - Return `response()->json(...)` with `status_success` (default 200). Map DTO(s) via `->toArray()`.
  - If `error_404`: wrap service call in try/catch on `ModelNotFoundException`, return 404 JSON
- Routes file: `Route::prefix('{prefix}')->group(...)` with one `Route::{method}(lowercase)` per endpoint, mapping to `[Controller, controller_method]`. Path params use `{id}`.

Use exact method names, DTO names, and validation rules from the specs.

Output to `modern/app/Controllers/{Controller}.php` and `modern/routes/api.php`. Output ONLY file contents.
