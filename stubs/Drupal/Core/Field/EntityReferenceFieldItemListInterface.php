<?php

namespace Drupal\Core\Field;

interface EntityReferenceFieldItemListInterface extends FieldItemListInterface {

  public function referencedEntities();

}
