<?php

declare(strict_types=1);

namespace Deuteros\Tests\Contract\Kernel;

use Deuteros\Tests\Contract\ContractField;
use Deuteros\Tests\Contract\EntityContractTestTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Runs the entity contract against real entities with real field items.
 *
 * This is the reference implementation of the contract: the double contract
 * tests confirm that entity doubles behave the same way.
 */
#[Group('deuteros')]
#[Group('contract')]
#[RunTestsInSeparateProcesses]
class EntityKernelContractTest extends EntityKernelTestBase {

  use EntityContractTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['datetime', 'datetime_range', 'filter', 'link', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    EntityTestHelper::createBundle(self::BUNDLE);
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * {@inheritdoc}
   */
  protected function createContractEntity(array $fields = [], int|string|null $id = NULL, ?string $uuid = NULL, ?string $label = NULL, ?string $url = NULL, bool $mutable = FALSE): FieldableEntityInterface {
    $values = ['type' => self::BUNDLE];
    foreach (['id' => $id, 'uuid' => $uuid, 'name' => $label] as $key => $value) {
      if ($value !== NULL) {
        $values[$key] = $value;
      }
    }

    foreach ($fields as $fieldName => $field) {
      $this->createField($fieldName, $field);
      $values[$fieldName] = $field->value;
    }

    return EntityTest::create($values);
  }

  /**
   * {@inheritdoc}
   */
  protected function createReferencedEntity(?int $id, string $label): EntityInterface {
    $entity = EntityTest::create(['type' => self::BUNDLE, 'name' => $label]);
    if ($id !== NULL) {
      $entity->set('id', $id);
      $entity->save();
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function resetContractState(): void {
    $storage = $this->entityTypeManager->getStorage(self::ENTITY_TYPE_ID);
    $storage->delete($storage->loadMultiple());

    foreach (FieldStorageConfig::loadMultiple() as $fieldStorage) {
      $fieldStorage->delete();
    }
  }

  /**
   * Creates a configurable field on the contract bundle.
   *
   * @param string $fieldName
   *   The field name.
   * @param \Deuteros\Tests\Contract\ContractField $field
   *   The field description.
   */
  private function createField(string $fieldName, ContractField $field): void {
    if (FieldConfig::loadByName(self::ENTITY_TYPE_ID, self::BUNDLE, $fieldName) !== NULL) {
      return;
    }

    if ($field->itemClass !== '') {
      $definition = $this->container->get('plugin.manager.field.field_type')->getDefinition($field->type);
      $this->assertIsArray($definition);
      $this->assertSame($field->itemClass, $definition['class'], sprintf('The item class of the "%s" contract field matches its field type.', $fieldName));
    }

    FieldStorageConfig::create([
      'field_name' => $fieldName,
      'entity_type' => self::ENTITY_TYPE_ID,
      'type' => $field->type,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => $field->settings,
    ])->save();

    FieldConfig::create([
      'field_name' => $fieldName,
      'entity_type' => self::ENTITY_TYPE_ID,
      'bundle' => self::BUNDLE,
    ])->save();
  }

}
