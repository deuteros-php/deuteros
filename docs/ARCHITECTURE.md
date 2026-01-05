# Deuteros Architecture

This document provides architectural documentation for contributors and maintainers of Deuteros.

## Overview

### Design Philosophy

Deuteros is built around these core principles:

1. **Value Objects**: Entity doubles are read-only by default, ensuring predictable behavior
2. **Interfaces Only**: No concrete Drupal classes are used, only interfaces
3. **Fail Loudly**: Unsupported operations throw with differentiated, helpful error messages
4. **Framework Agnostic Core**: The resolution logic is independent of PHPUnit/Prophecy
5. **Parity Guarantee**: PHPUnit and Prophecy adapters behave identically

### Layer Structure

```
┌────────────────────────────────────────────────────────────┐
│                    User Code (Tests)                       │
└────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌────────────────────────────────────────────────────────────┐
│              Factory Layer (Framework Integration)         │
│  ┌─────────────────────┐  ┌─────────────────────────────┐  │
│  │ MockEntityDouble    │  │ ProphecyEntityDouble        │  │
│  │ Factory (PHPUnit)   │  │ Factory (Prophecy)          │  │
│  └─────────────────────┘  └─────────────────────────────┘  │
│                    ▲                ▲                      │
│                    └────────┬───────┘                      │
│                             │                              │
│              ┌──────────────┴──────────────┐               │
│              │ EntityDoubleFactory (Base)  │               │
│              └─────────────────────────────┘               │
└────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌────────────────────────────────────────────────────────────┐
│              Resolution Layer (Framework-Agnostic)         │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                 EntityDoubleBuilder                  │  │
│  │    (id, uuid, label, bundle, get, hasField, set)     │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │             FieldItemListDoubleBuilder               │  │
│  │  (first, get, isEmpty, getValue, referencedEntities) │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │               FieldItemDoubleBuilder                 │  │
│  │            (getValue, __get, setValue, __set)        │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌────────────────────────────────────────────────────────────┐
│                      Support Layer                         │
│  ┌───────────────────┐ ┌────────────────────────────────┐  │
│  │ GuardrailEnforcer │ │    MutableStateContainer       │  │
│  └───────────────────┘ └────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │             EntityReferenceNormalizer                │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌────────────────────────────────────────────────────────────┐
│                    Definition Layer                        │
│  ┌──────────────────────────────────────────────────────┐  │
│  │            EntityDoubleDefinition                    │  │
│  │    (readonly value object, immutable by design)      │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │             FieldDoubleDefinition                    │  │
│  │         (stores field value, scalar/array/callable   │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │          EntityDoubleDefinitionBuilder               │  │
│  │             (fluent builder for definitions)         │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
```

---

## Layer Details

### Definition Layer

**Purpose**: Store configuration without behavior. Pure data structures.

| Class | File | Responsibility |
|-------|------|----------------|
| `EntityDoubleDefinition` | `src/Double/EntityDoubleDefinition.php` | Immutable value object storing entity metadata, fields, interfaces, method overrides |
| `FieldDoubleDefinition` | `src/Double/FieldDoubleDefinition.php` | Stores a single field's value (scalar, array, or callable) |
| `EntityDoubleDefinitionBuilder` | `src/Double/EntityDoubleDefinitionBuilder.php` | Fluent builder for creating definitions |

**Key characteristics**:
- All classes are `final readonly` (PHP 8.2+)
- No behavior logic, only data storage
- `withContext()` and `withMutable()` return new instances (immutable pattern)

### Resolution Layer

**Purpose**: Generate callable resolvers that implement entity/field behavior without framework knowledge.

| Class | File | Responsibility |
|-------|------|----------------|
| `EntityDoubleBuilder` | `src/Double/EntityDoubleBuilder.php` | Produces resolvers for entity methods |
| `FieldItemListDoubleBuilder` | `src/Double/FieldItemListDoubleBuilder.php` | Produces resolvers for field list methods |
| `FieldItemDoubleBuilder` | `src/Double/FieldItemDoubleBuilder.php` | Produces resolvers for field item methods |

**Resolver signature**: All builders produce callables with this signature:
```php
fn(array $context, ...$args): mixed
```

### Support Layer

**Purpose**: Shared utilities used across layers.

