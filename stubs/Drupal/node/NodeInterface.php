<?php

namespace Drupal\node;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

interface NodeInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

  public function getTitle();
  public function getCreatedTime();
  public function isPromoted();
  public function isSticky();

}
