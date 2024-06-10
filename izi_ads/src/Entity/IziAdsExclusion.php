<?php

namespace Drupal\izi_ads\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\izi_ads\IziAdsExclusionInterface;

/**
 * Defines the izi ads exclusion entity type.
 *
 * @ConfigEntityType(
 *   id = "izi_ads_exclusion",
 *   label = @Translation("IZI Ads Exclusion"),
 *   label_collection = @Translation("IZI Ads Exclusions"),
 *   label_singular = @Translation("izi ads exclusion"),
 *   label_plural = @Translation("izi ads exclusions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count izi ads exclusion",
 *     plural = "@count izi ads exclusions",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\izi_ads\IziAdsExclusionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\izi_ads\Form\IziAdsExclusionForm",
 *       "edit" = "Drupal\izi_ads\Form\IziAdsExclusionForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "izi_ads_exclusion",
 *   admin_permission = "administer izi_ads_exclusion",
 *   links = {
 *     "collection" = "/admin/structure/izi-ads-exclusion",
 *     "add-form" = "/admin/structure/izi-ads-exclusion/add",
 *     "edit-form" = "/admin/structure/izi-ads-exclusion/{izi_ads_exclusion}",
 *     "delete-form" = "/admin/structure/izi-ads-exclusion/{izi_ads_exclusion}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "path",
 *     "expires",
 *   }
 * )
 */
class IziAdsExclusion extends ConfigEntityBase implements IziAdsExclusionInterface {

  /**
   * The izi ads exclusion ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The izi ads exclusion label.
   *
   * @var string
   */
  protected $label;

  /**
   * The izi ads path.
   *
   * @var string
   */
  protected $path;

  /**
   * The izi ads expire date.
   *
   * @var string
   */
  protected $expires;

  /**
   * The izi ads exclusion status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The izi_ads_exclusion description.
   *
   * @var string
   */
  protected $description;

  /**
   *
   */
  public function getPath() {
    return $this->path;
  }

  /**
   *
   */
  public function getExpires() {
    return $this->expires;
  }

}
