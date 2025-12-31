<?php

namespace Drupal\Core\Field;

interface FieldItemListInterface extends \Traversable, \Countable {

  public function first();
  public function isEmpty();
  public function getValue();
  public function get($delta);
  public function __get($property_name);
  public function __set($property_name, $value);
  public function setValue($values, $notify = TRUE);

}
