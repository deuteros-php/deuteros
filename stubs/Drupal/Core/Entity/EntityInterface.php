<?php

namespace Drupal\Core\Entity;

interface EntityInterface {

  public function uuid();
  public function id();
  public function getEntityTypeId();
  public function bundle();
  public function label();
  public function save();
  public function delete();

}
