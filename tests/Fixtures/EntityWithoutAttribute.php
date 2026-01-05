<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * Test content entity without ContentEntityType attribute.
 *
 * Used to test that EntityTestHelper properly validates entity class
 * attributes.
 */
class EntityWithoutAttribute extends ContentEntityBase {

}
