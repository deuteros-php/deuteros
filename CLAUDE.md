# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**DEUTEROS** (Drupal Entity Unit Test Extensible Replacement Object Scaffolding) is a PHP library providing value-object entity doubles for Drupal unit testing. It allows testing code that depends on entity/field interfaces without Kernel tests, module enablement, database access, or service container.

- **Composer package:** `plach79/deuteros`
- **Root namespace:** `\Deuteros`
- **PHP version:** 8.3+
- **Drupal compatibility:** 10.x, 11.x
- **Test frameworks:** PHPUnit 9.0+/10.0+/11.0+, Prophecy 1.15+

## Build & Test Commands

This project uses two Composer configurations:
- `composer.dev.json` - Development (includes Drupal core, phpcs, phpstan)
- `composer.json` - Production (uses interface stubs, minimal dependencies)

```bash
# Development setup (recommended for working on the package)
COMPOSER=composer.dev.json composer install

# Run tests
composer test                 # Run all tests (alias for phpunit)
./vendor/bin/phpunit          # Run all tests directly
./vendor/bin/phpunit tests/Unit        # Unit tests only
./vendor/bin/phpunit tests/Integration # Integration tests only
./vendor/bin/phpunit --filter TestName # Run specific test by name

# Production setup (for testing stub compatibility)
composer install              # Uses stubs instead of Drupal core
```

## Coding Standards

The codebase follows Drupal coding standards (Drupal + DrupalPractice sniffs).

```bash
# Requires development setup (COMPOSER=composer.dev.json composer install)
composer phpcs                # Check coding standards
composer phpcbf               # Auto-fix coding standard violations
```

Key requirements enforced by phpcs:
- 2-space indentation
- Opening braces on same line as class/function declarations
- Line length max 80 characters
- `@return` descriptions required in docblocks
- Parentheses required for anonymous class constructors (`new class ()`)
- No empty doc comments

Additional formatting rules:
- Method/function signatures should be on a single line if ≤160 characters
- Constructors are exempt (they use property promotion and can span multiple lines)

## Static Analysis

PHPStan is configured at level 10 (max) with a baseline for existing issues.

```bash
# Requires development setup (COMPOSER=composer.dev.json composer install)
composer phpstan             # Run static analysis
```

Configuration files:
- `phpstan.neon` - Main configuration
- `phpstan-baseline.neon` - Baseline with ignored errors (to be reviewed/fixed)

## Architecture

### Layer Structure

1. **Definition Layer** (`Deuteros\Common\EntityDoubleDefinition`, `FieldDoubleDefinition`)
   - Immutable value objects storing entity double metadata and field values
   - Pure PHP, no Drupal dependencies

2. **Core Resolution Layer** (`Deuteros\Common\*DoubleBuilder`)
   - `EntityDoubleBuilder` - Resolvers for entity methods (id, uuid, bundle, etc.)
   - `FieldItemListDoubleBuilder` - Resolvers for field lists (first, get, getValue)
   - `FieldItemDoubleBuilder` - Resolvers for field items
   - Framework-agnostic: no PHPUnit/Prophecy references

3. **Shared Support**
   - `MutableStateContainer` - Stateful storage for mutable field values
   - `GuardrailEnforcer` - Centralized exception throwing for unsupported methods

4. **Factory Classes**
   - `Deuteros\Common\EntityDoubleFactory` - Abstract base with `fromTest()` factory
   - `Deuteros\PhpUnit\MockEntityDoubleFactory` - PHPUnit native mocks
   - `Deuteros\Prophecy\ProphecyEntityDoubleFactory` - Prophecy doubles

### Key Patterns

**Resolver Pattern:** All builders produce `callable` resolvers with signature:
```php
fn(array $context, ...$args): mixed
```

**Method Resolution Order:**
1. `methodOverrides` (highest precedence)
2. Core resolvers from builders
3. Guardrail failure (throws with differentiated message)

**Field List Caching:** `$entity->field_name` always returns the same `FieldItemListInterface` double per entity instance.

**Immutable vs Mutable:**
- Immutable doubles (default): Throw on field mutation
- Mutable doubles: Track changes in `MutableStateContainer` for assertions
- Metadata (id, uuid, entityType, bundle) always immutable

## PHP 8.3 Features Used

The codebase leverages modern PHP features:

- **Readonly classes** (PHP 8.2): `EntityDoubleDefinition`, `FieldDoubleDefinition` are `final readonly class`
- **Constructor property promotion**: Used throughout for cleaner constructors
- **Typed class constants** (PHP 8.3): `GuardrailEnforcer::UNSUPPORTED_METHODS` uses `const array`
- **Match expressions**: Used in `resolveValue()` and `normalizeToArray()` methods
- **Readonly properties**: Builder classes use `private readonly` for immutable dependencies

## Non-Negotiable Constraints

These constraints must never be violated:

- **No concrete Drupal classes** - Interfaces only
- **No service container access**
- **No database access**
- **Entities are value objects** - Read-only unless explicitly mutable
- **Unsupported operations fail loudly** with differentiated error messages
- **PHPUnit and Prophecy adapters must behave identically**
- **Use term "Double"** everywhere except when referring to PHPUnit mock objects
- **All code must pass `composer phpcs`** - Run before completing any code change
- **All code must pass `composer phpstan`** - Run before completing any code change

## Test Structure

- `tests/Unit/Common/` - Unit tests for definition and support classes
- `tests/Integration/PhpUnit/` - PHPUnit factory integration tests
- `tests/Integration/Prophecy/` - Prophecy factory integration tests
- `tests/Integration/BehavioralParityTest.php` - Ensures both adapters produce identical behavior

## Directory Layout

After running `COMPOSER=composer.dev.json composer install`:
- `stubs/` contains interface stubs (used when Drupal core is not available)
- `vendor` contains all Composer dependencies
- `web/core` contains Drupal core (only in development mode)

## Documentation

- `docs/init.md` - Implementation requirements and constraints
- `docs/plan.md` - Detailed implementation plan (single source of truth for scope/architecture)
- `docs/refactoring.md` - Detailed implementation plan of changes performed
  after the initial implementation (source of truth updates)
- `docs/todo.md` - To Do list
