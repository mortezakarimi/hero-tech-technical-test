<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>

## 1\. System Design & Core Architecture

This document defines the high-scale system design and architectural constraints for the HeroTech Back-End Developer Technical Challenge. The core objective is to build a highly available, concurrent event booking system using PHP, Laravel 13, MySQL, and Redis.

### Architectural Patterns

To ensure strict decoupling, testability, and adherence to SOLID principles under TDD, the system must employ the following patterns:

* Service-Repository Pattern: Controllers must remain lean, handling only HTTP request reception and JSON response formatting. Business logic must reside in dedicated Domain Services, while data querying and state persistence must be abstracted into a Repository Layer.
* Data Transfer Objects (DTOs): Data flowing between controllers, services, and repositories must be strongly typed using DTOs to eliminate reliance on loosely typed arrays.
* Database Transaction Isolation: All state-changing multi-step queries must be wrapped within database transactions to ensure atomicity.
* API Versioning: All api should have versioning to ensure changes not affect on the users after releases.

### Tech Stack Blueprint

* Framework: Laravel 13 (Latest Stable)
* Environment: Laravel Sail (Dockerized Development)
* Database: MySQL 8.x (Relational Storage, InnoDB engine)
* Caching & Concurrency Control: Redis
* Authentication: Laravel Sanctum (Token-based API auth)
* Documentation: L5-Swagger (OpenAPI 3.0 annotations)
* Testing Framework: Pest PHP (Goal: 100% Code Coverage via TDD)
* Performance Testing: k6 (Resilience and load validation)

## 2\. Critical Engineering Concerns & Concurrency Strategy

The paramount challenge is handling the "thundering herd" problem—thousands of concurrent users trying to book a single event simultaneously. The system must ensure that final bookings never exceed defined capacity under any load.

### A. Race Condition Mitigation (Overselling Prevention)

The implementation agent must utilize one of the following high-scale paradigms within the Booking Service:

1. Pessimistic Locking (Database Level):
2. Use SELECT ... FOR UPDATE when reading an event's capacity inside a transaction. This locks rows until the transaction commits, ensuring sequential capacity evaluation.
3. Atomic Redis Counters (Cache Level \- Recommended for Max Throughput):
4. Offload capacity tracking to Redis strings using atomic actions. Upon event creation, seed a key event:{id}:slots with the capacity value. When a booking request arrives:
    * Execute an atomic decrement (DECR / DECRBY).
    * If the returned value is less than 0, immediately reject the request at the cache layer with a 429 Too Many Requests or 422 Unprocessable Entity status before executing a database query.
    * If the value is $\\ge 0$, proceed to asynchronously or synchronously persist the booking in MySQL.

### B. Unique Active Booking Invariant

* A user can only hold one active booking per event.
* To prevent concurrent bypasses of this rule, a Database Unique Composite Constraint must be enforced on (user\_id, event\_id) where the status is active/confirmed.

## 3\. Data Schema Specifications

### Event Entity

Must support event creation, retrieval, and structural listing.

* id (Big Integer, Primary Key)
* title (String)
* description (Text)
* total\_capacity (Unsigned Integer)
* available\_slots (Unsigned Integer)
* start\_time (Timestamp)
* end\_time (Timestamp)

### Booking Entity

Must support booking registration , user reservation visibility , and explicit cancellation pipelines.

* id (Big Integer, Primary Key)
* user\_id (Foreign Key, Indexed)
* event\_id (Foreign Key, Indexed)
* status (Enum: pending, confirmed, cancelled)

## 4\. Quality Assurance & Performance Invariants

### Test-Driven Development (TDD) Boundaries

* Framework: Pest PHP.
* Target: 100% strict code coverage.
* Key Concurrency Test: The agent must write a multi-threaded feature test simulating race conditions (e.g., mocking 10 concurrent requests for an event with only 1 slot remaining) and assert that exactly 1 request succeeds while 9 fail cleanly.

### Resiliency Verification (k6)

* A load script tests/Performance/booking\_stress.js must be provided.
* Constraint: It must simulate high virtual users (VUs) executing heavy read loops on event lists alongside bursty mutations on the booking endpoint. The system must maintain a $0\\%$ data corruption rate (total active bookings must mathematically match the capacity constraints at all times).

