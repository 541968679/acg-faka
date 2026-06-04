# Repository Guidelines

## Project Overview

**ACG-Faka / KaynLab AI** — PHP 8 virtual goods automated delivery system. A custom MVC framework (not Laravel/Symfony) with Eloquent ORM and Smarty templates.

- PHP >= 8.0 required (uses PHP 8 attributes, match expressions, union types)
- Database: MySQL 5.6+ with `illuminate/database` Eloquent ORM
- Frontend: Smarty templates + jQuery + layui
- Deployment: Docker-based local dev and production remote-build deploy

## Project Structure & Module Organization

```
app/
├── Controller/          # Controllers (Admin/, User/, Shared/, Base/)
│   ├── Admin/          # Backend pages + API (/admin/...)
│   ├── User/           # Frontend pages + API (/user/...)
│   ├── Shared/         # Shared API (sub-station system)
│   └── Base/           # Base controllers (auth, permissions, view)
├── Model/              # Eloquent models (Order, Commodity, Card, User, Pay, etc.)
├── Service/            # Business logic layer (Order, Shop, Pay, Email, etc.)
├── Service/Bind/       # Service interface implementations
├── Interceptor/        # Middleware (Session validation, permissions, WAF)
├── Consts/             # Constants (Hook points, payment constants, etc.)
├── Entity/             # Data transfer entities (PayEntity)
├── Pay/                # Payment interface layer
│   ├── Base.php        # Payment base class
│   ├── Pay.php        # Payment interface (trade method)
│   └── Epay/          # EasyPay implementation
├── Util/               # Utility classes
├── View/               # Smarty templates (Admin/, User/, 404.html, etc.)
│   └── User/Theme/
│       ├── AiHub/     # Current theme (customized)
│       └── Cartoon/   # Original theme (reference only)

kernel/                 # Framework core (do not modify)
├── Kernel.php         # Startup engine
├── Helper.php         # Global functions (config, hook, dd, css, js, ready)
├── Plugin.php        # Plugin manager (hook system)
├── Plugin/           # Plugin subsystem
├── Annotation/       # PHP 8 attributes (@Inject, @Interceptor)
├── Container/       # DI container
├── Context/         # Request context
├── Database/        # Database initialization
├── Waf/            # Web Application Firewall
└── Cache/          # Caching

config/               # Configuration files (PHP arrays)
├── app.php          # Version info
├── database.php     # DB connection
├── dependencies.php # DI mappings
├── store.php        # App store credentials
└── waf/            # WAF rules

assets/              # Frontend static resources
├── aihub/          # AiHub theme CSS/JS (primary theme to edit)
├── common/        # Common JS/CSS (do not modify)
└── user/          # User JS/CSS (do not modify)

docker/              # Docker configuration
├── app/Dockerfile
├── app/entrypoint.sh
├── app/vhost.conf
└── mysql/         # MySQL initialization

runtime/             # Runtime files (logs, cache - needs write permission)
```

## Build, Test, and Development Commands

### Docker (Local Development)
```bash
# Start local stack
docker-compose up -d --build

# Stop containers
docker-compose down

# Full reset (removes volumes and DB data)
docker-compose down -v

# View logs
docker-compose logs -f app    # PHP/Apache logs
docker-compose logs -f db      # MySQL logs

# Shell into container
docker exec -it acgfaka-app bash
```

**URLs:**
- App: http://127.0.0.1:8080
- MySQL: localhost:3307 (user: faka, password from DB_PASSWORD)

### Composer
```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Within Docker container (as www-data)
sudo -u www-data composer install
```

### Deployment (Production)
```bash
# Deploy to production server
bash deploy.sh
```

## Coding Style & Naming Conventions

### PHP File Structure
Every PHP file MUST follow this order:
```php
<?php
declare(strict_types=1);

namespace App\...\;

use ...;
use ...;

/**
 * Class/Interface description
 * @package App\...
 */
#[Attribute]
class Example extends Base
{
    // code
}
```

### Indentation & Formatting
- **4 spaces** for indentation (not tabs)
- Opening brace on same line for classes/functions: `class Example {`
- Space before parentheses: `function name() {`
- No space before square brackets: `$array[0]`
- Single blank line between use blocks and class definition
- Two blank lines between methods

