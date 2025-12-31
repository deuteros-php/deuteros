<?php

namespace Drupal\Core\Entity;

interface FieldableEntityInterface extends EntityInterface, \Traversable {

  public function hasField($field_name);
  public function get($field_name);
  public function set($field_name, $value, $notify = TRUE);

}
