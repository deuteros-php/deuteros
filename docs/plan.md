# DEUTEROS

**Drupal Entity Unit Test Extensible Replacement Object Scaffolding**

* **Root namespace:** `\Deuteros`
* **Scope:** Unit-test–only, value-object entity doubles
* **Supported APIs:** PHPUnit (native mocks), Prophecy

---

## PHASE 0 — MANDATORY GUARDRAILS

Before any code is written, the following constraints must be respected:

* No concrete Drupal entity or field classes
* No service container usage
* No database access
* Interfaces only:
    * `Drupal\Core\Entity\EntityInterface`
    * `Drupal\Core\Entity\FieldableEntityInterface`
    * `Drupal\Core\Field\FieldItemListInterface`
    * `Drupal\Core\Field\FieldItemInterface`
    * This must support interface-level extensibility, not behavioral
      extensibility. That means the code can:
        * produce an entity double that implements additional interfaces
        * supply resolvers for methods introduced by those interfaces
    * This does not implement default behavior for the specified interfaces
* Entities are **value objects**, optionally mutable for field values only
    * Immutable doubles (default): field values cannot be changed after creation
    * Mutable doubles: field values can be updated via `set()` methods for
      assertion purposes
* Unsupported behavior must **fail loudly**
    * Specifically, missing resolvers for invoked methods must fail loudly
    * Error messages must be **differentiated by failure mode** (see Phase 6)
* PHPUnit and Prophecy adapters must exhibit **identical behavior**

Any deviation invalidates the implementation.

---

## PHASE 1 — DEFINITION LAYER (PURE PHP)

### Task 1.1 — `EntityDefinition`

**File**
```
src/Deuteros/Common/EntityDefinition.php
```

**Namespace**
```php
namespace Deuteros\Common;
```

**Responsibilities**
* Immutable value object
* Stores:
    * `entityType` (`string`)
    * `bundle` (`string`)
    * `id` (`mixed|null`)
    * `uuid` (`mixed|null`)
    * `label` (`mixed|null`)
    * `fields` (`array<string, FieldDefinition>`)
    * `interfaces` (`string[]`)
      * `EntityInterface` is always be implemented, regardless of its
        appearance in the `interfaces` property
    * `methodOverrides` (`array<string, callable|mixed>`)
        * keyed by method name
        * applies to any interface method
    * `context` (`array<string, mixed>`)

**Method resolution order**
1. `methodOverrides`
2. Core entity/field resolvers
3. Guardrail failure

This allows support for core entity interface methods (e.g. `getOwnerId()`) as
well as custom methods from custom interfaces without DEUTEROS knowing what
they mean.

**Constructor pattern**
* Use PHP 8.0+ named parameters for type safety
* Provide static `fromArray()` factory method for trait usage

```php
// Direct instantiation
new EntityDefinition(
  entityType: 'node',
  bundle: 'article',
  id: 1,
  fields: [...],
);

// Factory method (used by traits)
EntityDefinition::fromArray([
  'entity_type' => 'node',
  'bundle' => 'article',
  ...
]);
```

**Rules**
* Constructor normalizes missing values
* No logic beyond data storage
* No Drupal runtime dependencies
* Regarding interfaces:
    * No interface inheritance validation
    * No auto-adding of parent interfaces
    * The caller is responsible for correctness
* The `fields` property is only valid if `FieldableEntityInterface` is listed
  in the `interfaces` property, an exception is thrown otherwise

**Interface hierarchy warning**
```php
// ❌ WRONG - will fail at runtime if FieldableEntityInterface methods are called
'interfaces' => [ContentEntityInterface::class]

// ✓ CORRECT - full hierarchy declared
'interfaces' => [
  FieldableEntityInterface::class,
  ContentEntityInterface::class,
]
```

### Task 1.2 — `FieldDefinition`

**File**
```
src/Deuteros/Common/FieldDefinition.php
```

**Namespace**
```php
namespace Deuteros\Common;
```

**Responsibilities**
* Stores:
    * `value` (scalar | array | callable)
* No behavior beyond holding the value

---

