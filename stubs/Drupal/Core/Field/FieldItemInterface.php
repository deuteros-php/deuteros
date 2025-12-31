<?php

namespace Drupal\Core\Field;

interface FieldItemInterface {

  public function __get($property_name);
  public function __set($property_name, $value);
  public function getValue();
  public function setValue($values, $notify = TRUE);
  public function isEmpty();

}
