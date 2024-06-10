<?php

namespace Drupal\izi_search;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use Triquanta\IziTravel\DataType\MultipleFormInterface;

/**
 * Provides a breadcrumb builder for articles.
 */
class IziSearchBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * The IziObjectService service.
   *
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected IziObjectService $object_service;

  /**
   * MyModuleTermBreadcrumbBuilder constructor.
   *
   * @param \Drupal\izi_apicontent\IziObjectService $object_service
   */
  public function __construct(
    IziObjectService $object_service,
  ) {
    $this->object_service = $object_service;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    return $route_name === 'izi_search.country'
      || $route_name === 'izi_search.city';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $route_name = $route_match->getRouteName();

    $links[] = Link::createFromRoute($this->t('izi.TRAVEL'), '<front>');

    // Set the title and search string for city and country 'search'.
    $includes = ['country', 'city', 'translations'];
    if ($route_name == 'izi_search.city') {
      $uuid = $route_match->getParameter('city');
      try {
        /** @var \Triquanta\IziTravel\DataType\CompactCityInterface $city */
        $city = $this->object_service->loadObject($uuid, IZI_APICONTENT_TYPE_CITY, MultipleFormInterface::FORM_COMPACT, $includes);

        $location = $city->getLocation();
        $countryUuid = $location->getCountryUuid();
        /** @var \Triquanta\IziTravel\DataType\CompactCountryInterface $country */
        $country = $this->object_service->loadObject($countryUuid, IZI_APICONTENT_TYPE_COUNTRY, MultipleFormInterface::FORM_COMPACT, $includes);
        $links[] = Link::createFromRoute($this->t($country->getTitle()), 'izi_search.country', ['country' => $countryUuid]);
      }
      catch (IziLibiziNotFoundException $e) {
        // We don't launch an exception to avoid unnecessary 404 errors
        // caused because of breadcrumbs.
      }
    }

    if ($route_name == 'izi_search.country') {
      $uuid = $route_match->getParameter('country');
      try {
        /** @var \Triquanta\IziTravel\DataType\CompactCountryInterface $country */
        $country = $this->object_service->loadObject($uuid, IZI_APICONTENT_TYPE_COUNTRY, MultipleFormInterface::FORM_COMPACT, $includes);
        $links[] = Link::createFromRoute($this->t($country->getTitle()), 'izi_search.country', ['country' => $uuid]);
      }
      catch (IziLibiziNotFoundException $e) {
        // We don't launch an exception to avoid unnecessary 404 errors
        // caused because of breadcrumbs.
      }
    }

    $request = \Drupal::request();
    $title = \Drupal::service('title_resolver')->getTitle($request, $route_match->getRouteObject());
    $title = Unicode::truncate(Html::decodeEntities($title), 25, TRUE, TRUE);
    $links[] = Link::createFromRoute($this->t('@title', ['@title' => $title]), '<none>');

    $breadcrumb->setLinks($links);

    // Cache breadcrumb by path otherwise it will cache across custom routes.
    $breadcrumb->addCacheContexts(
      ['url.path']
    );

    return $breadcrumb;
  }

}