## PHASE 2 — CORE RESOLUTION LOGIC (FRAMEWORK-AGNOSTIC)

### Task 2.1 — EntityDoubleBuilder

**File**
```
src/Deuteros/Common/EntityDoubleBuilder.php
```

**Namespace**
```
namespace Deuteros\Common;
```

**Responsibilities**
* Accepts an `EntityDefinition`
* Produces callable resolvers for:
    * `id()`
    * `uuid()`
    * `label()`
    * `bundle()`
    * `getEntityTypeId()`
    * `hasField(string $name)`
    * `get(string $name)`
    * `__get(string $name)`

**Rules**
* Resolver signature:
  ```php
  fn (array $context, ...$args): mixed
  ```
* `methodOverrides` take precedence
* Unknown methods are not handled here
* No PHPUnit or Prophecy references

### Task 2.2 — `FieldItemListDoubleBuilder`

**File**
```
src/Deuteros/Common/FieldItemListDoubleBuilder.php
```

**Namespace**
```php
namespace Deuteros\Common;
```

**Responsibilities**
* Accepts:
    * `FieldDefinition`
    * Context array
* Produces callable resolvers for:
    * `first()` → returns `FieldItemInterface` double
    * `isEmpty()` → returns `bool`
    * `getValue()` → returns underlying value
    * `get(int $delta)` → returns `FieldItemInterface` double at delta
    * `__get('value')` → proxies to `first()->value`
    * `__get('target_id')` → proxies to `first()->target_id` (entity references)

**Rules**
* Scalar → single-value list (delta 0 only)
* Array → multi-value list (delta 0..n-1)
* Callables resolved lazily, **cached per entity double instance**
    * `$entity->field_test` always returns the same `FieldItemList` double
    * Resolves once per field access, cached within that access chain
* Delta-aware resolution via explicit `get(int $delta)` method
* No `ArrayAccess` implementation
* No `Iterator`/`IteratorAggregate` support

**Field access chain**
```php
$entity->field_test->value
// Resolves as:
// 1. $entity->__get('field_test') → "FieldItemListInterface" double
// 2. FieldItemList->__get('value') → proxies to "first()->value"
// 3. FieldItem->__get('value') → returns scalar
```

### Task 2.3 — Mutability Support

**Responsibilities**
* Provide stateful storage for mutable entity doubles
* Track field value changes for later assertion

**Mutable doubles support these additional methods:**

For `FieldableEntityInterface`:
* `set(string $field_name, $value, bool $notify = TRUE)` → updates internal
  state

For `FieldItemListInterface`:
* `setValue($values, bool $notify = TRUE)` → updates list value
* `__set('value', $value)` → proxies to `setValue()`

For `FieldItemInterface`:
* `setValue($value, bool $notify = TRUE)` → updates item value
* `__set('value', $value)` → proxies to `setValue()`

**Rules**
* Mutable state is stored in a separate state container, not in
  `EntityDefinition`
* Getters read from mutable state first, falling back to definition
* Only field values are mutable; entity metadata (`id`, `uuid`, `entityType`,
  `bundle`) remains immutable
* The `$notify` parameter is accepted but ignored (no event system)
* Immutable doubles throw on any setter invocation:
  ```php
  throw new \LogicException(
    "Cannot modify field '{$field_name}' on immutable entity double. "
    . "Use createMutableEntityDouble() if you need to test mutations."
  );
  ```

---

## PHASE 3 — SHARED BASE TRAIT (NORMALIZATION ONLY)

### Task 3.1 — `EntityDefinitionNormalizer`

**File**
```
src/Deuteros/Common/EntityDefinitionNormalizer.php
```

**Namespace**
```php
namespace Deuteros\Common;
```

**Responsibilities**
* Normalizes user input arrays into `EntityDefinition`
* Converts field arrays into `FieldDefinition`
* Merges provided context into the definition

**Rules**
* No doubles created here
* No PHPUnit or Prophecy usage
* No Drupal dependencies

---

## PHASE 4 — PHPUNIT ADAPTER (NATIVE MOCK OBJECTS)

### Task 4.1 — PhpUnit `EntityDoubleTrait`

