<?php

namespace Drupal\izi_apicontent\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Triquanta\IziTravel\DataType\MultipleFormInterface;

/**
 *
 */
class MtgObjectController extends IziApicontentController {

  /**
   * @Route("/browse/{uuid}", methods="GET", name="izi_apicontent.browse")
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
   *
   * @throws \Exception
   */
  public function show(Request $request, string $uuid, string $language) {
    // Include city, country and translations in order to build the related content block properly.
    $includes = IZI_APICONTENT_TYPE_MTG_OBJECT_INCLUDES;
    /** @var \Triquanta\IziTravel\DataType\FullMtgObjectInterface $object */
    try {
      $object = $this->object_service->loadObject(
        $uuid,
        IZI_APICONTENT_TYPE_MTG_OBJECT,
        MultipleFormInterface::FORM_FULL,
        $includes
      );
    }
    catch (IziLibiziNotFoundException $e) {
      throw new NotFoundHttpException();
    }
    catch (\Exception $e) {
      // If the exception is anything other than not found, we log the UUID but
      // display not found to the user.
      $this->logger->error("MtgObjectController: Unable to load object on page with UUID $uuid");
      throw new NotFoundHttpException();
    }
    return $this->izi_apicontent_page_view($object, $language);
  }

  /**
   *
   * @param string $uuid
   *   The uuid retrieved from the URL.
   *
   * @throws \Exception
   */
  public function getTitle(string $uuid): string {
    $object = $this->object_service->loadObjectByUUID($uuid, IZI_APICONTENT_TYPE_MTG_OBJECT);
    if (!$object) {
      return "";
    }
    $content = $this->object_service
      ->get_object_language_content($object->getContent());
    $title = $content->getTitle();
    return Xss::filter($title);
  }

}
