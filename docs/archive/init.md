You are implementing **DEUTEROS — Drupal Entity Unit Test Extensible Replacement Object Scaffolding**.

Your task is to implement the library **exactly according to the implementation plan available at @docs/plan.md**. That plan is the single source of truth for scope, architecture, naming, behavior, and constraints.

Read the plan in full before writing any code.

### Core intent

DEUTEROS provides **value-object entity doubles** for **Drupal unit tests only**, allowing code that depends on entity and field interfaces to be tested **without Kernel tests, module enablement, storage, or services**.

This is **not** an entity emulator and must not attempt to replicate Drupal internals.

### Non-negotiable constraints

You must comply with all the following:

* Use **interfaces only**
* No concrete Drupal classes
* No service container access
* No database access
* No Kernel or Functional test base classes
* Entities are **read-only value objects** unless explicitly instantiated as **mutable**
* Unsupported behavior must **fail loudly and deterministically**
* PHPUnit and Prophecy adapters must have **identical semantics**
* Use the term **“Double”** everywhere except when explicitly referring to **PHPUnit native mock objects**

Any deviation from these rules is a defect.

### Required architecture

Implement the following layers and nothing more:

1. **Definition layer**

    * `Deuteros\Common\EntityDefinition`
    * `Deuteros\Common\FieldDefinition`
      Pure value objects. No logic beyond normalization.

2. **Core resolution layer (framework-agnostic)**

    * `Deuteros\Common\EntityDoubleBuilder`
    * `Deuteros\Common\FieldItemListDoubleBuilder`
      Produces callable resolvers. No mocks, no Prophecy, no PHPUnit.

3. **Shared base trait**

    * `Deuteros\Common\EntityDefinitionNormalizerTrait`
      Input normalization only.

4. **PHPUnit adapter trait (native mock objects)**

    * `Deuteros\Mock\EntityDoubleTrait`
      Uses PHPUnit mocks and `willReturnCallback()`.

5. **Prophecy adapter trait**

    * `Deuteros\Prophecy\EntityDoubleTrait`
      Uses `prophesize()`, `will()`, and `reveal()`.

Core logic must be shared across adapters. Adapter traits must be thin.

### Guardrails you must enforce

The following methods must explicitly throw with the exact intent described in the plan:

* `save()`
* `delete()`
* `access()`
* `getTranslation()`
* `getTranslations()`
* `toUrl()`
* Entity reference traversal methods

The failure message must clearly state that this is a **unit-test value object** and that a **Kernel test** is required for that behavior.

### Dynamic resolution

All entity and field values must support:

* Static values
* Callbacks receiving:

    * A shared test context
    * Method arguments (e.g., field name, delta)

Callbacks must be resolved lazily.

### Validation

You must also implement **minimal PHPUnit self-tests** that prove:

* Scalar field access
* Callback-based field resolution
* Context propagation
* Extensibility via additional interface implementation
* Guardrail exception behavior
* Behavioral parity between PHPUnit and Prophecy adapters

These tests must not enable any Drupal modules.

### Completion standard

You are done **only** when:

* All phases in the plan are implemented
* Core logic accounts for the majority of the code
* PHPUnit and Prophecy behavior is demonstrably identical
* No scope expansion has occurred

If anything in the plan is unclear, stop and ask for clarification **before** implementing.

Begin implementation only after fully internalizing the plan.