| Class | File | Responsibility |
|-------|------|----------------|
| `GuardrailEnforcer` | `src/Double/GuardrailEnforcer.php` | Centralized exception generation with differentiated messages |
| `MutableStateContainer` | `src/Double/MutableStateContainer.php` | Tracks field mutations for mutable doubles |
| `EntityReferenceNormalizer` | `src/Double/EntityReferenceNormalizer.php` | Normalizes entity reference field values |

### Factory Layer

**Purpose**: Integrate with test frameworks (PHPUnit/Prophecy).

| Class | File | Responsibility |
|-------|------|----------------|
| `EntityDoubleFactory` | `src/Double/EntityDoubleFactory.php` | Abstract base with template method pattern |
| `MockEntityDoubleFactory` | `src/Double/PhpUnit/MockEntityDoubleFactory.php` | PHPUnit native mock implementation |
| `ProphecyEntityDoubleFactory` | `src/Double/Prophecy/ProphecyEntityDoubleFactory.php` | Prophecy double implementation |

---

## Key Patterns

### Resolver Pattern

All method implementations are expressed as callables that receive context:

```php
// Builder produces resolver
$resolver = fn(array $context, ...$args): mixed => /* implementation */;

// Factory wires resolver to mock/prophecy
$mock->method('methodName')->willReturnCallback($resolver);
```

This pattern enables:
- Context propagation to all methods
- Framework-agnostic core logic
- Consistent behavior across PHPUnit and Prophecy

#### Context Structure

All resolvers receive a context array with this structure:

```php
[
  '_definition' => EntityDoubleDefinition, // Always present
  // ... user-provided context values
]
```

The definition is added by `EntityDoubleDefinition::withContext()` before
user context is merged. This ensures all callbacks can access entity metadata.
The `_definition` key is reserved and cannot be overwritten by users.

Access the definition via the constant:
```php
$def = $context[EntityDoubleDefinition::CONTEXT_KEY];
$entityType = $def->entityType;
$bundle = $def->bundle;
```

### Method Resolution Order

When a method is called on an entity double:

1. **Method overrides** - Check `definition->methods` first (highest precedence)
2. **Core resolvers** - Use builder-generated resolver
3. **Guardrail check** - If explicitly unsupported, throw with reason
4. **Lenient default** - If lenient mode, return `null`
5. **Missing resolver exception** - Throw with interface context

### Template Method (Factory)

`EntityDoubleFactory` defines the skeleton for creating doubles:

```php
abstract class EntityDoubleFactory {
  // Template method
  protected function buildEntityDouble(EntityDoubleDefinition $definition): object {
    // 1. Resolve interfaces
    // 2. Create mutable state container (if needed)
    // 3. Create builder
    // 4. Create mock/prophecy object (abstract)
    // 5. Wire resolvers (abstract)
    // 6. Wire guardrails (abstract)
    // 7. Instantiate and return
  }

  // Abstract methods for adapters
  abstract protected function createDoubleForInterfaces(array $interfaces): object;
  abstract protected function wireEntityResolvers(...): void;
  // ... more abstract methods
}
```

### Field List Caching

Each entity instance caches field list doubles:

```php
$entity->get('field_name'); // Creates and caches FieldItemListInterface
$entity->get('field_name'); // Returns cached instance
$entity->field_name;        // Same cached instance
```

This ensures consistent object identity across multiple accesses.

### Entity Reference Auto-Detection

The `EntityReferenceNormalizer` detects entity references in field values:

```php
// All these formats are normalized to the same structure:
$author                              // EntityInterface directly
['entity' => $author]                // Explicit format
['entity' => $author, 'target_id' => 1] // With explicit target_id
[$author1, $author2]                 // Array of entities

// Normalized to:
[
  ['entity' => $author, 'target_id' => $author->id()],
]
```

---

## Data Flow

### Entity Creation

```
User: $factory->create($definition)
  │
  ├─► EntityDoubleFactory::create()
  │     │
  │     ├─► buildEntityDouble()
  │     │     │
  │     │     ├─► resolveInterfaces() - Deduplicate interface hierarchy
  │     │     ├─► new MutableStateContainer() - If mutable
  │     │     ├─► new EntityDoubleBuilder()
  │     │     ├─► createDoubleForInterfaces() - PHPUnit/Prophecy creates mock
  │     │     ├─► wireEntityResolvers() - Setup entity methods
  │     │     ├─► wireGuardrails() - Setup exception handling
  │     │     └─► instantiateDouble() - Reveal mock/prophecy
  │     │
  │     └─► Return EntityInterface
  │
  └─► User receives entity double
```

