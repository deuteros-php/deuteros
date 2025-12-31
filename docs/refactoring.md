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

## Task 2 - Extract Base Test Class with Factory Interface

**Status:** Complete

### Overview

Extracted shared test code into a base test class using a factory interface,
eliminating ~400 lines of duplicated test code while maintaining full coverage.

### Changes

**Created:**
- `src/Common/EntityDoubleFactoryInterface.php` - Public API contract
- `tests/Integration/EntityDoubleFactoryTestBase.php` - Shared test methods

**Modified:**
- `src/Common/EntityDoubleFactory.php` - Now implements the interface
- `tests/Integration/PhpUnit/MockEntityDoubleFactoryTest.php` - Extends base
- `tests/Integration/Prophecy/ProphecyEntityDoubleFactoryTest.php` - Extends base

### Architecture

```
EntityDoubleFactoryInterface
├── create(array, array): EntityInterface
└── createMutable(array, array): EntityInterface

EntityDoubleFactory implements EntityDoubleFactoryInterface
├── MockEntityDoubleFactory
└── ProphecyEntityDoubleFactory

EntityDoubleFactoryTestBase (abstract)
├── abstract createFactory(): EntityDoubleFactoryInterface
├── 22 shared test methods
└── Concrete classes implement createFactory() + unique tests
```

### Benefits

1. **DRY Tests**: 22 shared tests in base class, ~400 lines eliminated
2. **Type Safety**: Interface enables implementation-agnostic test code
3. **Extensibility**: New factory implementations only need to implement interface
4. **Clear Contract**: Public API documented via interface docblocks
