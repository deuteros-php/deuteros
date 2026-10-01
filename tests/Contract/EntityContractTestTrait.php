<?php

declare(strict_types=1);

namespace Deuteros\Tests\Contract;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Url;

/**
 * Defines the behavior entity doubles share with real entities.
 *
 * The contract runs against a real entity with real field items in a Kernel
 * test, and against the entity doubles of each adapter in unit tests. Passing
 * everywhere confirms that doubles behave as the real objects they stand in
 * for.
 *
 * Only behavior both sides support belongs here. Deuteros features without a
 * Drupal counterpart, such as guardrails, lenient mode, method overrides,
 * callbacks and immutable doubles, are covered by the integration tests.
 *
 * Each "doTest*" method is a subtest, and the single ::test method runs them
 * all in declaration order. Kernel tests run in a separate process each, so
 * one test method keeps the Drupal installation to a single one. The
 * ::resetContractState hook runs before every subtest to isolate it.
 *
 * Subtests describe entities through the ::createContractEntity hook: fields
 * are passed as "ContractField" objects holding the raw values, in any format
 * "ContentEntityBase::create" accepts.
 */
trait EntityContractTestTrait {

  /**
   * The entity type ID of contract entities.
   */
  protected const string ENTITY_TYPE_ID = 'entity_test';

  /**
   * The bundle of contract entities.
   */
  protected const string BUNDLE = 'article';

  /**
   * Creates the entity under contract.
   *
   * @param array<string, \Deuteros\Tests\Contract\ContractField> $fields
   *   The fields of the entity, keyed by field name.
   * @param int|string|null $id
   *   The entity ID.
   * @param string|null $uuid
   *   The entity UUID, or NULL to have one generated.
   * @param string|null $label
   *   The entity label.
   * @param string|null $url
   *   The URL a double returns from ::toUrl. A real entity ignores it and
   *   derives its URL from routing.
   * @param bool $mutable
   *   Whether the entity is changed by the test. Real entities always are.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity.
   */
  abstract protected function createContractEntity(array $fields = [], int|string|null $id = NULL, ?string $uuid = NULL, ?string $label = NULL, ?string $url = NULL, bool $mutable = FALSE): FieldableEntityInterface;

  /**
   * Creates an entity to reference from an entity reference field.
   *
   * The entity is of the contract entity type, which reference fields target.
   *
   * @param int|null $id
   *   The entity ID, or NULL for an entity that was never saved.
   * @param string $label
   *   The entity label.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  abstract protected function createReferencedEntity(?int $id, string $label): EntityInterface;

  /**
   * Removes whatever a previous subtest left behind.
   */
  abstract protected function resetContractState(): void;

  /**
   * Runs every subtest of the contract.
   */
  public function test(): void {
    $subtests = array_filter(get_class_methods($this), fn(string $method): bool => str_starts_with($method, 'doTest'));
    $this->assertNotEmpty($subtests);

    foreach ($subtests as $subtest) {
      $this->resetContractState();
      $this->{$subtest}();
    }
  }

  /**
   * Describes a single-value text field.
   *
   * @param mixed $value
   *   The raw field value.
   *
   * @return \Deuteros\Tests\Contract\ContractField
   *   The field.
   */
  protected static function stringField(mixed $value): ContractField {
    return new ContractField('string', $value, ['max_length' => 255]);
  }

  /**
   * Describes an entity reference field targeting the contract entity type.
   *
   * @param mixed $value
   *   The raw field value.
   *
   * @return \Deuteros\Tests\Contract\ContractField
   *   The field.
   */
  protected static function referenceField(mixed $value): ContractField {
    return new ContractField('entity_reference', $value, ['target_type' => self::ENTITY_TYPE_ID]);
  }

  /**
   * Describes a link field, whose items have more than one property.
   *
   * @param mixed $value
   *   The raw field value.
   *
   * @return \Deuteros\Tests\Contract\ContractField
   *   The field.
   */
  protected static function linkField(mixed $value): ContractField {
    return new ContractField('link', $value, [], 'Drupal\link\Plugin\Field\FieldType\LinkItem');
  }