### Field Access

```
User: $entity->get('field_name')
  │
  ├─► EntityDoubleBuilder::buildGetResolver()
  │     │
  │     ├─► Check fieldListCache - Return if cached
  │     │
  │     ├─► createFieldItemListDouble()
  │     │     │
  │     │     ├─► Detect entity references - Choose interface type
  │     │     ├─► new FieldItemListDoubleBuilder()
  │     │     ├─► wireFieldListResolvers()
  │     │     │     │
  │     │     │     └─► For each item: createFieldItemDouble()
  │     │     │           │
  │     │     │           └─► wireFieldItemResolvers()
  │     │     │
  │     │     └─► instantiateFieldListDouble()
  │     │
  │     ├─► Cache field list
  │     └─► Return FieldItemListInterface
  │
  └─► User receives field list double
```

---

## Non-Negotiable Constraints

These constraints must never be violated:

| Constraint | Rationale |
|------------|-----------|
| No concrete Drupal classes | Avoid dependencies on Drupal internals |
| No service container access | Keep doubles lightweight, no bootstrap needed |
| No database access | Enable pure unit tests |
| Entities are value objects by default | Predictable, testable behavior |
| Unsupported operations fail loudly | Prevent silent bugs, guide users to solutions |
| PHPUnit/Prophecy adapters behave identically | Users can switch frameworks freely |
| All code passes phpcs | Consistent coding standards |
| All code passes phpstan (level 10) | Maximum type safety |

---

## Adding New Features

### Adding a New Entity Method

1. **Add resolver to `EntityDoubleBuilder`**:
   ```php
   public function buildNewMethodResolver(): callable {
     return fn(array $context, ...$args) => /* implementation */;
   }
   ```

2. **Wire in `EntityDoubleFactory`**:
   ```php
   protected function wireEntityResolvers(...): void {
     // Add to method wiring
     $resolvers['newMethod'] = $builder->buildNewMethodResolver();
   }
   ```

3. **Add tests**:
   - Unit test in `tests/Unit/Common/EntityDoubleBuilderTest.php`
   - Integration test in `tests/Integration/EntityDoubleFactoryTestBase.php`

### Adding a New Guardrail

1. **Add to `GuardrailEnforcer::UNSUPPORTED_METHODS`**:
   ```php
   const array UNSUPPORTED_METHODS = [
     // ...existing...
     'newMethod' => 'requires specific service',
   ];
   ```

2. **Guardrails are automatically wired** by `wireGuardrails()` in factories

### Adding a New Field List Method

1. **Add resolver to `FieldItemListDoubleBuilder`**
2. **Wire in factory's `wireFieldListResolvers()`**
3. **Add unit test and integration test**

---

## Testing Strategy

```
         ┌──────────────────┐
         │   Performance    │     ◄── Benchmark comparisons
         └──────────────────┘
         ┌──────────────────┐
         │   Integration    │     ◄── Factory behavior, adapter parity
         └──────────────────┘
       ┌───────────────────────┐
       │        Unit           │  ◄── Builders, definitions, support classes
       └───────────────────────┘
```

**Distribution**: ~66% unit tests, remainder integration.

### Test Structure

| Directory | Purpose |
|-----------|---------|
| `tests/Unit/Common/` | Unit tests for definition, builder, and support classes |
| `tests/Integration/PhpUnit/` | PHPUnit factory integration tests |
| `tests/Integration/Prophecy/` | Prophecy factory integration tests |
| `tests/Performance/` | Benchmark tests |

### Adapter Parity

`EntityDoubleFactoryTestBase` is inherited by both PHPUnit and Prophecy test classes:

```php
// Both test classes inherit the same tests
class MockEntityDoubleFactoryTest extends EntityDoubleFactoryTestBase {
  // Uses MockEntityDoubleFactory
}

class ProphecyEntityDoubleFactoryTest extends EntityDoubleFactoryTestBase {
  // Uses ProphecyEntityDoubleFactory
}
```

This inheritance pattern guarantees identical behavior across adapters.

---

## Implementation History

Deuteros was implemented in phases with subsequent refactoring to improve API ergonomics and architecture. For detailed historical information, see:

