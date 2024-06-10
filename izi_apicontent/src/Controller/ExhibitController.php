<?php

namespace Drupal\izi_apicontent\Controller;

use Drupal\Core\Logger\LoggerChannelTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 */
class ExhibitController extends IziApicontentController {

  use LoggerChannelTrait;

  /**
   * AJAX menu callback to render the content of one exhibit.
   *
   * @param $uuid
   * @param $lang
   *
   * @return string
   */
  public function izi_apicontent_get_exhibit_details($uuid = FALSE, $lang = NULL) {
    $details = '';

    // Get content for stored tour details.
    if ($uuid && $wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri(self::CHILD_CACHE_DESTINATION)) {

      $path = $wrapper->realpath() . '/' . $uuid . '_' . $lang . '.html';
      if (file_exists($path)) {
        $details = file_get_contents($path);
      }
      else {
        $this->getLogger('izi_apicontent')
          ->error("
          Exhibit object not found. ExhibitController $uuid");
      }
    }

    $response = new Response();
    return $response->setContent($details);
  }

}
