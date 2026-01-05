<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test config entity for SubjectEntityFactory tests.
 *
 * Provides a minimal config entity class for testing entity instantiation
 * with mocked services. Config entities do not have field doubles since they
 * do not implement "FieldableEntityInterface".
 */
#[ConfigEntityType(
  id: 'test_config',
  label: new TranslatableMarkup('Test Config Entity'),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'status' => 'status',
  ],
)]
class TestConfigEntity extends ConfigEntityBase {

  /**
   * The entity ID.
   */
  protected ?string $id = NULL;

  /**
   * The entity label.
   */
  protected ?string $label = NULL;

  /**
   * A custom property for testing.
   */
  public ?string $description = NULL;

  /**
   * Another custom property for testing.
   */
  public int $weight = 0;

}