**File**
```
src/Deuteros/Mock/EntityDoubleTrait.php
```

**Namespace**
```
namespace Deuteros\Mock;
```

**Public API**
```php
// Immutable (default) - field values cannot change after creation
protected function createEntityDouble(
  array $definition,
  array $context = []
): Drupal\Core\Entity\EntityInterface;

// Mutable - field values can be updated for assertion purposes
protected function createMutableEntityDouble(
  array $definition,
  array $context = []
): Drupal\Core\Entity\EntityInterface;
```

**Responsibilities**
* Create a PHPUnit native mock object implementing:
    * `EntityInterface`
    * Plus all interfaces listed in `EntityDefinition::interfaces`
* Binds builder-provided resolvers using `willReturnCallback()`
* Ensures `__get()` proxies to `get()`
* Explicitly stubs unsupported methods to throw (see Phase 6)

**Implementation notes**
* Use PHPUnit's ability to mock multiple interfaces
* Do not introduce abstract base classes
* Do not instantiate Drupal concrete types

---

## PHASE 5 — PROPHECY ADAPTER

### Task 5.1 — Prophecy `EntityDoubleTrait`

**File**
```
src/Deuteros/Prophecy/EntityDoubleTrait.php
```

**Namespace**
```php
namespace Deuteros\Prophecy;
```

**Public API**
```php
// Immutable (default) - field values cannot change after creation
protected function createEntityDouble(
  array $definition,
  array $context = []
): Drupal\Core\Entity\EntityInterface;

// Mutable - field values can be updated for assertion purposes
protected function createMutableEntityDouble(
  array $definition,
  array $context = []
): Drupal\Core\Entity\EntityInterface;
```

**Responsibilities**
* Mirrors PHPUnit behavior exactly
    * The prophecy must target all declared interfaces
    * The revealed object must implement all of them
    * No behavior changes, only interface surface expansion.
* Uses:
    * `$this->prophesize()`
    * `will(fn ($args) => …)`
* Reuses all core builders and definitions
* Calls `reveal()` before returning

**Critical rule**
Behavioral parity with PHPUnit is mandatory

---

## PHASE 6 — GUARDRAIL ENFORCEMENT

### Task 6.1 — Unsupported Methods

For both traits, explicitly stub and throw on:
* `save()`
* `delete()`
* `access()`
* `getTranslation()`
* `toUrl()`
* entity reference traversal methods

Do not rely on default mock behavior:
* If a method from any declared interface is called and:
    * it is not covered by `methodOverrides`
    * and it is not part of the core entity/field surface
      → throw immediately
* Field interfaces are only implemented if `FieldableEntityInterface` is listed
  in `EntityDefinition::interfaces`

### Task 6.2 — Differentiated Error Messages

Use distinct exception messages for different failure modes:

**Missing resolver (method from declared interface, no override provided)**
```php
throw new \LogicException(sprintf(
  "Method '%s' on interface '%s' requires a resolver in methodOverrides. "
  . "Add '%s' => callable to your entity double definition.",
  $method,
  $interface,
  $method
));
```

**Explicitly unsupported (write operations, entity storage, services)**
```php
throw new \LogicException(sprintf(
  "Method '%s' is not supported. This entity double is a unit-test value object. "
  . "Use a Kernel test for this behavior.",
  $method
));
```

**Critical rule**
Extensibility must **not weaken guardrails**.

---

## PHASE 7 — DOCUMENTATION

### Task 7.1 — Trait-Level Documentation

Each public trait must document:

* Purpose of DEUTEROS (Drupal Entity Unit Test Extensible Replacement Object Scaffolding)
* Supported behaviors
* Explicitly unsupported behaviors
* Unit-test-only warning
* Examples using:
    * Static values
    * Callback-based resolution

### Task 7.2 — Common Interface Reference

Document required `methodOverrides` for common interfaces:

