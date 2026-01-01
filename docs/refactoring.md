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
$factory = new \Deuteros\PhpUnit\MockEntityDoubleFactory::fromTest($this);
$factory = new \Deuteros\Prophecy\ProphecyEntityDoubleFactory::fromTest($this);
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

## Task 4 - Replace Array-Based API with EntityDefinitionBuilder

**Status:** Complete

### Overview

Replaced the array-based entity double creation API with a fluent builder
pattern, providing better IDE support, type safety, and discoverability.

### Problem

The array-based API required users to remember key names and had no IDE
autocompletion:

```php
// Old API - error-prone, no IDE support
$entity = $factory->create([
  'entity_type' => 'node',
  'bundle' => 'article',
  'fields' => ['field_title' => 'Test'],
  'interfaces' => [FieldableEntityInterface::class],
]);
```

### Solution

Fluent builder with auto-adding of `FieldableEntityInterface` when fields are
defined:

```php
// New API - discoverable, type-safe, IDE-friendly
$entity = $factory->create(
  EntityDefinitionBuilder::create('node')
    ->bundle('article')
    ->field('field_title', 'Test')  // Auto-adds FieldableEntityInterface
);
```

### Changes

**Created:**
- `src/Common/EntityDefinitionBuilder.php` - Fluent builder class
- `tests/Unit/Common/EntityDefinitionBuilderTest.php` - Builder unit tests

**Modified:**
- `src/Common/EntityDefinition.php`:
  - Added `withMutable()` method
  - Optimized `withContext()` to return same instance when empty
  - Removed `fromArray()` static method
- `src/Common/EntityDoubleFactoryInterface.php` - Changed to accept
  `EntityDefinition` instead of array
- `src/Common/EntityDoubleFactory.php`:
  - Updated `create()` and `createMutable()` signatures
  - Removed `normalizeDefinition()` method
  - Updated docblock examples
- `tests/Unit/Common/EntityDefinitionTest.php` - Removed `fromArray()` tests,
  added `withMutable()` tests
- `tests/Integration/EntityDoubleFactoryTestBase.php` - Converted to builder
- `tests/Integration/BehavioralParityTest.php` - Converted to builder

### API

```php
EntityDefinitionBuilder
├── create(string $entityType): self       # Start new definition
├── from(EntityDefinition $def): self      # Copy existing definition
├── bundle(string): self
├── id(mixed): self                        # Scalar or callable
├── uuid(mixed): self
├── label(mixed): self
├── field(string, mixed): self             # Auto-adds FieldableEntityInterface
├── fields(array): self                    # Bulk add
├── interface(class-string): self          # Deduplicated
├── interfaces(array): self                # Bulk add
├── methodOverride(string, mixed): self
├── methodOverrides(array): self           # Bulk add
├── context(string, mixed): self           # Single key
├── withContext(array): self               # Bulk add
└── build(): EntityDefinition
```

### Benefits

1. **IDE Support**: Full autocompletion for all builder methods
2. **Type Safety**: Method signatures enforce correct types
3. **Discoverability**: Users can explore API via method chaining
4. **Auto-Interface**: `field()` automatically adds `FieldableEntityInterface`
5. **Definition Reuse**: `from()` allows copying and modifying definitions
6. **Clean Factory API**: Factory accepts typed `EntityDefinition` instead of
   untyped array

## Task 5 - Unwrap Multi-Line Method Signatures

**Status:** Complete

### Overview

Unwrapped all method/function signatures that fit within 160 characters to a
single line for improved readability. Constructors are exempt as they use
property promotion.

### Changes

**Modified:**
- `CLAUDE.md` - Added formatting rule for 160-char signature limit
- `src/Common/EntityDoubleFactoryInterface.php` - Unwrapped 2 signatures
- `src/Common/EntityDoubleFactory.php` - Unwrapped 8 signatures
- `src/PhpUnit/MockEntityDoubleFactory.php` - Unwrapped 5 signatures
- `src/Prophecy/ProphecyEntityDoubleFactory.php` - Unwrapped 4 signatures

### Rule

Method and function signatures should be on a single line if they fit within
160 characters. Constructors are exempt because they typically use property
promotion and benefit from multi-line formatting for readability.

### Benefits

1. **Readability**: Signatures are easier to scan when on a single line
2. **Consistency**: All non-constructor signatures follow the same pattern
3. **Documented**: Rule added to CLAUDE.md for future contributions

## Task 6 - Add fromInterface() with Lenient Mode

**Status:** Complete

### Overview

Added a `fromInterface()` factory method to `EntityDefinitionBuilder` that uses
reflection to auto-detect the full interface hierarchy. Also added a lenient
mode that returns `null` for unconfigured methods instead of throwing.

