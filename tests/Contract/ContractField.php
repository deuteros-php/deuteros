<?php

declare(strict_types=1);

namespace Deuteros\Tests\Contract;

/**
 * Describes a field of a contract entity independently of its implementation.
 *
 * The Kernel implementation turns it into a real field storage and field, the
 * double implementations into a "FieldDoubleDefinition". The value is passed
 * unchanged to both, in any format "ContentEntityBase::create" accepts.
 */
final readonly class ContractField {

  /**
   * Constructs a ContractField.
   *
   * @param string $type
   *   The Drupal field type, e.g. "string" or "entity_reference".
   * @param mixed $value
   *   The raw field value.
   * @param array<string, mixed> $settings
   *   The field storage settings.
   * @param string $itemClass
   *   The field item class of the field type. The double implementations pass
   *   it to Deuteros to name the main property; the Kernel implementation
   *   checks it against the class of the real field type.
   */
  public function __construct(
    public string $type,
    public mixed $value,
    public array $settings = [],
    public string $itemClass = '',
  ) {}

}
