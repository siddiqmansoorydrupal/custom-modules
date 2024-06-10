<?php

namespace Drupal\izi_credit\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\user\UserInterface;

/**
 * Defines the Credit entity.
 *
 * @ContentEntityType(
 *   id = "credit",
 *   label = @Translation("Credit"),
 *   base_table = "credit",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "user_id" = "user_id",
 *     "amount" = "amount",
 *   },
 *   links = {
 *     "canonical" = "/credit/{credit}",
 *     "add-form" = "/credit/add",
 *     "edit-form" = "/credit/{credit}/edit",
 *     "delete-form" = "/credit/{credit}/delete"
 *   }
 * )
 */
class Credit extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setSetting('target_type', 'user');

    $fields['amount'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Amount'))
      ->setSetting('scale', 2);

    return $fields;
  }
}
