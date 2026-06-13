# Application Development Rules

## General

PHP 8.4 application built on Laravel 11+. The target architecture is a strict layered API (Model → DTO → Repository → Service → Controller → Route). Follow the Laravel docs and PHP FIG standards (PSR-4, PSR-12) as primary references. `declare(strict_types=1);` is mandatory in every PHP file.

This project modernizes legacy procedural CRUD (see `legacy/`) into a clean, framework-grade API (see `modern/`). Never mix the two layers.

## Documentation & Syntax Lookup

When unsure about syntax, a method signature, a framework API, or any PHP/Laravel/Pest detail, **use the Context7 MCP server** to fetch authoritative, up-to-date docs before guessing. Context7 serves the latest documentation for languages, frameworks, and tech stacks and is the preferred source over training-data recall.

Resolve the library first (e.g., `laravel`, `pestphp`, `php`), then query the specific symbol or behavior. Prefer it whenever you need:
- Exact method signatures, return types, or parameter names
- Version-specific syntax (PHP 8.4 enums, readonly classes, Laravel 11 facades)
- Framework conventions you cannot recall with confidence
- Anything you would otherwise state from memory without verification

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

The five layers map to the `decompose-legacy` builder subagents. Each layer talks only to the one below it.

- **Model** — Eloquent, namespace `App\Models`. Defines `$table`, `$fillable`, `$casts`, and relationship methods. No queries on relations here beyond definitions. Entity names are singular PascalCase (`Product`); tables are snake_case plural (`products`).
- **DTO** — namespace `App\DTO\{Entity}`. Every DTO is a `readonly class` with constructor property promotion. Four shapes per entity: base (`{Entity}DTO`), `Create{Entity}DTO`, `Update{Entity}DTO` (all nullable), `{Entity}ResponseDTO`. Provide `fromModel()` factories and `toArray()` serializers.
- **Repository** — namespace `App\Repositories`. An interface plus its Eloquent implementation. Eloquent queries live **here and nowhere else**. Constructor-inject the Model (readonly promoted). No business logic — pure data access.
- **Service** — namespace `App\Services`. An interface plus its implementation. Constructor-inject the repository **interface** (readonly promoted). Accepts/returns DTOs, not models. Throws `ModelNotFoundException` when a lookup fails.
- **Controller** — namespace `App\Controllers`. Constructor-inject the service **interface**. One method per endpoint: validate request → build DTO → call service → return `response()->json(...)`. No business or query logic.

Dependency direction: **Controller → Service (interface) → Repository (interface) → Model**. Always type-hint the interface, never the concrete class.

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

Use Eloquent and the query builder. **Parameterized queries only** — bind values, never interpolate:

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

The `legacy/` code interpolates values directly into SQL strings. That pattern is forbidden in `modern/` code.

## Environment Configuration

All config via environment variables (`.env`). Never hardcode credentials.

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

- Deploy the API on **Render** (PHP via Dockerfile) — alternatively Laravel Forge/Vapor
- Build command: `composer install --no-dev --optimize-autoloader`
- Start command: `php artisan serve --host 0.0.0.0 --port $PORT` (or configure PHP-FPM + nginx)
- Set environment variables in the hosting dashboard
- Run migrations on deploy: `php artisan migrate --force`

## Security

- Validate **all** request input via `$request->validate(...)` or form requests
- Parameterized queries / Eloquent bindings only — never string-concatenated SQL (prevents injection)
- Escape output when rendering HTML — use `htmlspecialchars()` (the legacy pages do this for display)
- No secrets in code — everything via `.env`
- CORS configured for the specific frontend URL only
- Type every signature (`declare(strict_types=1)` + param/return types) — surfaces bad input early
