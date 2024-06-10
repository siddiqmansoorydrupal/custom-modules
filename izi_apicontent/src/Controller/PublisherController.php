<?php

namespace Drupal\izi_apicontent\Controller;

use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Triquanta\IziTravel\DataType\MultipleFormInterface;

/**
 *
 */
class PublisherController extends IziApicontentController {

  /**
   * @Route("/browse/{uuid}", methods="GET", name="izi_apicontent.browse.publishers")
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object.
   *
   * @param string $uuid
   *   The uuid retrieved from the URL.
   *
   * @param string $language
   *
   * @return mixed[]
   *   A Drupal render array.
   */
  public function show(Request $request, string $uuid, string $language) {
    try {
      /** @var \Triquanta\IziTravel\DataType\FullPublisherInterface $object */
      $object = $this->object_service->loadObject(
        $uuid,
        IZI_APICONTENT_TYPE_PUBLISHER,
        MultipleFormInterface::FORM_FULL
      );
    }
    catch (IziLibiziNotFoundException $e) {
      throw new NotFoundHttpException();
    }
    return $this->izi_apicontent_page_view($object, $language);
  }

  /**
   * Publisher content AJAX load more page callback.
   *
   * @param string $uuid
   *   The publisher's UUID.
   * @param int $offset
   *   The offset of the batch of content items retrieved.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Drupal render array with publisher content (and read more link if
   *   needed).
   *
   * @throws \Exception
   */
  public function izi_apicontent_publisher_ajax_load_more(string $uuid, int $offset) {
    // Get content.
    $limit = IZI_APICONTENT_PUBLISHER_CONTENT_AMOUNT + 1;

    $preferred_content_language = $this->language_service->get_preferred_language();
    if ($preferred_content_language == IZI_APICONTENT_LANGUAGE_ANY) {
      $request_languages = izi_apicontent_get_preferred_content_languages();
    }
    else {
      $request_languages = [$preferred_content_language];
    }

    $publisher_content = $this->object_service->izi_apicontent_publisher_content_load($uuid, $limit, $offset, $request_languages);
    // Check if we need a load more link.
    $load_more = FALSE;
    if (count($publisher_content) == $limit) {
      array_pop($publisher_content);
      $load_more = TRUE;
    }

    $html = \Drupal::service('renderer')->renderRoot($this->izi_apicontent_build_publisher_content($publisher_content));

    // Deliver JSON.
    return new JsonResponse([
      'results' => $html,
      'load_more' => $load_more,
      'offset' => $offset + IZI_APICONTENT_PUBLISHER_CONTENT_AMOUNT,
    ]);
  }

}