### Naming Conventions
| Element | Convention | Example |
|---------|-----------|---------|
| Classes/Interfaces | PascalCase | `App\Service\Order` |
| Traits | PascalCase | `Kernel\Annotation\Inject` |
| Methods | camelCase | `getTradeAmount()`, `orderSuccess()` |
| Variables | camelCase | `$commodityId`, `$userGroup` |
| Constants | SCREAMING_SNAKE | `Bill::TYPE_SUB`, `Hook::USER_API_*` |
| Private properties | camelCase with underscore prefix | `private $_cache` |
| Database columns | snake_case | `trade_no`, `create_time`, `user_id` |
| Controller files | PascalCase | `Dashboard.php`, `Commodity.php` |
| Model files | PascalCase | `User.php`, `Order.php` |
| Template files | PascalCase | `Dashboard/Index.html`, `Trade/Commodity.html` |

### PHP 8 Features
- Use **union types**: `function foo(int|string $bar): int|null`
- Use **match expressions**: `match ($type) { ... }`
- Use **attributes**: `#[Inject]`, `#[Interceptor(ManageSession::class)]`
- Use **constructor property promotion**: `public function __construct(#[Inject] private Service $service) {}`
- Use **named arguments**: `someFunction(param: 'value')`

### Type Declarations
- Use strict types: `declare(strict_types=1);`
- All functions/methods must have return types when possible
- Use nullable types: `?string`, `?int`, `?array`
- Use union types: `int|string`, `Commodity|int`
- Use `mixed` type sparingly (requires PHP 8.0+)

### Use Statements
- One use statement per line
- Alphabetical order within groups
- Blank line between use groups
- Use aliases when names conflict: `use Some\Long\Class as Alias;`
- Avoid fully qualified class names in code (use `use` statements)

### DocBlocks
All functions require docblocks:
```php
/**
 * Calculate order amount with discounts
 * @param int $owner
 * @param int $num
 * @param Commodity $commodity
 * @param UserGroup|null $group
 * @return float
 * @throws JSONException
 */
public function calcAmount(...): float
```

### Error Handling
- Use `\Kernel\Exception\JSONException` for API errors
- Use `try-catch` blocks with specific exception types
- Never suppress errors with `@`
- Always log significant errors via `debug()` function
- Return early on error conditions

### Dependency Injection
Use `#[Inject]` attribute for service injection:
```php
use Kernel\Annotation\Inject;

class OrderService
{
    #[Inject]
    private Shared $shared;

    #[Inject]
    private Email $email;
}
```

### Database (Eloquent)
```php
// Query building
Model::query()->where("status", 1)->first();
Model::query()->whereIn("id", [1, 2, 3])->get();

// Relationships
public function commodity(): BelongsTo { ... }
public function items(): HasMany { ... }

// Timestamps
public $timestamps = false; // if no created_at/updated_at

// Type casting
protected $casts = ['id' => 'integer', 'balance' => 'float'];
```

### Template Files (Smarty)
- Variables: `{$variable_name}`
- Functions: `{include file="header.html"}`
- Modifiers: `{$var|escape}`
- No PHP logic in templates

### Frontend Assets
- Edit theme CSS in `assets/aihub/css/aihub.css`
- Edit theme JS in `assets/aihub/js/aihub-index.js`
- Use CDN resources via `css()` and `js()` helper functions
- DEBUG mode loads individual JS files; production loads `_.js` (combined)

## Testing Guidelines

This project does not have an automated test suite. Validate changes with:

1. **Smoke Tests** (via browser/Docker):
   - Load homepage `/` and verify no PHP errors
   - Load admin `/admin` and verify login works
   - Test affected pages render correctly

2. **Database Flows**:
   - Test CRUD operations against local MySQL container
   - Verify foreign key constraints work correctly
   - Check transaction rollback on errors

3. **Manual Checklist**:
   - [ ] Pages load without errors
   - [ ] Forms submit correctly
   - [ ] Database operations work
   - [ ] Payment callbacks process correctly

## Security Guidelines

- **Never commit secrets**: Use environment variables for credentials
- **Sanitize all input**: Use WAF and validate via `Interceptor` classes
- **SQL injection**: Use Eloquent ORM or parameterized queries only
- **XSS**: Use `escape` modifier in templates, `htmlspecialchars()` in PHP
- **CSRF**: Implement via framework's built-in protections
- **File uploads**: Validate file types and sizes strictly

## Deployment Notes

- Production site: `https://shop.kaynstech.com`
- Production server: GCP VM `acgfaka-hk` (`myvps-2606to2608`, `asia-east2-a`, `34.96.139.162`)
- Project path: `/opt/acgfaka/repo`; production env file: `/opt/acgfaka/.env.prod`
- Deploy via `bash deploy.sh` to make the server pull `origin/main`, build the Docker image, restart the stack, and run health checks
- View deploy logs: `sudo tail -f /opt/acgfaka/deploy.log`
- View app logs: `sudo docker logs -f acgfaka-app`