| Interface | Required methodOverrides |
|-----------|-------------------------|
| `EntityOwnerInterface` | `getOwnerId()`, `getOwner()`, `setOwnerId()`, `setOwner()` |
| `EntityChangedInterface` | `getChangedTime()`, `setChangedTime()` |
| `EntityPublishedInterface` | `isPublished()`, `setPublished()`, `setUnpublished()` |
| `RevisionLogInterface` | `getRevisionCreationTime()`, `setRevisionCreationTime()`, `getRevisionUser()`, `setRevisionUser()`, `getRevisionUserId()`, `setRevisionUserId()`, `getRevisionLogMessage()`, `setRevisionLogMessage()` |
| `ContentEntityInterface` | (inherits from above as needed) |

### Task 7.3 — Usage Examples

**Immutable double (default)**
```php
$entity = $this->createEntityDouble(
  [
    'entity_type' => 'node',
    'bundle' => 'article',
    'fields' => [
      'field_test' => fn ($context) => $context['test'],
      'field_tags' => [
        ['target_id' => 1],
        ['target_id' => 2],
        ['target_id' => 3],
      ],
    ],
    'interfaces' => [
      FieldableEntityInterface::class,
      ContentEntityInterface::class,
      EntityChangedInterface::class,
    ],
    'methodOverrides' => [
      'getChangedTime' => fn ($context) => $context['changed'],
      'setChangedTime' => fn () => throw new \LogicException('Read-only'),
      'isPublished' => fn () => TRUE,
    ],
  ],
  [
    'test' => 'Dynamic',
    'changed' => $time = time() - 1,
  ],
);

// Field access
$this->assertSame('Dynamic', $entity->field_test->value);
$this->assertSame($time, $entity->getChangedTime());

// Multi-value delta access
$this->assertSame(1, $entity->field_tags->first()->target_id);
$this->assertSame(2, $entity->field_tags->get(1)->target_id);
```

**Mutable double (for testing code that modifies entities)**
```php
$entity = $this->createMutableEntityDouble(
  [
    'entity_type' => 'node',
    'bundle' => 'article',
    'fields' => [
      'field_status' => 'draft',
      'field_reviewer' => NULL,
    ],
    'interfaces' => [
      FieldableEntityInterface::class,
    ],
  ],
);

// Initial state.
$this->assertSame('draft', $entity->field_status->value);
$this->assertNull($entity->field_reviewer->value);

// Call the method under test (which modifies the entity).
$this->workflowService->submitForReview($entity, $reviewer);

// Assert mutations occurred.
$this->assertSame('pending_review', $entity->field_status->value);
$this->assertSame($reviewer->id(), $entity->field_reviewer->target_id);
```

---

## PHASE 8 — VALIDATION TESTS

### Task 8.1 — Self-Tests

* Create unit tests providing full coverage for all individual classes in the
`\Deuteros` namespace.

* Create end-to-end tests extending `Drupal\Tests\UnitTestCase` validating:
  * Scalar field access
  * Callback-based field resolution
  * Context propagation through field callables
  * Multi-value field delta access via `get(int $delta)`
  * Nested field access (`$entity->field->value`)
  * Interface composition (multiple interfaces)
  * `methodOverrides` take precedence over core resolvers
  * Missing resolver throws with correct (differentiated) message
  * Unsupported method throws with correct (differentiated) message
  * Callable resolution caching (same `FieldItemList` double returned)
  * PHPUnit vs Prophecy behavioral parity (same inputs → same outputs)

**Rules**
* No Drupal modules enabled
* No database
* No Kernel test base classes

---

## COMPLETION CRITERIA

The implementation is complete only when:

* PHPUnit and Prophecy traits behave identically
* Callbacks receive both context and method arguments
* No Drupal runtime services are referenced
* Unsupported behavior fails deterministically with differentiated messages
* Callable results are cached per entity double instance
* Immutable doubles reject all setter invocations with clear error messages
* Mutable doubles correctly track and return mutated field values
* Common logic accounts for more than 80 percent of total LOC

---

## FINAL INSTRUCTION TO THE AGENT

DEUTEROS is not an entity emulator. It is a deliberately constrained,
value-object entity double designed to make Drupal unit testing viable without
Kernel overhead:

* Do not expand scope.
* Do not add realism.
* Do not optimize for convenience over correctness.
