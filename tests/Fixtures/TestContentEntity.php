<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test content entity for EntityTestHelper tests.
 *
 * Provides a minimal content entity class for testing entity instantiation
 * with mocked services.
 */
#[ContentEntityType(
  id: 'test_entity',
  label: new TranslatableMarkup('Test Entity'),
  entity_keys: [
    'id' => 'id',
    'bundle' => 'type',
    'label' => 'name',
    'uuid' => 'uuid',
    'langcode' => 'langcode',
  ],
)]
class TestContentEntity extends ContentEntityBase {

}
