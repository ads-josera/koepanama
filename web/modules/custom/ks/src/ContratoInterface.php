<?php

namespace Drupal\ks;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a contrato entity type.
 */
interface ContratoInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
