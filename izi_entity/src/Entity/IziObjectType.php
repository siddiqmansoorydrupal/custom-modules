<?php

namespace Drupal\izi_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the IZI Object type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "izi_object_type",
 *   label = @Translation("IZI Object type"),
 *   label_collection = @Translation("IZI Object types"),
 *   label_singular = @Translation("izi object type"),
 *   label_plural = @Translation("izi objects types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count izi objects type",
 *     plural = "@count izi objects types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\izi_entity\Form\IziObjectTypeForm",
 *       "edit" = "Drupal\izi_entity\Form\IziObjectTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\izi_entity\IziObjectTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer izi object types",
 *   bundle_of = "izi_object",
 *   config_prefix = "izi_object_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/izi_object_types/add",
 *     "edit-form" = "/admin/structure/izi_object_types/manage/{izi_object_type}",
 *     "delete-form" = "/admin/structure/izi_object_types/manage/{izi_object_type}/delete",
 *     "collection" = "/admin/structure/izi_object_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class IziObjectType extends ConfigEntityBundleBase {

  /**
   * The machine name of this izi object type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the izi object type.
   *
   * @var string
   */
  protected $label;

}
