<?php

namespace Drupal\izi_urlalias\Plugin\QueueWorker;

use Drupal\Core\Language\LanguageInterface;
use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use Triquanta\IziTravel\DataType\CompactPublisher;
use Triquanta\IziTravel\DataType\MtgObjectInterface;
use Triquanta\IziTravel\DataType\MultipleFormInterface;

/**
 * Defines 'izi_urlalias_tour_alias' queue worker.
 *
 * @QueueWorker(
 *   id = "izi_urlalias_tour_alias",
 *   title = @Translation("Tour Alias Worker"),
 *   cron = {"time" = 60}
 * )
 */
class TourAlias extends IziQueueWorkerBase {

  public const QUEUE_NAME = 'izi_urlalias_tour_alias';

  /**
   *
   */
  public function processItem($data) {
    $this->izi_urlalias_update_tour_alias($data);
  }

  /**
   *
   */
  protected function izi_urlalias_update_tour_alias(MtgObjectInterface $object) {
    $publishers = [];

    // Add or update the URL alias for this object.
    $this->izi_urlalias_update_url_alias($object);

    // Descend into the related content and add/update URL alias for each child.
    /** @var \Triquanta\IziTravel\DataType\ContentInterface $content */
    $content = $object->getContent()[0];
    $children = $content->getChildren();
    foreach ($children as $child) {
      $this->izi_urlalias_update_url_alias($child);
    }

    // Collect publishers UUIDs for later processing.
    // Publishers data is only in one language. For simplicity, we use the
    // first of the available languages.
    $publisher_uuid = $object->getPublisher()->getUuid();
    $langcodes = $object->getPublisher()->getAvailableLanguageCodes();
    $publisher_langcode = reset($langcodes);
    $publishers[] = "$publisher_uuid:$publisher_langcode";

    // Log: finished processing MTG object.
    $this->logger->debug('Finished processing MTG @uuid and it\'s children', ['@uuid' => $object->getUuid()]);

    // Process publisher data.
    if ($publishers) {
      $publishers = array_unique($publishers);

      // Publishers that were processed earlier in this batch will be excluded.
      $verified = izi_urlalias_get_status_multiple($publishers, 1);
      if ($verified) {
        $publishers = array_diff($publishers, array_keys((array) $verified));
      }

      // Load new and unprocessed publishers and update their URL alias.
      foreach ($publishers as $id) {
        [$uuid] = explode(':', $id);

        // Log: start processing publisher.
        $this->logger->debug('IZI Url Alias - Publisher - Processing publisher @uuid', ['@uuid' => $uuid]);

        try {
          $publisherObject = $this->iziObjects->loadObject($uuid, IZI_APICONTENT_TYPE_PUBLISHER, MultipleFormInterface::FORM_COMPACT);
          usleep(self::IZI_URLALIAS_API_SLEEP);
        }
        catch (IziLibiziNotFoundException $e) {
          continue;
        }

        // Add or update the URL alias for the fetched object.
        $this->izi_urlalias_update_publisher_url_alias($publisherObject);

        // Log: finished processing MTG object.
        $this->logger->debug('Finished processing publisher @uuid', ['@uuid' => $uuid]);
      }
    }
  }

  /**
   *
   */
  public function izi_urlalias_update_url_alias(MtgObjectInterface $object) {

    try {
      // Try to get the subtype.
      // If the subtype could not be determined, no url-alias should be made.
      $subtype = $this->iziObjects->izi_apicontent_get_sub_type($object);
    }
    catch (\Exception $e) {
      $subtype = FALSE;

      // Log: delete alias.
      $this->logger->debug('Alias deleted: Object not found in API, aliases for /browse/@uuid will be deleted', ['@uuid' => $uuid]);

      // The alias needs to be deleted.
      // Set deleted to 2, causes an immediate delete,
      // rather than the day that is normally requested.
      izi_urlalias_status_delete($object->getUuid());
    }

    if ($subtype) {
      $uuid = $object->getUuid();
      // If the object is new or updated, create a new alias.
      $status = izi_urlalias_get_status($uuid, $object->getLanguageCode());
      $revision_hash = $object->getRevisionHash();
      if (!$status || $status->hash != $revision_hash) {
        $url = $this->izi_urlalias_MtgObject_clean_url($object);
        $source = '/browse/' . $uuid . '/' . $object->getLanguageCode();
        $indexable_subtypes = [
          'tour',
          'museum',
          'collection',
        ];

        if (in_array($subtype, $indexable_subtypes)) {
          $xmlsitemap_language = izi_urlalias_izi_to_drupal_language($object->getLanguageCode());
          $langcodes = array_keys(\Drupal::languageManager()->getLanguages());
          if (in_array($xmlsitemap_language, $langcodes)) {
            // Tours, museums and/or collections with a language included in Drupal interface
            // They will be included in the sitemap of its language.
            izi_urlalias_update_alias($uuid, 'tour', $source, $url, $xmlsitemap_language, TRUE);
          }
          else {
            // Tours, museums and/or collections with a language not included in Drupal interface, like japanese
            // They will be included in "other languages" sitemap.
            izi_urlalias_update_alias($uuid, 'tour', $source, $url, 'en', TRUE);
          }
        }
        else {
          // Exhibits and tourist attractions
          // They won't be included in any sitemap.
          izi_urlalias_update_alias($uuid, 'tour', $source, $url, LanguageInterface::LANGCODE_NOT_SPECIFIED, FALSE);
        }
      }

      izi_urlalias_status_set_verified($uuid, $object->getLanguageCode(), $object->getRevisionHash());
    }
  }

  /**
   * Adds or updates a URL alias for one Publisher object.
   *
   * The alias is updated when the object's revision has changed.
   *
   * @param \Triquanta\IziTravel\DataType\CompactPublisher $object
   *   Publisher.
   */
  protected function izi_urlalias_update_publisher_url_alias(CompactPublisher $object): void {
    // If the object is new or updated, create a new alias.
    $status = izi_urlalias_get_status($object->getUuid(), $object->getLanguageCode());
    $revision_hash = $object->getRevisionHash();
    if (!$status || $status->hash != $revision_hash) {
      $uuid = $object->getUuid();
      $url = $this->izi_urlalias_publisher_clean_url($object);
      $source = '/browse/publishers/' . $object->getUuid();
      // Publishers will be saved without specific language.
      izi_urlalias_update_alias($uuid, 'publisher', $source, $url);
    }

    izi_urlalias_status_set_verified($object->getUuid(), $object->getLanguageCode(), $object->getRevisionHash());
  }

  /**
   * Constructs a human-readable URL alias for MtgObject content.
   *
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface $object
   *   The Mtg content object.
   *
   * @return string
   *   A url for the object.
   */
  protected function izi_urlalias_MtgObject_clean_url(MtgObjectInterface $object): string {
    $title = _izi_urlalias_prepare_url_string($object->getTitle());
    $hash = _izi_urlalias_prepare_uuid($object->getUuid());
    $langcode = $object->getLanguageCode();
    return "$hash-$title/$langcode";
  }

  /**
   * Constructs a human readable URL alias for Publisher content.
   *
   * @param \Triquanta\IziTravel\DataType\CompactPublisher $object
   *   The publisher content object.
   *
   * @return string
   *   An url for the object.
   */
  protected function izi_urlalias_publisher_clean_url(CompactPublisher $object): string {
    $title = _izi_urlalias_prepare_url_string($object->getTitle());
    $hash = _izi_urlalias_prepare_uuid($object->getUuid());
    return "$hash-$title";
  }

}