  /**
   * Returns the item at a delta, failing when there is none.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param string $fieldName
   *   The field name.
   * @param int $delta
   *   The delta.
   *
   * @return \Drupal\Core\Field\FieldItemInterface
   *   The field item.
   */
  protected function getItem(FieldableEntityInterface $entity, string $fieldName, int $delta = 0): FieldItemInterface {
    $item = $entity->get($fieldName)->get($delta);
    $this->assertInstanceOf(FieldItemInterface::class, $item);
    return $item;
  }

  /**
   * Asserts that a list holds the given entities, in order.
   *
   * Real reference fields load saved entities from storage, so they return
   * equal entities rather than the same instances.
   *
   * @param list<\Drupal\Core\Entity\EntityInterface> $expected
   *   The expected entities.
   * @param array<\Drupal\Core\Entity\EntityInterface> $actual
   *   The actual entities.
   */
  protected function assertSameEntities(array $expected, array $actual): void {
    $describe = fn(EntityInterface $entity): array => [$entity->getEntityTypeId(), $entity->id(), $entity->label()];
    $this->assertSame(array_map($describe, $expected), array_map($describe, array_values($actual)));
  }

  /**
   * Tests the entity metadata accessors.
   */
  protected function doTestMetadata(): void {
    $entity = $this->createContractEntity(id: 42, uuid: '550e8400-e29b-41d4-a716-446655440000', label: 'Test Article');

    $this->assertSame(self::ENTITY_TYPE_ID, $entity->getEntityTypeId());
    $this->assertSame(self::BUNDLE, $entity->bundle());
    $this->assertSame(42, $entity->id());
    $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $entity->uuid());
    $this->assertSame('Test Article', $entity->label());
  }

  /**
   * Tests that an ID given as a numeric string is returned unchanged.
   */
  protected function doTestNumericStringId(): void {
    $entity = $this->createContractEntity(id: '123');

    $this->assertSame('123', $entity->id());
  }

  /**
   * Tests that an entity given no UUID has one.
   */
  protected function doTestGeneratedUuid(): void {
    $first = $this->createContractEntity();
    $second = $this->createContractEntity();

    $this->assertIsString($first->uuid());
    $this->assertNotSame($first->uuid(), $second->uuid());
  }

  /**
   * Tests ::hasField for defined and undefined fields.
   */
  protected function doTestHasField(): void {
    $entity = $this->createContractEntity(['field_title' => self::stringField('Title')]);

    $this->assertTrue($entity->hasField('field_title'));
    $this->assertFalse($entity->hasField('field_undefined'));
  }

  /**
   * Tests that getting an undefined field throws.
   */
  protected function doTestGetUndefinedFieldThrows(): void {
    $entity = $this->createContractEntity(['field_title' => self::stringField('Title')]);

    try {
      $entity->get('field_undefined');
      $this->fail('Getting an undefined field throws.');
    }
    catch (\InvalidArgumentException) {
      // Expected: both sides reject the field, with different messages.
    }
  }

  /**
   * Tests that ::get returns the same field item list on every call.
   */
  protected function doTestFieldItemListIsCached(): void {
    $entity = $this->createContractEntity(['field_title' => self::stringField('Title')]);

    $this->assertSame($entity->get('field_title'), $entity->get('field_title'));
    // @phpstan-ignore property.notFound
    $this->assertSame($entity->get('field_title'), $entity->field_title);
  }

  /**
   * Tests reading a scalar value through the field item list and its item.
   */
  protected function doTestScalarFieldValue(): void {
    $entity = $this->createContractEntity([
      'field_title' => self::stringField('Test Title'),
      'field_count' => new ContractField('integer', 42),
    ]);

    // @phpstan-ignore property.notFound
    $this->assertSame('Test Title', $entity->get('field_title')->value);
    // @phpstan-ignore property.notFound
    $this->assertSame('Test Title', $this->getItem($entity, 'field_title')->value);
    // @phpstan-ignore property.notFound
    $this->assertSame(42, $entity->get('field_count')->value);
  }

  /**
   * Tests reading a field through magic property access on the entity.
   */
  protected function doTestMagicFieldAccess(): void {
    $entity = $this->createContractEntity([
      'field_title' => self::stringField('Test Title'),
      'field_author' => self::referenceField(['target_id' => 42]),
    ]);

    // @phpstan-ignore property.notFound, property.nonObject
    $this->assertSame('Test Title', $entity->field_title->value);
    // @phpstan-ignore property.notFound, property.nonObject
    $this->assertSame(42, $entity->field_author->target_id);
  }

  /**
   * Tests ::getValue on field item lists and field items.
   */
  protected function doTestGetValue(): void {
    $entity = $this->createContractEntity([
      'field_title' => self::stringField('Test Title'),
      'field_count' => new ContractField('integer', 42),
      'field_tags' => self::referenceField([['target_id' => 1], ['target_id' => 2]]),
    ]);

    $this->assertSame([['value' => 'Test Title']], $entity->get('field_title')->getValue());
    $this->assertSame(['value' => 'Test Title'], $this->getItem($entity, 'field_title')->getValue());
    $this->assertSame([['value' => 42]], $entity->get('field_count')->getValue());
    $this->assertSame([['target_id' => 1], ['target_id' => 2]], $entity->get('field_tags')->getValue());
  }

  /**
   * Tests a field whose items have more than one property.
   */
  protected function doTestMultiplePropertyField(): void {
    $values = [
      ['uri' => 'https://example.com', 'title' => 'Example', 'options' => []],
      ['uri' => 'https://drupal.org', 'title' => 'Drupal', 'options' => []],
    ];
    $entity = $this->createContractEntity(['field_links' => self::linkField($values)]);

    // @phpstan-ignore property.notFound
    $this->assertSame('https://example.com', $entity->get('field_links')->uri);
    // @phpstan-ignore property.notFound
    $this->assertSame('Drupal', $this->getItem($entity, 'field_links', 1)->title);
    $this->assertSame($values, $entity->get('field_links')->getValue());
    $this->assertSame($values[1], $this->getItem($entity, 'field_links', 1)->getValue());
  }

  /**
   * Tests a single item given as a property array rather than a delta list.
   */
  protected function doTestSingleItemPropertyArray(): void {
    $entity = $this->createContractEntity([
      'field_link' => self::linkField(['uri' => 'https://example.com', 'title' => 'Example', 'options' => []]),
    ]);

    $this->assertCount(1, $entity->get('field_link'));
    $this->assertSame([['uri' => 'https://example.com', 'title' => 'Example', 'options' => []]], $entity->get('field_link')->getValue());
  }

  /**
   * Tests accessing the items of a multi-value field.
   */
  protected function doTestMultiValueField(): void {
    $entity = $this->createContractEntity([
      'field_tags' => self::referenceField([['target_id' => 1], ['target_id' => 2], ['target_id' => 3]]),
    ]);
    $items = $entity->get('field_tags');

    $first = $items->first();
    $this->assertNotNull($first);
    // @phpstan-ignore property.notFound
    $this->assertSame(1, $first->target_id);
    // @phpstan-ignore property.notFound
    $this->assertSame(3, $this->getItem($entity, 'field_tags', 2)->target_id);
    // @phpstan-ignore property.notFound, method.impossibleType
    $this->assertSame(1, $items->target_id);
    $this->assertNull($items->get(3));
    $this->assertCount(3, $items);
  }

  /**
   * Tests iterating over the items of a field.
   */
  protected function doTestFieldItemIteration(): void {
    $entity = $this->createContractEntity([
      'field_tags' => self::referenceField([['target_id' => 1], ['target_id' => 2], ['target_id' => 3]]),
      'field_title' => self::stringField('Test Title'),
      'field_empty' => self::stringField(NULL),
    ]);

    $targetIds = [];
    foreach ($entity->get('field_tags') as $delta => $item) {
      // @phpstan-ignore property.notFound
      $targetIds[$delta] = $item->target_id;
    }
    $this->assertSame([1, 2, 3], $targetIds);

    $values = [];
    foreach ($entity->get('field_title') as $item) {
      // @phpstan-ignore property.notFound
      $values[] = $item->value;
    }
    $this->assertSame(['Test Title'], $values);

    $this->assertSame([], iterator_to_array($entity->get('field_empty')));
  }

  /**
   * Tests reading the items of a field with array syntax.
   */
  protected function doTestFieldItemArrayAccess(): void {
    $entity = $this->createContractEntity([
      'field_tags' => self::referenceField([['target_id' => 1], ['target_id' => 2]]),
    ]);
    $items = $entity->get('field_tags');

    $this->assertTrue(isset($items[0]));
    $this->assertTrue(isset($items[1]));
    $this->assertFalse(isset($items[2]));
    // @phpstan-ignore property.nonObject, property.notFound
    $this->assertSame(2, $items[1]->target_id);
    $this->assertSame($items->get(1), $items[1]);
  }

  /**
   * Tests a field set to NULL.
   */
  protected function doTestNullFieldValue(): void {
    $entity = $this->createContractEntity(['field_title' => self::stringField(NULL)]);
    $items = $entity->get('field_title');

    $this->assertTrue($items->isEmpty());
    $this->assertNull($items->first());
    $this->assertSame([], $items->getValue());
    $this->assertCount(0, $items);
    // @phpstan-ignore property.notFound
    $this->assertNull($items->value);
  }

  /**
   * Tests a field set to an empty list.
   */
  protected function doTestEmptyListFieldValue(): void {
    $entity = $this->createContractEntity(['field_tags' => self::referenceField([])]);
    $items = $entity->get('field_tags');

    $this->assertTrue($items->isEmpty());
    $this->assertNull($items->first());
    $this->assertSame([], $items->getValue());
  }

  /**
   * Tests a field set to an empty string.
   */
  protected function doTestEmptyStringFieldValue(): void {
    $entity = $this->createContractEntity(['field_title' => self::stringField('')]);
    $items = $entity->get('field_title');

    // @phpstan-ignore property.notFound
    $this->assertSame('', $items->value);
    $this->assertSame([['value' => '']], $items->getValue());
    $this->assertTrue($this->getItem($entity, 'field_title')->isEmpty());
    $this->assertTrue($items->isEmpty());
  }

  /**
   * Tests a field set to zero, which is not empty.
   */
  protected function doTestZeroFieldValue(): void {
    $entity = $this->createContractEntity(['field_count' => new ContractField('integer', 0)]);
    $items = $entity->get('field_count');

    // @phpstan-ignore property.notFound
    $this->assertSame(0, $items->value);
    $this->assertFalse($items->isEmpty());
  }

  /**
   * Tests that Unicode values are returned unchanged.
   */
  protected function doTestUnicodeFieldValue(): void {
    $value = '日本語テスト 🎉 مرحبا العالم Ñoño';
    $entity = $this->createContractEntity(['field_title' => self::stringField($value)]);

    // @phpstan-ignore property.notFound
    $this->assertSame($value, $entity->get('field_title')->value);
    $this->assertSame([['value' => $value]], $entity->get('field_title')->getValue());
  }

  /**
   * Tests a multi-value field with an item holding NULL.
   */
  protected function doTestNullItemInMultiValueField(): void {
    $entity = $this->createContractEntity([
      'field_title' => self::stringField([['value' => 'first'], ['value' => NULL], ['value' => 'third']]),
    ]);

    // @phpstan-ignore property.notFound
    $this->assertSame('first', $this->getItem($entity, 'field_title', 0)->value);
    // @phpstan-ignore property.notFound
    $this->assertNull($this->getItem($entity, 'field_title', 1)->value);
    $this->assertTrue($this->getItem($entity, 'field_title', 1)->isEmpty());
    // @phpstan-ignore property.notFound
    $this->assertSame('third', $this->getItem($entity, 'field_title', 2)->value);
    $this->assertCount(3, $entity->get('field_title'));
  }

  /**
   * Tests ::getString on field item lists and field items.
   */
  protected function doTestGetString(): void {
    $entity = $this->createContractEntity([
      'field_title' => self::stringField('Hello World'),
      'field_tags' => self::stringField(['alpha', 'beta', 'gamma']),
      'field_empty' => self::stringField(NULL),
    ]);

    $this->assertSame('Hello World', $entity->get('field_title')->getString());
    $this->assertSame('Hello World', $this->getItem($entity, 'field_title')->getString());
    $this->assertSame('alpha, beta, gamma', $entity->get('field_tags')->getString());
    $this->assertSame('', $entity->get('field_empty')->getString());
  }

  /**
   * Tests isset() on the properties of field item lists and field items.
   */
  protected function doTestPropertyIsset(): void {
    $entity = $this->createContractEntity([
      'field_title' => self::stringField('Test Title'),
      'field_empty' => self::stringField(NULL),
      'field_ref' => self::referenceField(['target_id' => 42]),
    ]);
    $item = $this->getItem($entity, 'field_title');

    $this->assertTrue(isset($entity->get('field_title')->value));
    $this->assertFalse(isset($entity->get('field_title')->target_id));
    $this->assertFalse(isset($entity->get('field_empty')->value));
    $this->assertTrue(isset($entity->get('field_ref')->target_id));
    $this->assertTrue(isset($item->value));
    $this->assertFalse(isset($item->target_id));
  }

  /**
   * Tests iterating over the fields of an entity.
   *
   * A real entity also iterates over its base fields, so the test only checks
   * the configured fields.
   */
  protected function doTestEntityFieldIteration(): void {
    $entity = $this->createContractEntity([
      'field_title' => self::stringField('Test Title'),
      'field_count' => new ContractField('integer', 42),
    ]);

    $fields = [];
    // @phpstan-ignore foreach.nonIterable
    foreach ($entity as $name => $items) {
      assert(is_string($name));
      $fields[$name] = $items;
    }

    $this->assertArrayHasKey('field_title', $fields);
    $this->assertArrayHasKey('field_count', $fields);
    $this->assertSame($entity->get('field_title'), $fields['field_title']);
    // @phpstan-ignore property.nonObject
    $this->assertSame(42, $fields['field_count']->value);
  }

  /**
   * Tests referencing an entity by passing it as the field value.
   */
  protected function doTestEntityReferenceShorthand(): void {
    $author = $this->createReferencedEntity(42, 'Author');
    $entity = $this->createContractEntity(['field_author' => self::referenceField($author)]);
    $items = $entity->get('field_author');

    $this->assertInstanceOf(EntityReferenceFieldItemListInterface::class, $items);
    // @phpstan-ignore property.notFound
    $this->assertSame($author, $items->entity);
    // @phpstan-ignore property.notFound
    $this->assertSame($author, $this->getItem($entity, 'field_author')->entity);
    // @phpstan-ignore property.notFound
    $this->assertSame($author->id(), $items->target_id);
  }

  /**
   * Tests referencing an entity with an explicit "entity" property.
   */
  protected function doTestEntityReferenceExplicitFormat(): void {
    $author = $this->createReferencedEntity(42, 'Author');
    $entity = $this->createContractEntity(['field_author' => self::referenceField(['entity' => $author])]);

    // @phpstan-ignore property.notFound
    $this->assertSame($author, $entity->get('field_author')->entity);
    // @phpstan-ignore property.notFound
    $this->assertSame($author->id(), $entity->get('field_author')->target_id);
  }

  /**
   * Tests referencing an entity that was never saved.
   */
  protected function doTestEntityReferenceToUnsavedEntity(): void {
    $author = $this->createReferencedEntity(NULL, 'Author');
    $entity = $this->createContractEntity(['field_author' => self::referenceField($author)]);

    // @phpstan-ignore property.notFound
    $this->assertSame($author, $entity->get('field_author')->entity);
    // @phpstan-ignore property.notFound, method.impossibleType
    $this->assertNull($entity->get('field_author')->target_id);
  }

  /**
   * Tests a reference holding only the ID of an entity that does not exist.
   */
  protected function doTestEntityReferenceTargetIdOnly(): void {
    $entity = $this->createContractEntity(['field_author' => self::referenceField(['target_id' => 42])]);
    $items = $entity->get('field_author');

    $this->assertInstanceOf(EntityReferenceFieldItemListInterface::class, $items);
    // @phpstan-ignore property.notFound, method.impossibleType
    $this->assertSame(42, $items->target_id);
    // @phpstan-ignore property.notFound
    $this->assertNull($items->entity);
  }

  /**
   * Tests the entities referenced by a multi-value field.
   */
  protected function doTestReferencedEntities(): void {
    $tag1 = $this->createReferencedEntity(1, 'Tag 1');
    $tag2 = $this->createReferencedEntity(2, 'Tag 2');
    $entity = $this->createContractEntity(['field_tags' => self::referenceField([$tag1, $tag2])]);
    $items = $entity->get('field_tags');
    assert($items instanceof EntityReferenceFieldItemListInterface);

    $this->assertSameEntities([$tag1, $tag2], $items->referencedEntities());
    // @phpstan-ignore property.notFound
    $this->assertSame($tag2, $this->getItem($entity, 'field_tags', 1)->entity);
    // @phpstan-ignore property.notFound
    $this->assertSame($tag2->id(), $this->getItem($entity, 'field_tags', 1)->target_id);
  }

  /**
   * Tests a multi-value reference field with an empty item in between.
   */
  protected function doTestReferencedEntitiesSkipEmptyItems(): void {
    $tag1 = $this->createReferencedEntity(1, 'Tag 1');
    $tag2 = $this->createReferencedEntity(2, 'Tag 2');
    $entity = $this->createContractEntity(['field_tags' => self::referenceField([$tag1, ['entity' => NULL], $tag2])]);
    $items = $entity->get('field_tags');
    assert($items instanceof EntityReferenceFieldItemListInterface);

    $this->assertSameEntities([$tag1, $tag2], $items->referencedEntities());
  }

  /**
   * Tests a reference field holding only an empty reference.
   */
  protected function doTestEmptyEntityReference(): void {
    $entity = $this->createContractEntity(['field_author' => self::referenceField(['entity' => NULL])]);
    $items = $entity->get('field_author');

    $this->assertInstanceOf(EntityReferenceFieldItemListInterface::class, $items);
    $this->assertTrue($items->isEmpty());
    $this->assertSame([], $items->referencedEntities());
  }

  /**
   * Tests that a field of another type is not an entity reference list.
   */
  protected function doTestNonReferenceFieldIsNotReferenceList(): void {
    $entity = $this->createContractEntity(['field_title' => self::stringField('Test Title')]);

    $this->assertNotInstanceOf(EntityReferenceFieldItemListInterface::class, $entity->get('field_title'));
  }

  /**
   * Tests navigating a chain of references.
   */
  protected function doTestNestedEntityReferences(): void {
    $leaf = $this->createReferencedEntity(3, 'Leaf');
    $middle = $this->createContractEntity(id: 2, label: 'Middle', fields: ['field_ref' => self::referenceField($leaf)]);
    $root = $this->createContractEntity(id: 1, label: 'Root', fields: ['field_ref' => self::referenceField($middle)]);

    // @phpstan-ignore property.notFound
    $referenced = $root->get('field_ref')->entity;
    $this->assertInstanceOf(FieldableEntityInterface::class, $referenced);
    $this->assertSame('Middle', $referenced->label());
    // @phpstan-ignore property.notFound
    $this->assertSame('Leaf', $referenced->get('field_ref')->entity->label());
  }

  /**
   * Tests the field definition of a field.
   */
  protected function doTestFieldDefinition(): void {
    $entity = $this->createContractEntity(['field_title' => self::stringField('Title')]);

    $definition = $entity->get('field_title')->getFieldDefinition();
    $this->assertSame('field_title', $definition->getName());
    $this->assertSame('string', $definition->getType());
    $this->assertSame(255, $definition->getSetting('max_length'));
    $this->assertNull($definition->getSetting('missing'));
  }

  /**
   * Tests the field storage definition of a field.
   */
  protected function doTestFieldStorageDefinition(): void {
    $entity = $this->createContractEntity(['field_title' => self::stringField('Title')]);

    $storage = $entity->get('field_title')->getFieldDefinition()->getFieldStorageDefinition();
    $this->assertSame('field_title', $storage->getName());
    $this->assertSame('string', $storage->getType());
    $this->assertSame(255, $storage->getSetting('max_length'));
    $this->assertNull($storage->getSetting('missing'));
    $this->assertSame('value', $storage->getMainPropertyName());
  }

  /**
   * Tests the main property of an entity reference field.
   */
  protected function doTestEntityReferenceMainProperty(): void {
    $entity = $this->createContractEntity(['field_tags' => self::referenceField([['target_id' => 1]])]);

    $storage = $entity->get('field_tags')->getFieldDefinition()->getFieldStorageDefinition();
    $this->assertSame('entity_reference', $storage->getType());
    $this->assertSame(self::ENTITY_TYPE_ID, $storage->getSetting('target_type'));
    $this->assertSame('target_id', $storage->getMainPropertyName());
  }

  /**
   * Tests the main property named by the field item class.
   */
  protected function doTestMainPropertyFromItemClass(): void {
    $entity = $this->createContractEntity(['field_link' => self::linkField(['uri' => 'https://example.com'])]);

    $storage = $entity->get('field_link')->getFieldDefinition()->getFieldStorageDefinition();
    $this->assertSame('link', $storage->getType());
    $this->assertSame('uri', $storage->getMainPropertyName());
  }

  /**
   * Tests changing a field value with ::set.
   */
  protected function doTestSetFieldValue(): void {
    $entity = $this->createContractEntity(['field_status' => self::stringField('draft')], mutable: TRUE);

    $this->assertSame($entity, $entity->set('field_status', 'published'));
    // @phpstan-ignore property.notFound
    $this->assertSame('published', $entity->get('field_status')->value);
    $this->assertSame([['value' => 'published']], $entity->get('field_status')->getValue());
  }

  /**
   * Tests changing a field value through magic property access.
   */
  protected function doTestMagicSetFieldValue(): void {
    $entity = $this->createContractEntity(['field_status' => self::stringField('draft')], mutable: TRUE);

    // @phpstan-ignore property.notFound
    $entity->field_status = 'published';

    // @phpstan-ignore property.notFound
    $this->assertSame('published', $entity->get('field_status')->value);
  }

  /**
   * Tests changing a reference field to another entity.
   */
  protected function doTestSetEntityReference(): void {
    $first = $this->createReferencedEntity(1, 'First');
    $second = $this->createReferencedEntity(2, 'Second');
    $entity = $this->createContractEntity(['field_ref' => self::referenceField($first)], mutable: TRUE);

    $entity->set('field_ref', $second);

    // @phpstan-ignore property.notFound
    $this->assertSame($second, $entity->get('field_ref')->entity);
    // @phpstan-ignore property.notFound
    $this->assertSame($second->id(), $entity->get('field_ref')->target_id);
  }

  /**
   * Tests that iterating over the fields of an entity sees changes.
   */
  protected function doTestEntityFieldIterationAfterSet(): void {
    $entity = $this->createContractEntity(['field_status' => self::stringField('draft')], mutable: TRUE);
    $entity->set('field_status', 'published');

    $values = [];
    // @phpstan-ignore foreach.nonIterable
    foreach ($entity as $name => $items) {
      assert(is_string($name));
      // @phpstan-ignore property.nonObject
      $values[$name] = $items->value;
    }

    $this->assertSame('published', $values['field_status']);
  }

  /**
   * Tests that the field definition survives a change of the field value.
   */
  protected function doTestFieldDefinitionSurvivesSet(): void {
    $entity = $this->createContractEntity(['field_link' => self::linkField(['uri' => 'https://example.com'])], mutable: TRUE);
    $entity->set('field_link', ['uri' => 'https://example.org']);

    $definition = $entity->get('field_link')->getFieldDefinition();
    $this->assertSame('field_link', $definition->getName());
    $this->assertSame('link', $definition->getType());
    $this->assertSame('uri', $definition->getFieldStorageDefinition()->getMainPropertyName());
  }

  /**
   * Tests changing the value of a field item with ::setValue.
   */
  protected function doTestSetFieldItemValue(): void {
    $entity = $this->createContractEntity(['field_status' => self::stringField('draft')], mutable: TRUE);

    $this->getItem($entity, 'field_status')->setValue(['value' => 'published']);

    // @phpstan-ignore property.notFound
    $this->assertSame('published', $entity->get('field_status')->value);
  }

  /**
   * Tests changing a field item property through magic property access.
   */
  protected function doTestMagicSetFieldItemProperty(): void {
    $entity = $this->createContractEntity(['field_status' => self::stringField('draft')], mutable: TRUE);

    // @phpstan-ignore property.notFound
    $this->getItem($entity, 'field_status')->value = 'published';

    // @phpstan-ignore property.notFound
    $this->assertSame('published', $entity->get('field_status')->value);
  }

  /**
   * Tests the URL of an entity.
   */
  protected function doTestToUrl(): void {
    $entity = $this->createContractEntity(id: 42, url: '/entity_test/42');

    $url = $entity->toUrl();
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(Url::class, $url);
    $this->assertSame('/entity_test/42', $url->toString());

    $generated = $url->toString(TRUE);
    // @phpstan-ignore method.alreadyNarrowedType
    $this->assertInstanceOf(GeneratedUrl::class, $generated);
    $this->assertSame('/entity_test/42', $generated->getGeneratedUrl());
  }

}
