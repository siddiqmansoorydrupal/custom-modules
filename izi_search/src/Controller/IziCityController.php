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
class IziCityController extends BaseController {

  /**
   *
   */
  public function getTitle(Request $request) {
    $uuid = $request->get('city');
    try {
      /** @var \Triquanta\IziTravel\DataType\CompactCityInterface $city */
      $city = $this->object_service->loadObject(
        $uuid,
        IZI_APICONTENT_TYPE_CITY,
        MultipleFormInterface::FORM_COMPACT,
        ['country', 'city', 'translations']
      );
    }
    catch (IziLibiziNotFoundException $e) {
      throw new NotFoundHttpException();
    }
    $city_name_translated = \Drupal::service('izi_search.izi_search_service')
      ->izi_search_country_city_translated_title($city);

    switch ($request->get('filter_type')) {
      case 'all':
        $title = izi_metatag_format_string_from_variable(
          'izi_metatag_city_all_title',
          ['@city' => $city_name_translated]
        );
        break;

      case MtgObjectInterface::TYPE_TOUR:
        $title = izi_metatag_format_string_from_variable(
          'izi_metatag_city_tour_title',
          ['@city' => $city_name_translated]
        );
        break;

      case MtgObjectInterface::TYPE_MUSEUM:
        $title = izi_metatag_format_string_from_variable(
          'izi_metatag_city_museum_title',
          ['@city' => $city_name_translated]
        );
        break;

      case 'quest':
        $title = izi_metatag_format_string_from_variable(
          'izi_metatag_city_quest_title',
          ['@city' => $city_name_translated]
        );
        break;
    }
    return Xss::filter($title);
  }

  /**
   * Builds the response.
   */
  public function build($city, $filter_type = 'all', $filter_lang = 'all') {
    return $this->izi_search_page_results_view("city", $city, $filter_type, $filter_lang);
  }

}