### Problem

Users had to manually specify all interfaces in the hierarchy when creating
entity doubles for complex types like `NodeInterface`. This was error-prone
and required knowledge of Drupal's interface inheritance.

### Solution

The `fromInterface()` method uses PHP reflection to automatically detect and
add all interfaces in the hierarchy:

```php
// Before - manual interface specification
EntityDefinitionBuilder::create('node')
  ->interface(NodeInterface::class)
  ->interface(ContentEntityInterface::class)
  ->interface(EntityChangedInterface::class)
  ->interface(EntityOwnerInterface::class)
  ->interface(EntityPublishedInterface::class)
  ->interface(FieldableEntityInterface::class)
  // ... more interfaces
  ->build();

// After - automatic detection
EntityDefinitionBuilder::fromInterface('node', NodeInterface::class)
  ->bundle('article')
  ->build();
```

### Changes

**Modified:**
- `src/Common/EntityDefinition.php`:
  - Added `primaryInterface` property for improved error messages
  - Added `lenient` property for lenient mode
  - Added `getDeclaringInterface()` helper method
  - Updated `withContext()` and `withMutable()` to preserve new properties
- `src/Common/EntityDefinitionBuilder.php`:
  - Added `fromInterface(string $entityType, string $interface)` factory method
  - Added `lenient(bool $lenient = TRUE)` method
  - Updated `from()` to copy new properties
  - Updated `build()` to pass new properties
- `src/Common/GuardrailEnforcer.php`:
  - Added `getLenientDefault()` method
- `src/PhpUnit/MockEntityDoubleFactory.php`:
  - Updated `wireGuardrails()` to handle lenient mode
- `src/Prophecy/ProphecyEntityDoubleFactory.php`:
  - Updated `wireGuardrails()` to handle lenient mode
- `composer.json`:
  - Added autoload-dev entries for `Drupal\node` and `Drupal\user` namespaces

**Created:**
- `tests/Integration/NodeInterfaceTest.php` - Tests for NodeInterface hierarchy

**Test Files Updated:**
- `tests/Unit/Common/EntityDefinitionBuilderTest.php` - Added fromInterface tests
- `tests/Unit/Common/EntityDefinitionTest.php` - Added getDeclaringInterface tests
- `tests/Integration/EntityDoubleFactoryTestBase.php` - Added shared tests
- `tests/Integration/BehavioralParityTest.php` - Added parity tests

### API

```php
// Auto-detect interface hierarchy
EntityDefinitionBuilder::fromInterface('node', NodeInterface::class)
  ->bundle('article')
  ->id(42)
  ->methodOverride('getTitle', fn() => 'My Title')
  ->build();

// Lenient mode - unconfigured methods return null
EntityDefinitionBuilder::fromInterface('node', NodeInterface::class)
  ->bundle('article')
  ->lenient()
  ->build();

// In lenient mode:
$entity->save();       // Returns null instead of throwing
$entity->delete();     // Returns null instead of throwing
$entity->getTitle();   // Returns null (unconfigured method)
```

### Interface Hierarchy Detection

`fromInterface()` uses `ReflectionClass::getInterfaces()` to get all interfaces
in the hierarchy:

- Includes all parent interfaces (e.g., `ContentEntityInterface`,
  `FieldableEntityInterface`, `EntityInterface`)
- Keeps `Traversable` and `IteratorAggregate` for foreach support
- Validates that the interface exists and extends `EntityInterface`
- Stores the primary interface for improved error messages

### Lenient Mode Behavior

- Default: `lenient(false)` - throws for unconfigured methods (existing behavior)
- `lenient(true)`:
  - Unconfigured methods return `null`
  - Explicitly unsupported methods (`save`, `delete`, etc.) also return `null`
  - Identical behavior between PHPUnit and Prophecy adapters

### Benefits

1. **Zero Maintenance**: No need to manually track interface hierarchies
2. **Works for Any Interface**: Core, contrib, or custom entity types
3. **Minimal API Surface**: Single new method on existing builder
4. **Forwards Compatible**: New Drupal interfaces automatically supported
5. **Lenient Testing**: Exploratory testing without configuring every method

## Task 7 - Break Core Circular Dependency

**Status:** Complete

### Overview

Removed the hard dependency on `drupal/core` so that Drupal core itself can
depend on this package without creating a circular dependency. Implemented via
autoloaded interface stubs that define the required interfaces when Drupal core
is not available.

### Problem

The original `composer.json` had `drupal/core` in `require-dev`. If Drupal core
wanted to depend on Deuteros, this would create a circular dependency.

