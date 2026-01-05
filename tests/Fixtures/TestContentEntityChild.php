<?php

declare(strict_types=1);

namespace Deuteros\Tests\Fixtures;

/**
 * Test content entity subclass without its own attribute.
 *
 * Used to test that "SubjectEntityFactory" properly traverses the class
 * hierarchy when looking for entity type attributes.
 */
class TestContentEntityChild extends TestContentEntity {

}
