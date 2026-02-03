<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Final content entity for testing URL parameter with final classes.
 *
 * Provides a final content entity class to test that SubjectEntityFactory
 * throws a clear exception when trying to use the URL parameter with final
 * entity classes (PHP doesn't allow extending final classes).
 */
#[ContentEntityType(
  id: 'final_entity',
  label: new TranslatableMarkup('Final Entity'),
  entity_keys: [
    'id' => 'id',
    'bundle' => 'type',
    'label' => 'name',
    'uuid' => 'uuid',
    'langcode' => 'langcode',
  ],
)]
final class FinalContentEntity extends ContentEntityBase {

}
