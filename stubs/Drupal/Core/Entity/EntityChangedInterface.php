<?php

namespace Drupal\Core\Entity;

interface EntityChangedInterface extends EntityInterface {

  public function getChangedTime();
  public function setChangedTime($timestamp);

}