- [docs/archive/init.md](archive/init.md) - Initial requirements and constraints
- [docs/archive/plan.md](archive/plan.md) - Original 8-phase implementation plan
- [docs/archive/refactoring.md](archive/refactoring.md) - Post-implementation improvements (15 tasks)

---

## Entity Testing Layer

In addition to entity doubles, Deuteros provides a `SubjectEntityFactory` for
testing actual Drupal entity class instances with doubled dependencies.

### Architecture Overview

```
┌────────────────────────────────────────────────────────────┐
│                    User Code (Tests)                       │
└────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌────────────────────────────────────────────────────────────┐
│            SubjectEntityFactory (Single Entry Point)       │
│  - fromTest() → auto-detects PHPUnit/Prophecy              │
│  - installContainer() → sets up container with doubles     │
│  - create() → real entity with field doubles               │
│  - uninstallContainer() → cleanup                          │
└────────────────────────────────────────────────────────────┘
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌──────────────────┐
│ PhpUnitService  │  │ ProphecyService │  │ EntityDouble     │
│ Doubler         │  │ Doubler         │  │ Factory          │
└─────────────────┘  └─────────────────┘  └──────────────────┘
```

### Key Design Decisions

**Constructor Bypass via Reflection**

The helper uses `ReflectionClass::newInstanceWithoutConstructor()` to create
entity instances without invoking the constructor. This avoids the need to
double the many services that `ContentEntityBase`'s constructor requires. After
instantiation, required internal properties are set via reflection:

- `entityTypeId` - From the `#[ContentEntityType]` attribute
- `entityKeys` - Entity keys (id, bundle, uuid) from provided values
- `fields` - DEUTEROS field doubles are injected directly
- Internal state (`translations`, `defaultLangcode`, etc.)

**Service Container Doubling**

The helper installs a container with doubled services via `\Drupal::setContainer()`:

| Service | Purpose |
|---------|---------|
| `entity_type.manager` | Returns entity type definitions |
| `entity_type.bundle.info` | Bundle information |
| `language_manager` | Default language handling |
| `uuid` | UUID generation (stubbed) |
| `module_handler` | No-op hook invocations |
| `entity_field.manager` | Returns empty field definitions |

**Entity Type Configuration from Attributes**

Entity keys are read from PHP 8 attributes (`#[ContentEntityType]` or
`#[ConfigEntityType]`) on the entity class. This eliminates explicit config:

```php
#[ContentEntityType(
  id: 'node',
  entity_keys: [
    'id' => 'nid',
    'bundle' => 'type',
    // ...
  ],
)]
class Node extends ContentEntityBase { }

#[ConfigEntityType(
  id: 'my_config',
  entity_keys: ['id' => 'id', 'label' => 'label'],
)]
class MyConfig extends ConfigEntityBase { }
```

**Content vs Config Entity Handling**

The factory handles content entities and config entities differently:

| Entity Type | Base Class | Field Doubles | Initialization |
|-------------|------------|---------------|----------------|
| Content | `ContentEntityBase` | Yes (injected via reflection) | `initializeContentEntity()` |
| Config | `ConfigEntityBase` | No (not fieldable) | `initializeConfigEntity()` |

Content entities implement `FieldableEntityInterface` and receive DEUTEROS field
doubles. Config entities have their properties set directly via reflection.

### Files

| File | Purpose |
|------|---------|
| `src/Entity/SubjectEntityFactory.php` | Main entry point |
| `src/Entity/SubjectEntityTestBase.php` | Abstract test base class for simplified DX |
| `src/Entity/ServiceDoublerInterface.php` | Service doubler contract |
| `src/Entity/PhpUnit/PhpUnitServiceDoubler.php` | PHPUnit service doubler |
| `src/Entity/Prophecy/ProphecyServiceDoubler.php` | Prophecy service doubler |

### Test Structure

| Directory | Purpose |
|-----------|---------|
| `tests/Unit/Entity/` | Unit tests for SubjectEntityFactory |
| `tests/Integration/Entity/` | Integration tests |
| `tests/Integration/Entity/SubjectEntityFactoryTestBase.php` | Shared tests for adapter parity |
| `tests/Integration/Entity/PhpUnit/` | PHPUnit adapter tests |
| `tests/Integration/Entity/Prophecy/` | Prophecy adapter tests |