### Solution

1. **Interface Stubs**: Created `stubs/` directory with minimal interface
   definitions that mirror Drupal's interface hierarchy
2. **Conditional Loading**: Bootstrap file only loads stubs when the real
   Drupal interfaces are not available
3. **Dual Composer Configuration**:
   - `composer.json` - Production (uses stubs, minimal dependencies)
   - `composer.dev.json` - Development (includes drupal/core, phpcs, phpstan)

### Changes

**Created:**
- `stubs/bootstrap.php` - Conditional loader for stub interfaces
- `stubs/Drupal/Core/Entity/EntityInterface.php`
- `stubs/Drupal/Core/Entity/FieldableEntityInterface.php`
- `stubs/Drupal/Core/Entity/ContentEntityInterface.php`
- `stubs/Drupal/Core/Entity/EntityChangedInterface.php`
- `stubs/Drupal/Core/Entity/EntityPublishedInterface.php`
- `stubs/Drupal/Core/Config/Entity/ConfigEntityInterface.php`
- `stubs/Drupal/Core/Field/FieldItemInterface.php`
- `stubs/Drupal/Core/Field/FieldItemListInterface.php`
- `stubs/Drupal/node/NodeInterface.php`
- `stubs/Drupal/user/EntityOwnerInterface.php`
- `composer.dev.json` - Full development configuration

**Modified:**
- `composer.json` - Removed drupal/core, added stubs autoload
- `CLAUDE.md` - Updated build commands for dual composer setup

### Stub Interface Design

Stubs include method signatures (not just empty interfaces) because PHPUnit
and Prophecy require method definitions to create mocks. Each stub contains
only the methods actually used by Deuteros:

```php
// stubs/Drupal/Core/Entity/EntityInterface.php
interface EntityInterface {
  public function uuid();
  public function id();
  public function getEntityTypeId();
  public function bundle();
  public function label();
  public function save();
  public function delete();
  // ... other methods used by Deuteros
}
```

### Development Workflow

```bash
# Development setup (recommended)
COMPOSER=composer.dev.json composer install
composer phpcs
composer phpstan
composer test

# Production setup (verify stubs work)
composer install
composer test
```

### Benefits

1. **No Circular Dependency**: Drupal core can now depend on Deuteros
2. **Minimal Production Footprint**: No drupal/core in production dependencies
3. **Full Test Coverage**: Tests pass with both real interfaces and stubs
4. **Transparent**: Users with Drupal installed get real interfaces automatically

## Task 8 - Magic Accessor Support (__get/__set)

**Status:** Complete

### Overview

Added support for magic property access on entity doubles, allowing code like
`$entity->field_name` instead of `$entity->get('field_name')`. This matches
real Drupal entity behavior where fields can be accessed as properties.

### Problem

Entity doubles didn't support magic property access (`$entity->field_name`)
because:
1. `FieldableEntityInterface` doesn't declare `__get`/`__set` methods
2. PHPUnit's `createMock()` can only mock methods declared in the interface
3. The resolver existed in `EntityDoubleBuilder` but wasn't wired to entity mocks

### Solution

Used dynamic interface generation (via `eval()`) to create a runtime interface
that extends all requested interfaces AND declares `__get`/`__set` methods.
Moved this logic to the base `EntityDoubleFactory` class so both PHPUnit and
Prophecy adapters can use it.

### Changes

**Modified:**
- `src/Common/EntityDoubleFactory.php` - Added `getOrCreateRuntimeInterface()`
  and `declareRuntimeInterface()` methods
- `src/PhpUnit/MockEntityDoubleFactory.php` - Removed old combined interface
  code, use base class method, wire `__get`/`__set` resolvers
- `src/Prophecy/ProphecyEntityDoubleFactory.php` - Use runtime interface,
  wire `__get`/`__set` via `MethodProphecy`
- `tests/Integration/EntityDoubleFactoryTestBase.php` - Added magic accessor
  tests
- `tests/Integration/BehavioralParityTest.php` - Added parity tests

### New Behavior

```php
// Before: Only explicit get() worked
$title = $entity->get('field_title')->value;

// After: Magic property access also works
$title = $entity->field_title->value;

// Magic set for mutable entities
$entity = $factory->createMutable($definition);
$entity->field_status = 'published';  // Works!

// Immutable entities throw on magic set
$entity = $factory->create($definition);
$entity->field_status = 'published';  // Throws LogicException
```

### Architecture

The solution leverages the existing runtime interface generation pattern
(previously used only for multiple interface support) and extends it to also
declare magic methods:

