# Refactoring

This amends the [implementation plan](plan.md) by describing tweaks to the final
architecture.

## Task 1 - Convert Traits to Factories

**Status:** Complete

### Overview

Converted the trait-based architecture to factory classes for better testability,
dependency injection, and separation of concerns.

### Changes

**Deleted:**
- `src/Mock/EntityDoubleTrait.php`
- `src/Prophecy/EntityDoubleTrait.php`
- `src/Common/EntityDefinitionNormalizerTrait.php`

**Created:**
- `src/Common/EntityDoubleFactory.php` - Abstract base with shared logic
- `src/PhpUnit/MockEntityDoubleFactory.php` - PHPUnit adapter
- `src/Prophecy/ProphecyEntityDoubleFactory.php` - Prophecy adapter

### New API

```php
use Deuteros\Common\EntityDoubleFactory;

// Unified DX - auto-detects Prophecy vs PHPUnit based on test traits
$factory = EntityDoubleFactory::fromTest($this);
$entity = $factory->create([
  'entity_type' => 'node',
  'bundle' => 'article',
  'fields' => ['field_title' => 'Test'],
  'interfaces' => [FieldableEntityInterface::class],
]);

// Or explicit instantiation
$factory = new \Deuteros\PhpUnit\MockEntityDoubleFactory($this);
$factory = new \Deuteros\Prophecy\ProphecyEntityDoubleFactory($this->getProphet());
```

### Architecture

```
EntityDoubleFactory (abstract)
├── fromTest(TestCase): static     # Auto-detects mocking framework
├── create(array, array): EntityInterface
├── createMutable(array, array): EntityInterface
└── Abstract methods for framework-specific behavior

MockEntityDoubleFactory extends EntityDoubleFactory
└── Uses PHPUnit's createMock() via reflection

ProphecyEntityDoubleFactory extends EntityDoubleFactory
└── Uses Prophet::prophesize() with willImplement()
```

### Benefits

1. **DRY**: Shared logic consolidated in `EntityDoubleFactory`
2. **Unified DX**: `fromTest()` auto-detects mocking framework
3. **Clear Separation**: Framework-specific code isolated in concrete factories
4. **Explicit Dependencies**: Factory receives `TestCase` or `Prophet` via constructor
5. **Testable**: Factories can be tested independently of test case classes
