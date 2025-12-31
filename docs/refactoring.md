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

## Task 3 - Dynamic Interface Generation for Multiple Interfaces

**Status:** Complete

### Overview

Enabled MockEntityDoubleFactory to handle multiple interfaces that share a common
parent (e.g., both `FieldableEntityInterface` and `EntityChangedInterface` extend
`EntityInterface`), achieving full parity with ProphecyEntityDoubleFactory.

### Problem

PHPUnit's `createMockForIntersectionOfInterfaces()` fails when interfaces share
a common parent because they have duplicate method signatures. The previous
workaround filtered interfaces to keep only one per parent hierarchy, limiting
the PHPUnit adapter's capabilities.

### Solution

Generate a combined interface at runtime via `eval()` that extends all requested
interfaces:

```php
// Instead of: createMockForIntersectionOfInterfaces([A, B, C])
// Generate: interface Deuteros\Generated\CombinedInterface_abc123 extends A, B, C {}
// Then: createMock(CombinedInterface_abc123::class)
```

This works because PHP interfaces can extend multiple interfaces that share a
parent - there's no conflict since interfaces have no implementations.

### Changes

**Modified:**
- `src/PhpUnit/MockEntityDoubleFactory.php` - Added dynamic interface generation
- `src/Common/EntityDoubleFactory.php` - Simplified `resolveInterfaces()`
- `tests/Integration/PhpUnit/MockEntityDoubleFactoryTest.php` - Updated tests
- `tests/Integration/Prophecy/ProphecyEntityDoubleFactoryTest.php` - Updated docs
- `tests/Integration/BehavioralParityTest.php` - Added multi-interface parity test

### Architecture

```
MockEntityDoubleFactory
├── $combinedInterfaceCache (static)    # Caches generated interfaces
├── getOrCreateCombinedInterface()      # Gets/creates combined interface
├── declareCombinedInterface()          # Uses eval() to declare interface
└── createDoubleForInterfaces()         # Uses combined interface for multi

Generated namespace: Deuteros\Generated\CombinedInterface_<hash>
Hash: First 12 chars of MD5 of sorted interface names
```

### Benefits

1. **Full Parity**: PHPUnit and Prophecy adapters now have identical interface
   capabilities
2. **No Filtering**: Users can specify any combination of interfaces
3. **Cached**: Combined interfaces are cached statically to avoid redundant
   `eval()` calls
4. **Deterministic**: Same interface combination always produces the same
   generated interface name