```php
// Generated at runtime
interface RuntimeInterface_abc123 extends FieldableEntityInterface {
    public function __get(string $name): mixed;
    public function __set(string $name, mixed $value): void;
}
```

Both adapters now use this unified approach:
- PHPUnit: `$this->getOrCreateRuntimeInterface($interfaces)`
- Prophecy: `$this->getOrCreateRuntimeInterface($interfaces)`

### Benefits

1. **Matches Real Drupal Behavior**: Entity fields work as properties
2. **Full Parity**: Both PHPUnit and Prophecy adapters behave identically
3. **Consistent with Existing Patterns**: Reuses dynamic interface generation
4. **Immutability Preserved**: Immutable doubles throw on property assignment

## Task 9 - Add Correctness Assertions to BehavioralParityTest

**Status:** Complete

### Overview

Enhanced `BehavioralParityTest` to verify that returned values match the expected
values from entity/field definitions, not just that PHPUnit and Prophecy adapters
return the same values.

### Problem

Several test methods only verified parity (PHPUnit result == Prophecy result)
without verifying correctness (result == expected definition value). This meant
both adapters could return the same *wrong* value and the test would still pass.

### Solution

Added explicit correctness assertions that verify returned values match the
expected values from `EntityDefinition` and `FieldDefinition`. Since verifying
both adapters return the expected value implicitly verifies parity by transitivity,
redundant parity checks were removed.

### Changes

**Modified:**
- `tests/Integration/BehavioralParityTest.php`:
  - `testMetadataParity()` - Verify entity type, bundle, id, uuid, label
  - `testFieldValueParity()` - Verify field_text, field_number, field_ref
  - `testCallbackResolutionParity()` - Added comment, already had correctness
  - `testMultiValueFieldParity()` - Verify first(), get(0/1/2), get(99)
  - `testMethodOverrideParity()` - Added comment, already had correctness
  - `testMultiInterfaceParity()` - Verify field_text and getChangedTime
  - `testFromInterfaceParity()` - Verify entity type, bundle, id, field_test
  - `testMagicGetParity()` - Verify field_text and field_ref
  - `testMagicSetMutableParity()` - Removed redundant parity check
- `phpstan-baseline.neon` - Updated error occurrence counts

### Pattern

Before (only parity):
```php
$this->assertSame(
  $mock->get('field_text')->value,
  $prophecy->get('field_text')->value
);
```

After (correctness, which implies parity):
```php
// Verify correctness against definition values.
$this->assertSame('Test Value', $mock->get('field_text')->value);
$this->assertSame('Test Value', $prophecy->get('field_text')->value);
```

### Benefits

1. **Catches Bugs**: Will detect if both adapters return the same wrong value
2. **Clearer Intent**: Tests document what the expected values should be
3. **Simpler**: Removed redundant parity assertions (transitivity)
4. **Complete Coverage**: All 10 test methods now verify correctness

## Task 10 - Performance Benchmarking

**Status:** Complete

### Overview

Added performance benchmark tests comparing Deuteros entity doubles with Drupal
Kernel tests. The benchmarks measure the overhead of entity creation and field
operations across three implementations: PHPUnit mocks, Prophecy doubles, and
Drupal Kernel tests.

### Architecture

```
tests/Performance/
├── NodeOperationsBenchmarkTrait.php   # Shared benchmark logic
├── PhpUnitNodeBenchmarkTest.php       # PHPUnit mock benchmarks
├── ProphecyNodeBenchmarkTest.php      # Prophecy double benchmarks
└── KernelNodeBenchmarkTest.php        # Drupal Kernel test benchmarks
```

### Benchmark Design

The benchmark uses a data provider with configurable iterations (default: 100)
to run identical operations on each test implementation:

**Operations Measured:**
- Entity creation (per iteration)
- Metadata access: `id()`, `uuid()`, `bundle()`, `label()`, `getEntityTypeId()`
- Node-specific methods: `getTitle()`, `isPublished()`, `getCreatedTime()`,
  `isPromoted()`, `isSticky()`, `getOwnerId()`
- Field access: `get()`, `->value`, `getValue()`, `hasField()`
- Multi-value fields: `first()`, `get(index)`, `isEmpty()`
- Entity references: `->target_id`

### Changes

**Created:**
- `tests/Performance/NodeOperationsBenchmarkTrait.php` - Trait with configurable
  `ITERATION_COUNT`, data provider, and `performNodeOperations()` method
- `tests/Performance/PhpUnitNodeBenchmarkTest.php` - Uses `MockEntityDoubleFactory`
- `tests/Performance/ProphecyNodeBenchmarkTest.php` - Uses `ProphecyEntityDoubleFactory`
- `tests/Performance/KernelNodeBenchmarkTest.php` - Uses `Node::create()` (no save)

