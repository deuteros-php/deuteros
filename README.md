# DEUTEROS

**Drupal Entity Unit Test Extensible Replacement Object Scaffolding**

A PHP library providing value-object entity doubles for Drupal unit testing, allowing you to test code that depends on entity/field interfaces without Kernel tests, module enablement, database access, or service container.

## Installation

```bash
composer require --dev plach79/deuteros
```

## Documentation

See [docs/](docs/) for full documentation:

- [Usage Guide](docs/USAGE.md) - Getting started, API reference, and examples
- [Architecture](docs/ARCHITECTURE.md) - For contributors and maintainers

## Requirements

- PHP 8.3+
- Drupal 10.x or 11.x
- PHPUnit 9.0+/10.0+/11.0+ or Prophecy 1.15+

## License

MIT
