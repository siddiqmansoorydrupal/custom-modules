<?php

namespace Drupal\izi_apicontent;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use Triquanta\IziTravel\DataType\FullCollectionInterface;
use Triquanta\IziTravel\DataType\FullExhibitInterface;
use Triquanta\IziTravel\DataType\FullTouristAttractionInterface;
use Triquanta\IziTravel\DataType\MtgObjectInterface;

/**
 * Provides a breadcrumb builder for articles.
 */
class IziApicontentBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;


  /**
   * The IziObjectService service.
   *
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected IziObjectService $object_service;

  /**
   * The IziObjectService service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected LanguageService $language_service;

  /**
   * MyModuleTermBreadcrumbBuilder constructor.
   *
   * @param \Drupal\izi_apicontent\IziObjectService $object_service
   * @param \Drupal\izi_apicontent\LanguageService $language_service
   */
  public function __construct(
    IziObjectService $object_service,
    LanguageService $language_service,
  ) {
    $this->object_service = $object_service;
    $this->language_service = $language_service;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // Add conditionals that return TRUE when the current route should have it's
    // breadcrumb handled here.
    $route_name = \Drupal::routeMatch()->getRouteName();
    return $route_name === 'izi_apicontent.browse';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    // Set up existing query parameters for breadcrumb.
    // $options = $route_match->getParameters()->all();
    // Adds a class name, following Yannics class names, for each breadcrumb item.
    $options['attributes'] = ['class' => 'breadcrumbs__link'];

    // First element: home.
    $site_name = \Drupal::config('system.site')->get('name') ?? 'Home';
    $links[] = Link::createFromRoute(
      $this->t($site_name),
      '<front>',
      [],
      $options,
    );

    // Truncate length.
    $max_length = 25;

    // Load mtg object by uuid.
    $uuid = $route_match->getParameter('uuid');

    try {
      $object = $this->object_service->loadObject(
        $uuid,
        IZI_APICONTENT_TYPE_MTG_OBJECT
      );
    }
    catch (IziLibiziNotFoundException $e) {
      // We don't launch an exception to avoid unnecessary 404 errors
      // caused because of breadcrumbs. See IZT-2114.
      $object = NULL;
    }

    // If the object is a collection, we start as if we're building a breadcrumb
    // for its parent.
    $collection = FALSE;
    if ($object instanceof FullExhibitInterface ||
      $object instanceof FullTouristAttractionInterface ||
      $object instanceof FullCollectionInterface
    ) {
      // Store the collection object for later.
      $collection = clone $object;
      // Overwrite the object with the parent.
      $parent_uuid = $object->getParentUuid();
      if ($parent_uuid) {
        try {
          $object = $this->object_service->loadObject($parent_uuid, IZI_APICONTENT_TYPE_MTG_OBJECT);
        }
        catch (IziLibiziNotFoundException $e) {
          // We don't launch an exception to avoid unnecessary 404 errors
          // caused because of breadcrumbs. See IZT-2114.
          $object = NULL;
        }
      }
      // Else {.
      // @todo (legacy) Needs to be fixed later. No priority now, Redmine #18788.
      //   }
    }

    // If it is an mtg object, or an "all guides in specific city overview".
    // Next two elements: country and city.
    if (
      $object instanceof MtgObjectInterface
      || ($object === NULL && (!empty($city) || !empty($country)))
    ) {
      $interface_langcode = $this->language_service->get_interface_language();
      $country_uuid = NULL;
      $city_uuid = NULL;
      $country_name = "";
      $city_name = "";
      if (!empty($city)) {
        $country_uuid = $city->getLocation()->getCountryUuid();
        $country_name = $this->object_service->getCountryNameByUuid($country_uuid, $interface_langcode);
        $links[] = $this->izi_apicontent_link(
          $country_name,
          $country_uuid,
          IZI_APICONTENT_TYPE_COUNTRY,
          $options,
          $interface_langcode
        );
      }
      elseif (!empty($country)) {
        $country_uuid = $country->getUuid();
        $country_name = $this->object_service->getCountryNameByUuid($country_uuid, $interface_langcode);
        $links[] = $this->izi_apicontent_link(
          $country_name,
          $country_uuid,
          IZI_APICONTENT_TYPE_COUNTRY,
          $options,
          $interface_langcode
        );
      }
      else {
        $country_location = $object->getLocation();
        if (!empty($country_location)) {
          $country_uuid = $country_location->getCountryUuid();
          if ($country_uuid) {
            try {
              $country_name = $this->object_service->getCountryNameByUuid($country_uuid, $interface_langcode);
              $links[] = $this->izi_apicontent_link(
                $country_name,
                $country_uuid,
                IZI_APICONTENT_TYPE_COUNTRY,
                $options,
                $interface_langcode
              );
            }
            catch (\Exception $e) {
              \Drupal::logger('izi_apicontent')->error("IziApicontentBreadcrumbBuilder: $uuid Missing city $country_uuid");
            }
          }
        }

        $city_location = $object->getLocation();
        if (!empty($city_location)) {
          $city_uuid = $city_location->getCityUuid();
          if ($city_uuid) {
            try {
              $city_name = $this->object_service->getCityNameByUuid($city_uuid, $interface_langcode);
              $links[] = $this->izi_apicontent_link(
                $city_name,
                $city_uuid,
                IZI_APICONTENT_TYPE_CITY,
                $options,
                $interface_langcode
              );
            }
            catch (\Exception $e) {
              // We don't launch an exception to avoid unnecessary 404 errors
              // caused because of breadcrumbs. See IZT-2114.
              \Drupal::logger('izi_apicontent')->error("IziApicontentBreadcrumbBuilder: $uuid Missing city $city_uuid");
            }
          }
        }

        if ($collection) {
          // Current page object.
          $parent_content = $this->object_service->get_object_language_content($object->getContent());
          $parent_title = Unicode::truncate(Xss::filter($parent_content->getTitle()), $max_length, TRUE, TRUE);
          $links[] = $this->izi_apicontent_link($parent_title, $object->getUuid(), IZI_APICONTENT_TYPE_MTG_OBJECT, $options);

          // Last element: collection_object (no link).
          $content = $this->object_service->get_object_language_content($collection->getContent());
          $title = Unicode::truncate(Xss::filter($content->getTitle()), $max_length, TRUE, TRUE);
          $links[] = Link::createFromRoute($title, '<none>');
        }
        else {
          // Last element: current page object (no link).
          $content = $this->object_service->get_object_language_content($object->getContent());
          $title = Unicode::truncate(Xss::filter($content->getTitle()), $max_length, TRUE, TRUE);
          $links[] = Link::createFromRoute($title, '<none>');
        }
      }
      $breadcrumb->setLinks($links);
    }

    // Cache breadcrumb by path otherwise it will cache across custom routes.
    $breadcrumb->addCacheContexts(
      ['url.path']
    );

    return $breadcrumb;
  }

  /**
   * @param string $text
   *   The link text.
   * @param string|\Triquanta\IziTravel\DataType\UuidInterface $object
   *   The uuid of an API content object or the object itself.
   * @param string $type
   *   The type of object.
   * @param mixed[] $options
   *   An array of options as accepted by the url() function.
   * @param string|null $language
   *   The uuid of an API content object or the object itself.
   *
   * @return \Drupal\Core\Link
   *   A link for an API object page.
   */
  private function izi_apicontent_link(
    $text,
    $object,
    $type = IZI_APICONTENT_TYPE_MTG_OBJECT,
    $options = [],
    $interface_lancode = ""
  ) {
    $path = $this->object_service->izi_apicontent_path($object, $type, $interface_lancode);
    $alias = \Drupal::service('path_alias.manager')
      ->getAliasByPath($path);
    $site_langcode = $this->language_service->get_interface_language();
    $url = Url::fromUri("base:{$site_langcode}{$alias}", $options);
    $link = Link::fromTextAndUrl(
      $text,
      $url
    );
    return $link;
  }

}
