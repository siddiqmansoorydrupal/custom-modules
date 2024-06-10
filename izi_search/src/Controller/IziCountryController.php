<?php

namespace Drupal\izi_search\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Triquanta\IziTravel\DataType\MtgObjectInterface;
use Triquanta\IziTravel\DataType\MultipleFormInterface;

/**
 * Returns responses for izi_search routes.
 */
class IziCountryController extends BaseController {

  /**
   *
   */
  public function getTitle(Request $request): string {
    $uuid = $request->get('country');
    try {
      /** @var \Triquanta\IziTravel\DataType\CompactCountryInterface $country */
      $country = $this->object_service->loadObject(
        $uuid,
        IZI_APICONTENT_TYPE_COUNTRY,
        MultipleFormInterface::FORM_COMPACT,
        ['country', 'city', 'translations']
      );
    }
    catch (IziLibiziNotFoundException $e) {
      throw new NotFoundHttpException();
    }
    $langcode = \Drupal::service('izi_apicontent.language_service')
      ->get_interface_language();
    $country_name_translated = $this->object_service->getCountryNameByUuid($country->getUuid(), $langcode);

    switch ($request->get('filter_type')) {
      case 'all':
        $title = izi_metatag_format_string_from_variable(
          'izi_metatag_country_all_title',
          ['@country' => $country_name_translated]
        );
        break;

      case MtgObjectInterface::TYPE_TOUR:
        $title = izi_metatag_format_string_from_variable(
          'izi_metatag_country_tour_title',
          ['@country' => $country_name_translated]
        );
        break;

      case MtgObjectInterface::TYPE_MUSEUM:
        $title = izi_metatag_format_string_from_variable(
          'izi_metatag_country_museum_title',
          ['@country' => $country_name_translated]
        );
        break;

      case 'quest':
        $title = izi_metatag_format_string_from_variable(
          'izi_metatag_country_quest_title',
          ['@country' => $country_name_translated]
        );
        break;
    }

    return Xss::filter($title);
  }

  /**
   * Builds the response.
   */
  public function build($country, $filter_type = 'all', $filter_lang = 'all') {
    return $this->izi_search_page_results_view("country", $country, $filter_type, $filter_lang);
  }

}
