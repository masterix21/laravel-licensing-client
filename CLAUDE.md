# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel client package that consumes the features of `masterix21/laravel-licensing` server package. It acts as a client to validate and manage licenses in Laravel applications by communicating with a licensing server. The package uses the Spatie Laravel Package Tools for structure and follows standard Laravel package conventions.

## Development Commands

### Code Quality
```bash
composer analyse    # Run PHPStan static analysis
composer format     # Format code with Laravel Pint
```

### Testing
```bash
composer test              # Run all tests with Pest
composer test-coverage     # Run tests with coverage report
vendor/bin/pest --filter TestName  # Run a specific test
```

### Package Development
```bash
composer install           # Install dependencies
composer prepare          # Discover package (runs after autoload-dump)
```

## Architecture

The package follows Laravel package conventions as a client for the licensing server:
- Service Provider: `LaravelLicensingClientServiceProvider` - configures the package using Spatie Package Tools
- Main Class: `LaravelLicensingClient` - client implementation for communicating with licensing server
- Facade: `LaravelLicensingClient` - provides static interface for license validation
- Command: `LaravelLicensingClientCommand` - Artisan command for license management tasks
- Configuration: `config/licensing-client.php` - client configuration (server URL, API keys, etc.)
- Migration: `create_laravel_licensing_client_table` stub - stores local license cache/state

## Testing Environment

- Uses Pest PHP testing framework with Laravel plugin
- Orchestra Testbench for package testing in Laravel context
- Tests extend `LucaLongo\LaravelLicensingClient\Tests\TestCase`
- PHPUnit configuration in `phpunit.xml.dist`

## Code Standards

- PHP 8.2+ required
- PSR-4 autoloading under `LucaLongo\LaravelLicensingClient` namespace
- Laravel Pint for code formatting (PSR-12 based)
- PHPStan with Larastan for static analysis
- Follow Laravel conventions for naming and structure
- ## Conventions
- You must follow all existing code conventions used in this package and in `masterix21/laravel-licensing`. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.
- Any reference about `masterix21/laravel-licesing` can be found browsing here:
- https://github.com/masterix21/laravel-licensing/blob/main/AI_GUIDELINES.md
- https://github.com/masterix21/laravel-licensing/blob/main/docs/CLIENT_IMPLEMENTATION_GUIDE.md
- Use early return
- DRY