<?php

namespace Drupal\user;

use Drupal\Core\Entity\EntityInterface;

interface EntityOwnerInterface extends EntityInterface {

  public function getOwnerId();

}