### Kernel Test Compatibility

The Kernel test is conditionally defined based on whether
`EntityKernelTestBase` is available:

```php
if (!class_exists(EntityKernelTestBase::class)) {
  // Define placeholder that skips when Drupal core unavailable
  class KernelNodeBenchmarkTest extends TestCase {
    public function testSkipped(): void {
      $this->markTestSkipped('Drupal core is not available.');
    }
  }
  return;
}

// Real test class when Drupal core is available
class KernelNodeBenchmarkTest extends EntityKernelTestBase { ... }
```

This allows:
- Production composer (stubs only): Kernel test is skipped
- Dev composer (Drupal core): Full Kernel test runs

### Running Benchmarks

```bash
# Deuteros tests only (works with production composer)
./vendor/bin/phpunit tests/Performance/PhpUnitNodeBenchmarkTest.php
./vendor/bin/phpunit tests/Performance/ProphecyNodeBenchmarkTest.php

# All tests including Kernel (requires dev composer)
COMPOSER=composer.dev.json composer install
./vendor/bin/phpunit tests/Performance/
```

### Benefits

1. **Quantifiable Gains**: Demonstrates Deuteros performance advantage
2. **Adapter Comparison**: PHPUnit vs Prophecy performance difference
3. **Portable**: Works with production composer (Kernel test skipped gracefully)
4. **Configurable**: `ITERATION_COUNT` constant allows tuning benchmark intensity

## Task 11 - Rename Definition Classes to Include "Double"

**Status:** Complete

### Overview

Renamed entity and field definition classes to include "Double" in their names
to avoid confusion with Drupal core field definitions. This makes it clear that
these classes define test doubles, not actual Drupal entities or fields.

### Changes

**Renamed Classes:**
- `EntityDefinition` → `EntityDoubleDefinition`
- `FieldDefinition` → `FieldDoubleDefinition`
- `EntityDefinitionBuilder` → `EntityDoubleDefinitionBuilder`

**Renamed Files:**
- `src/Common/EntityDefinition.php` → `src/Common/EntityDoubleDefinition.php`
- `src/Common/FieldDefinition.php` → `src/Common/FieldDoubleDefinition.php`
- `src/Common/EntityDefinitionBuilder.php` → `src/Common/EntityDoubleDefinitionBuilder.php`
- `tests/Unit/Common/EntityDefinitionTest.php` → `tests/Unit/Common/EntityDoubleDefinitionTest.php`
- `tests/Unit/Common/FieldDefinitionTest.php` → `tests/Unit/Common/FieldDoubleDefinitionTest.php`
- `tests/Unit/Common/EntityDefinitionBuilderTest.php` → `tests/Unit/Common/EntityDoubleDefinitionBuilderTest.php`

**Updated References:**
- All source files in `src/Common/`, `src/PhpUnit/`, `src/Prophecy/`
- All test files in `tests/Unit/`, `tests/Integration/`, `tests/Performance/`
- PHPStan baseline file (`phpstan-baseline.neon`)
- CLAUDE.md architecture documentation

**Additional Cleanup:**
- Standardized parameter naming: `$definition` instead of `$entityDefinition`
  in `wireFieldListResolvers()` methods
- Made `FieldItemListDoubleBuilder::$definition` property mutable (removed
  readonly) to allow updates when field values change via `setValue()`
- Renamed test variables to use "Double" terminology (e.g., `$fieldDoubleDefinition`)

### New API

```php
// Before
use Deuteros\Common\EntityDefinition;
use Deuteros\Common\FieldDefinition;
use Deuteros\Common\EntityDefinitionBuilder;

$definition = EntityDefinitionBuilder::create('node')
  ->field('title', new FieldDefinition('Test'))
  ->build();
$factory->create($definition);

// After
use Deuteros\Common\EntityDoubleDefinition;
use Deuteros\Common\FieldDoubleDefinition;
use Deuteros\Common\EntityDoubleDefinitionBuilder;

$definition = EntityDoubleDefinitionBuilder::create('node')
  ->field('title', new FieldDoubleDefinition('Test'))
  ->build();
$factory->create($definition);
```

### Benefits

1. **Avoids Confusion**: Clear distinction from Drupal core's `FieldDefinition`
   and related classes
2. **Consistent Naming**: Aligns with existing "Double" terminology used
   throughout the codebase (e.g., `EntityDoubleBuilder`, `FieldItemDoubleBuilder`)
3. **Self-Documenting**: Class names immediately convey their purpose
