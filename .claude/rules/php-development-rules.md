# Application Development Rules

## General

PHP 8.4 app on Laravel 11+. Target = strict layered API (Model → DTO → Repository → Service → Controller → Route). Follow Laravel docs + PHP FIG standards (PSR-4, PSR-12) as primary refs. `declare(strict_types=1);` mandatory every PHP file.

Project modernizes legacy procedural CRUD (see `legacy/`) into clean framework-grade API (see `modern/`). Never mix layers.

## Documentation & Syntax Lookup

Unsure about syntax, method signature, framework API, any PHP/Laravel/Pest detail? **Use Context7 MCP server** to fetch authoritative up-to-date docs before guessing. Context7 serves latest docs for languages, frameworks, tech stacks — preferred over training-data recall.

Resolve library first (e.g. `laravel`, `pestphp`, `php`), then query specific symbol/behavior. Prefer when need:
- Exact method signatures, return types, parameter names
- Version-specific syntax (PHP 8.4 enums, readonly classes, Laravel 11 facades)
- Framework conventions cannot recall with confidence
- Anything would otherwise state from memory without verification

## Project Structure

```
modern/
    app/
        Models/            # Eloquent models
        DTO/{Entity}/      # Request/response DTOs (readonly classes)
        Repositories/      # {Entity}RepositoryInterface + implementation
        Services/          # {Entity}ServiceInterface + implementation
        Controllers/       # HTTP controllers (thin)
    routes/
        api.php            # API route registration
    composer.json
legacy/
    db.php                 # Legacy mysqli bootstrap (reference only)
    *.php                  # Procedural CRUD pages (source of truth for extraction)
output/
    specs/                 # Generated schema/dtos/operations/routes YAML (gitignored)
```

## Layered Architecture Conventions

Five layers map to `decompose-legacy` builder subagents. Each layer talks only to one below.

- **Model** — Eloquent, namespace `App\Models`. Defines `$table`, `$fillable`, `$casts`, relationship methods. No queries on relations here beyond definitions. Entity names singular PascalCase (`Product`); tables snake_case plural (`products`).
- **DTO** — namespace `App\DTO\{Entity}`. Every DTO is `readonly class` with constructor property promotion. Four shapes per entity: base (`{Entity}DTO`), `Create{Entity}DTO`, `Update{Entity}DTO` (all nullable), `{Entity}ResponseDTO`. Provide `fromModel()` factories + `toArray()` serializers.
- **Repository** — namespace `App\Repositories`. Interface + Eloquent implementation. Eloquent queries live **here and nowhere else**. Constructor-inject Model (readonly promoted). No business logic — pure data access.
- **Service** — namespace `App\Services`. Interface + implementation. Constructor-inject repository **interface** (readonly promoted). Accepts/returns DTOs, not models. Throws `ModelNotFoundException` when lookup fails.
- **Controller** — namespace `App\Controllers`. Constructor-inject service **interface**. One method per endpoint: validate request → build DTO → call service → return `response()->json(...)`. No business or query logic.

Dependency direction: **Controller → Service (interface) → Repository (interface) → Model**. Always type-hint interface, never concrete class.

## Routing

Register routes in `modern/routes/api.php`:

```php
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::post('/', [ProductController::class, 'store']);
    Route::put('/{id}', [ProductController::class, 'update']);
    Route::delete('/{id}', [ProductController::class, 'destroy']);
});
```

- RESTful resource naming (`/api/products`, `/api/products/{id}`)
- Correct HTTP methods (GET, POST, PUT, DELETE)
- Always return JSON

## Database

Use Eloquent + query builder. **Parameterized queries only** — bind values, never interpolate:

```php
// Correct — bindings via Eloquent/query builder
$products = Product::query()
    ->where('category_id', $categoryId)
    ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%"))
    ->orderByDesc('created_at')
    ->get();

// Raw SQL only when necessary — always bound
$products = DB::select('SELECT * FROM products WHERE category_id = ?', [$categoryId]);
```

`legacy/` code interpolates values directly into SQL strings. That pattern forbidden in `modern/` code.

## Environment Configuration

All config via env vars (`.env`). Never hardcode credentials.

| Variable | Required | Default |
|---|---|---|
| `APP_ENV` | No | `local` |
| `APP_KEY` | Yes | — |
| `APP_DEBUG` | No | `true` |
| `DB_CONNECTION` | No | `mysql` |
| `DB_HOST` | No | `127.0.0.1` |
| `DB_DATABASE` | Yes | — |
| `DB_USERNAME` | Yes | — |
| `DB_PASSWORD` | Yes | — |
| `FRONTEND_URL` | Yes | — |

## Deployment

- Deploy API on **Render** (PHP via Dockerfile) — alternatively Laravel Forge/Vapor
- Build command: `composer install --no-dev --optimize-autoloader`
- Start command: `php artisan serve --host 0.0.0.0 --port $PORT` (or configure PHP-FPM + nginx)
- Set env vars in hosting dashboard
- Run migrations on deploy: `php artisan migrate --force`

## Security

- Validate **all** request input via `$request->validate(...)` or form requests
- Parameterized queries / Eloquent bindings only — never string-concatenated SQL (prevents injection)
- Escape output when rendering HTML — use `htmlspecialchars()` (legacy pages do this for display)
- No secrets in code — everything via `.env`
- CORS configured for specific frontend URL only
- Type every signature (`declare(strict_types=1)` + param/return types) — surfaces bad input early