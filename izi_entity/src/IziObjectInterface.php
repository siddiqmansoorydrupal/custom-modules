<?php

namespace Drupal\izi_entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an izi object entity type.
 */
interface IziObjectInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
