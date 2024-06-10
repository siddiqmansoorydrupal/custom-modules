<?php

namespace Drupal\izi_apicontent\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Link;
use Drupal\Core\Locale\CountryManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\izi_apicontent\HelpersService;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Drupal\izi_libizi\Exception\IziLibiziAccessDeniedException;
use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use Drupal\izi_maps\MapsService;
use Drupal\izi_reviews\ReviewsService;
use Drupal\izi_search\IziSearchService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Triquanta\IziTravel\DataType\CollectionInterface;
use Triquanta\IziTravel\DataType\CompactExhibitInterface;
use Triquanta\IziTravel\DataType\CompactMtgObjectInterface;
use Triquanta\IziTravel\DataType\CompactPublisherInterface;
use Triquanta\IziTravel\DataType\CompactTouristAttractionInterface;
use Triquanta\IziTravel\DataType\ContentInterface;
use Triquanta\IziTravel\DataType\FullCollectionInterface;
use Triquanta\IziTravel\DataType\FullExhibitInterface;
use Triquanta\IziTravel\DataType\FullMtgObjectInterface;
use Triquanta\IziTravel\DataType\FullMuseumInterface;
use Triquanta\IziTravel\DataType\FullPublisherInterface;
use Triquanta\IziTravel\DataType\FullTourInterface;
use Triquanta\IziTravel\DataType\FullTouristAttractionInterface;
use Triquanta\IziTravel\DataType\MtgObjectInterface;
use Triquanta\IziTravel\DataType\MultipleFormInterface;
use Triquanta\IziTravel\DataType\MuseumInterface;
use Triquanta\IziTravel\DataType\PaidDataInterface;
use Triquanta\IziTravel\DataType\PlaybackInterface;
use Triquanta\IziTravel\DataType\PublisherContentInterface;
use Triquanta\IziTravel\DataType\Schedule;
use Triquanta\IziTravel\DataType\StoryNavigationInterface;
use Triquanta\IziTravel\DataType\TourInterface;


use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_product\Entity\ProductVariation;

/**
 * Returns responses for Izi apicontent routes.
 */
class IziApicontentController extends ControllerBase {

  use DependencySerializationTrait;

  const CHILD_CACHE_DESTINATION = 'private://izi_content/children/';
  const PARENT_CACHE_DESTINATION = 'private://izi_content/parents/';
  
  // Define class properties instead of constants
  private $childCacheDestination;
  private $parentCacheDestination;

  /**
   * The IziObjectService service.
   *
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected IziObjectService $object_service;

  /**
   * The \Drupal\izi_apicontent\LanguageService service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected LanguageService $language_service;

  /**
   * The Drupal State API service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The Drupal State API service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected HelpersService $helpers_service;

  /**
   * The breadcrumb manager.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface
   */
  protected $breadcrumbManager;

  /**
   * Review service.
   *
   * @var \Drupal\izi_reviews\ReviewsService
   */
  protected ReviewsService $reviews_service;

  /**
   * Search service.
   *
   * @var \Drupal\izi_search\IziSearchService
   */
  protected IziSearchService $search_service;

  /**
   * Maps service.
   *
   * @var \Drupal\izi_maps\MapsService
   */
  protected MapsService $maps_service;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * ModalFormContactController constructor.
   *
   * @param \Drupal\izi_apicontent\IziObjectService $object_service
   *   IZI Object Service.
   * @param \Drupal\izi_apicontent\LanguageService $language_service
   *   IZI Language Service.
   * @param \Drupal\izi_apicontent\HelpersService $helpers_service
   *   IZI Helper Service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal State API service.
   * @param \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $breadcrumb_manager
   *   The Drupal State API service.
   * @param \Drupal\izi_reviews\ReviewsService $reviews_service
   *   IZI Reviews Service.
   * @param \Drupal\izi_maps\MapsService $maps_service
   *   IZI Maps Service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    IziObjectService $object_service,
    LanguageService $language_service,
    HelpersService $helpers_service,
    StateInterface $state,
    BreadcrumbBuilderInterface $breadcrumb_manager,
    ReviewsService $reviews_service,
    MapsService $maps_service,
    IziSearchService $search_service,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger,
  ) {
    $this->object_service = $object_service;
    $this->language_service = $language_service;
    $this->helpers_service = $helpers_service;
    $this->state = $state;
    $this->breadcrumbManager = $breadcrumb_manager;
    $this->reviews_service = $reviews_service;
    $this->maps_service = $maps_service;
    $this->search_service = $search_service;
    $this->fileSystem = $file_system;
    $this->logger = $logger->get('izi_apicontent');
	
	$this->childCacheDestination = 'private://izi_content/children/';
	$this->parentCacheDestination = 'private://izi_content/parents/';
	
	
	if (\Drupal::currentUser()->isAuthenticated()) {		
		$current_url = Url::fromRoute('<current>');		
		$variation_ids = $this->getVariationIdsByUrl($current_url->toString());		
		
		
		if (empty($variation_ids)) {
			$pattern = '/\/tourstops_load_more\/([a-f0-9-]+)\//';
			// Perform the regular expression match
			if (preg_match($pattern, $current_url->toString(), $matches)) {
				// Extract the UUID from the matched result
				$uuid = $matches[1];
				$variation_ids = $this->getVariationIdsBySku($uuid);	
			}
		}
		if (!empty($variation_ids)) {
			foreach ($variation_ids as $variation_id) {
				if ($variation_id !== FALSE) {
					$current_user = \Drupal::currentUser();
					$variation = \Drupal\commerce_product\Entity\ProductVariation::load($variation_id);
					$is_purchased = $this->checkIfVariationPurchasedByCurrentUser($current_user, $variation); 
					
					if ($is_purchased) {
						$this->childCacheDestination = 'private://izi_content/children/paid/';
						$this->parentCacheDestination = 'private://izi_content/parents/paid/';
						break;
					}
				}
			}
		}		
	}
  }
  
  // You can have methods to retrieve these values if needed
  public function getChildCacheDestination() {
	return $this->childCacheDestination;
  }

  public function getParentCacheDestination() {
	return $this->parentCacheDestination;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('izi_apicontent.izi_object_service'),
      $container->get('izi_apicontent.language_service'),
      $container->get('izi_apicontent.helpers_service'),
      $container->get('state'),
      $container->get('breadcrumb'),
      $container->get('izi_reviews.reviews_service'),
      $container->get('izi_maps.maps_service'),
      $container->get('izi_search.izi_search_service'),
      $container->get('file_system'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Page callback for the content retrieved from the Izi Travel API. Performs
   * generic page rendering tasks for all types of API content.
   *
   * @param \Triquanta\IziTravel\DataType\FullMtgObjectInterface|\Triquanta\IziTravel\DataType\FullPublisherInterface $object
   *   A full MTG object retrieved from the API.
   *
   * @param string $uuid
   *   The uuid retrieved from the URL.
   *
   * @param string $type
   *   API content type.
   *
   * @return mixed[]|RedirectResponse
   *   A Drupal render array.
   */
  protected function izi_apicontent_page_view($object): array|RedirectResponse {
    // Story navigation objects are not relevant to the site and return a 404.
    if ($object instanceof StoryNavigationInterface) {
      throw new NotFoundHttpException();
    }

    // For SEO reasons redirect to a parent page when the parent_uuid parameter is set and the parent is a MTG object.
    $parent_uuid = \Drupal::request()->query->get('parent_uuid');
    if ($parent_uuid) {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $parent_url = "base:/{$language}/browse/{$parent_uuid}";
      $fragment = $object->getUuid();
      $options = [
        'fragment' => $fragment,
      ];
      if ($passcode = \Drupal::request()->query->get('passcode')) {
        $options['query'] = ['passcode' => $passcode];
      }
      $url = Url::fromUri($parent_url, $options)->setAbsolute()->toString();
      return new RedirectResponse($url);
    }

    $content = $this->object_service->get_object_language_content($object->getContent());

    $context = [];

    $build = $this->izi_apicontent_build_object_view($object, $content, $context);

    $build['#cache'] = [
      'max-age' => 1,
      'contexts' => [
        'url.path',
      ],
    ];

    return $build;
  }

  /**
   * Renders an object from the API. Performs generic object rendering tasks for
   * all types of API content and calls different view functions per object type.
   *
   * @param \Triquanta\IziTravel\DataType\FullMtgObjectInterface|\Triquanta\IziTravel\DataType\FullPublisherInterface $object
   *   A full MTG object retrieved from the API.
   * @param \Triquanta\IziTravel\DataType\ContentInterface $content
   *   A content object in the correct language.
   * @param array $context
   *   Array containing information about the current context, containing:
   *   - 'child': Indicates if the object is rendered as child of another object;
   *   - 'purchase': Indicates if the (parent of) the current object contains
   *     paid content.
   *
   * @return mixed[]
   *   A Drupal render array.
   *
   * @throws \Exception
   */
  protected function izi_apicontent_build_object_view($object, $content = NULL, array $context = []) {

    $type = $this->object_service->izi_apicontent_get_object_type($object);

    $purchase = NULL;

    // Merge in defaults.
    $context += [
      'child' => FALSE,
      'purchase' => FALSE,
      'brand' => FALSE,
      'parent_uuid' => FALSE,
    ];

    if (!$content) {
      $content = $this->object_service->get_object_language_content($object->getContent());
    }

    $build = [
      '#uuid' => $object->getUuid(),
      '#type' => $type,
      '#theme' => 'izi_' . $type,
      '#title' => html_entity_decode(Xss::filter($content->getTitle())),
      '#title_attribute' => $this->helpers_service->_izi_apicontent_prepare_html_attribute($content->getTitle()),
      '#brand_modifier' => 'default',
    ];
    if ($type === IZI_APICONTENT_TYPE_MTG_OBJECT) {
      // Add some basic info about this MTG Object to the javascript settings
      // These are used for example by the custom Google Analytics Events.
      $mtg_info = [
        'language' => $content->getLanguageCode(),
        'title' => $content->getTitle(),
        'type' => $this->object_service->izi_apicontent_get_sub_type($object),
        'uuid' => $object->getUuid(),
      ];
      // drupal_add_js(array('iziMtgInfo' => $mtg_info), 'setting');.
      $build['#attached']['drupalSettings']['iziMtgInfo'] = $mtg_info;
    }

    // Also add the protocol.
    $build['#server_protocol'] = $this->helpers_service->_izi_apicontent_get_server_protocol();

    // @todo (legacy) For collections or tours we don't use the izi-mtg-object template,
    //   but the izi-mtg-collection template for now.
    //   It might be that later on these templates are merged together again.
    $sub_type = $this->object_service->izi_apicontent_get_sub_type($object);
    if ($sub_type === IZI_APICONTENT_SUB_TYPE_COLLECTION) {
      $build['#theme'] = 'izi_mtg_collection';
    }
    // @todo (legacy) Also for tours we don't use the izi-mtg-object template. See comment above.
    elseif ($sub_type === IZI_APICONTENT_SUB_TYPE_TOUR) {
      // Check if the playback is a Quest. If so use a different theme.
      $playback = $content->getPlayback();
      if ($playback instanceof PlaybackInterface && $playback->getType() == 'quest') {
        $build['#theme'] = 'izi_mtg_tour_quest';
      }
      else {
        $build['#theme'] = 'izi_mtg_tour';
		
		
		$current_user = \Drupal::currentUser();
		$roles = $current_user->getRoles();
		if (in_array('reseller', $roles)) {
			
			// Get the current URL.
			$current_url = Url::fromRoute('<current>');		
			$_product_info=[];
			
			$_product_info['currency']="EUR";
			$_product_info['price']=0;
			
			
			if (method_exists($object, 'getPurchase')) {
				$getPurchase=$object->getPurchase();
				
				if($getPurchase){
					$_product_info['currency']=$getPurchase->getCurrencyCode();
					$_product_info['price']=$getPurchase->getPrice();
					$_product_info['sku']=$object->getUuid();
					$_product_info['title']=$object->getTitle();
				}
				
			}
			$current_url = Url::fromRoute('<current>');
			$add_to_coupons_url = Url::fromRoute('izi_credit.add_to_coupons', [
				'sku' => $_product_info['sku'],
				'currency' => $_product_info['currency'],
				'price' => $_product_info['price'],
				'title' => $_product_info['title'],
				'return' => $current_url->toString(),
			], ['absolute' => true])->toString();
			
			$build = array_merge($build, [
			  '#add_to_coupons_url' => $add_to_coupons_url,
			]);
			
			
		}		
		
      }
    }

    if ($context['brand']) {
      $build['#brand_modifier'] = 'brand';
    }

    // Use a different template for children.
    if ($context['child']) {
      if (isset($context['child_theme'])) {
        $build['#theme'] = $context['child_theme'];
      }
      else {
        $build['#theme'] .= '_child';
      }
    }

    // If a top level object or a parent contains paid content, set
    // $context['purchase'] to TRUE so this information can be passed on to the
    // object's children.. If $context['purchase'] is already TRUE, skip this step
    // so we do not accidentally overwrite the value passed into this function.
    if (!$context['purchase'] && !$context['parent_uuid']) {
      // Check if a top-level object has purchasing details.
      if ($object instanceof FullTourInterface || $object instanceof FullMuseumInterface) {
        $purchase = $object->getPurchase();
        $context['parent_uuid'] = $object->getUuid();
      }
      // Tour stops *always* need to respect the parent's 'purchase' status.
      elseif ($object instanceof FullTouristAttractionInterface) {
        // If the parent is available.
        if (!empty($context['parent_uuid'])) {
          // Set current purchase to the parent's.
          $purchase = $context['purchase'];
          // Set the if_free variable.
          // An object is free when there are no purchase objects.
          $build['#is_free'] = !(bool) $purchase;
        }
      }
      // Check if the parent object has purchasing details.
      elseif ($object instanceof FullCollectionInterface || $object instanceof FullExhibitInterface) {
        try {
          /** @var \Triquanta\IziTravel\DataType\FullTourInterface $parent */
          $parent_uuid = $object->getParentUuid();
          if ($parent_uuid) {
            $parent = $this->object_service->loadObject(
              $parent_uuid,
              IZI_APICONTENT_TYPE_MTG_OBJECT,
              MultipleFormInterface::FORM_COMPACT
            );
            /** @var \Triquanta\IziTravel\DataType\PaidDataInterface $parent */
            if ($parent instanceof PaidDataInterface) {
              $purchase = $parent->getPurchase();
            }
          }
        }
        catch (\Exception $e) {
          // It is possible that the parent object cannot be found, but we want
          // to render anyway, so we catch gracefully.
          $this->logger->notice("Parent object {$object->getParentUuid()} not found.");
        }
      }

      // Is forced to the parent's purchase for FullTouristAttractionInterface.
      $context['purchase'] = (bool) $purchase;
      // Set the if_free variable.
      // An object is free when there are no purchase objects.
      $build['#is_free'] = !(bool) $purchase;
    }
    if ($type === IZI_APICONTENT_TYPE_MTG_OBJECT) {
      return $this->izi_apicontent_build_mtg_object_view($object, $content, $build, $context);
    }
    elseif ($type === IZI_APICONTENT_TYPE_PUBLISHER) {
      return $this->izi_apicontent_view_publisher($object, $content, $build, $context);
    }
    throw new \Exception(sprintf('Could not display the object of type %s with uuid %s.', $type, $object->getUuid()));
  }

  /**
   * Renders a full tour or museum object. Not intended to be called directly,
   * but through izi_apicontent_object_view();.
   *
   * @param \Triquanta\IziTravel\DataType\FullMtgObjectInterface $mtg_object
   *   A full MTG object retrieved from the API.
   * @param \Triquanta\IziTravel\DataType\ContentInterface $content
   *   A content object in the correct language.
   * @param array $build
   *   The base render array, to be expanded.
   * @param array $context
   *   Information about the current context, see izi_apicontent_object_view().
   *
   * @return \mixed[] A Drupal render array.
   *   A Drupal render array.
   */
  protected function izi_apicontent_build_mtg_object_view(FullMtgObjectInterface $mtg_object, ContentInterface $content, array $build, array $context) {

    // Generate QR code.
    $url = $this->object_service->izi_apicontent_path_without_language($mtg_object, IZI_APICONTENT_TYPE_MTG_OBJECT);
    $site_language = $this->language_service->get_interface_language();

    if ($url) {
      global $base_url;
      $url = $base_url . $url;
    }
    $build['#qr_code'] = $this->helpers_service->generateQRCode($url, 100);

    $build['#audioplayer'] = $this->izi_apicontent_build_audioplayer(
      $content->getAudio(),
      $mtg_object,
      $context,
    );

    // Set up existing query parameters, mainly for get passcode if it's set.
    $query = \Drupal::routeMatch()->getParameters()->all();
    $options = !empty($query) ? ['query' => $query] : [];

    // For the description, re-use some Drupal text filtering functions. Yes,
    // this is the quick-and-dirty approach.
    $description = $content->getDescription();
    $description = $this->helpers_service->_izi_apicontent_filter_html_tags($description);
    $description = $this->helpers_service->_izi_apicontent_filter_url($description);
    $build['#description'] = [
      '#markup' => $description,
    ];

    // For the news, re-use some Drupal text filtering functions. Yes,
    // this is the quick-and-dirty approach.
    $news = $content->getNews();
    if ($news) {
      $news = $this->helpers_service->_izi_apicontent_filter_html_tags($news);
      $news = $this->helpers_service->_izi_apicontent_filter_url($news);
    }
    $build['#news'] = $news;

    // Build related content block.
    if (!empty($content->getReferences())) {
      $rendered_references = '';
      foreach ($content->getReferences() as $key => $reference) {
        if ($key <= 11) {
          try {
            $item = $this->search_service->izi_search_build_object_teaser($reference);
            $rendered_element = \Drupal::service('renderer')->render($item);
            $rendered_references .= (string) $rendered_element;
          }
          catch (IziLibiziNotFoundException $e) {
            // Do nothing, just don't render.
          }
        }
      }

      $related_content_carousel = [
        'no_pading' => [
          'title' => [
            '#prefix' => '<h2 class="strip__title">',
            '#suffix' => '</h2>',
            '#markup' => t('See also'),
          ],
          'content' => [
            '#markup' => $rendered_references,
            '#theme_wrappers' => ['container'],
            '#attributes' => ['class' => ['related-content-carousel']],
          ],
          '#theme_wrappers' => ['container'],
          '#attributes' => ['class' => ['contained']],
        ],
        '#theme_wrappers' => ['container'],
        '#attributes' => ['class' => ['strip', 'related-content-wrapper']],
      ];

      $build['#related_content'] = $related_content_carousel;
    }

    // Build open in app button.
    // Ignore drupal language on mobile link.
    $language_none = new \stdClass();
    $language_none->language = FALSE;
    $link_options = [
      'alias' => TRUE,
      'absolute' => TRUE,
      'attributes' => [
        'class' => ['open_app_link'],
      ],
    ];
    $url = Url::fromUri(
      \Drupal::request()->getSchemeAndHttpHost() . $this->object_service->izi_apicontent_path($mtg_object),
    );
    $url->setOptions($link_options);

    $open_in_app_block = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['app_message', 'new'],
      ],
      'link' => [
        '#markup' => Link::fromTextAndUrl('Open in app', $url)->toString(),
      ],
      'close' => [
        '#prefix' => '<span class="close_app_message">',
        '#suffix' => '</span>',
      ],
    ];

    $build['#app_button'] = $open_in_app_block;

    $images = $content->getImages();
    // Filter the images, so we filter map images out.
    $filter_images = [];
    foreach ($images as $image) {
      if ($image->getType() != IZI_APICONTENT_IMAGE_TYPE_MAP) {
        $filter_images[] = $image;
      }
    }
    $images = $filter_images;
    $build['#images'] = $this->izi_apicontent_build_imagegallery($images, $mtg_object, $context);
    // Add the first image as a header image.
    if (is_array($images) && count($images)) {
      $img_url = $this->object_service->izi_apicontent_media_url($images[0], $mtg_object);
      $build['#header_image'] = $img_url;
    }
    // Create a default image. Might need to be changed because it is very ugly.
    else {
      global $base_url;
      $module_path = \Drupal::service('extension.list.module')->getPath('izi_apicontent');
      // $build['#header_image'] = $base_url . '/' . drupal_get_path('module', 'izi_apicontent') . '/img/placeholder-icon.png';
      $build['#header_image'] = "{$base_url}/{$module_path}/img/placeholder-icon.png";
    }

    // Create the app link.
    $app_config = \Drupal::config('izi_apicontent.app_settings')
      ->getRawData();
    $app_url = $app_config['app_download_page'];
    $build['#app_url'] = $app_url;

    $build['#mtg_type'] = $mtg_object->getType();
    $build['#language'] = $content->getLanguageCode();

    // Create the reviews.
    if (!$context['child']) {
      $build['#reviews'] = $this->reviews_service->izi_reviews_form_and_listing_render_block_content($mtg_object->getUuid());
      $build['#number_of_reviews_text'] = \Drupal::translation()
        ->formatPlural($build['#reviews']['#count'], '@count review', '@count reviews');
      $rating_object = $this->reviews_service->izi_reviews_load_rating_and_reviews_object($mtg_object->getUuid(), 0, IZI_REVIEWS_SHOW_LIMIT);
      $build['#reviews_score'] = $this->reviews_service->helpers_service->_izi_reviews_calculate_starts($rating_object->getRatingAverage());
    }

    /*******************************************************************/
    /** Define more content if this happens to be a museum mtg_object. */
    /*******************************************************************/
    $breadcrumb = $this->breadcrumbManager->build(\Drupal::routeMatch())->getLinks();

    if ($build['#mtg_type'] == IZI_APICONTENT_SUB_TYPE_MUSEUM) {
      // Add JS to load exhibits by AJAX.
      $build['#attached']['library'][] = "izi_apicontent/izi_apicontent.museum_exhibits";

      // Define path to city.
      // We can just call $this->breadcrumbManager->build(\Drupal::routeMatch())->getLinks();
      // and let it return the third array item, because this is always the city
      // in the breadcrumb.
      $build['#city_path'] = !empty($breadcrumb[2]) ? $breadcrumb[2] : '';

      // Define map coordinates.
      $location = $mtg_object->getLocation();
      if (!empty($location)) {
        $build['#latitude'] = Xss::filter($location->getlatitude());
        $build['#longitude'] = Xss::filter($location->getLongitude());
      }

      $contact = $mtg_object->getContactInformation();
      if ($contact) {
        // Define address.
        $street_name = $location
          ? $this->object_service->izi_apicontent_openstreet_api_reverse_geocode_street_name($location, $this)
          : '';
        $build['#address'] = $street_name;
        $build['#city'] = Xss::filter(trim($contact->getCity()));

        $countries = CountryManager::getStandardList();
        $country_code_upper = strtoupper($contact->getCountryCode());
        $build['#country'] = $countries[$country_code_upper] ?? $country_code_upper;

        // Build website link.
        $build['#website'] = Xss::filter($contact->getWebsite());
      }

      // Define openinghours.
      $schedule = $mtg_object->getSchedule();
      if (!empty($schedule)) {
        $build['#opening'] = $this->izi_apicontent_build_openinghours(
          $schedule,
          [
            'lat' => $build['#latitude'],
            'long' => $build['#longitude'],
          ]
        );
      }

      // Get the sponsors.
      $sponsors = $mtg_object->getSponsors();
      $build['#sponsors'] = [];
      if (!empty($sponsors)) {
        foreach ($sponsors as $sponsor) {
          // Get the link and the image.
          $image = $sponsor->getImages();
          $image_url = $this->object_service->izi_apicontent_media_url($image[0], $mtg_object);
          // Get the link.
          $url = $sponsor->getWebsite();
          $sponsor_name = $sponsor->getName();
          $build['#sponsors'][] = [
            'image_url' => Xss::filter($image_url),
            'url' => Xss::filter($url),
            'name' => Xss::filter($sponsor_name),
          ];
        }
      }

      // Get video.
      $build['#video'] = $this->_izi_apicontent_prepare_content_videos($content, $mtg_object);
    }

    if ($build['#mtg_type'] === IZI_APICONTENT_SUB_TYPE_COLLECTION) {
      // Define breadcrumb path to the museum. We can just call drupal_get_breadcrumb()
      // and let it return the fourth array item, because this is always the museum
      // in the breadcrumb, and because this has already been defined by izi_apicontent_page_view().
      if (!empty($breadcrumb[3])) {
        $build['#museum_path'] = $breadcrumb[3];
      }
      else {
        $build['#museum_path'] = '';
      }

      // Add JS to load exhibits by AJAX.
      $build['#attached']['library'][] = "izi_apicontent/izi_apicontent.museum_exhibits";
    }
    elseif ($build['#mtg_type'] === IZI_APICONTENT_SUB_TYPE_TOUR) {
      // @todo (legacy) add check if 2 is set, else use 1.
      if (empty($options['query']['passcode'])) {
        if (!empty($breadcrumb[2])) {
          $build['#museum_path'] = $breadcrumb[2];
        }
        else {
          $build['#museum_path'] = '';
        }
      }

      $includes = IZI_APICONTENT_TYPE_MTG_OBJECT_INCLUDES;

      // Define map coordinates.
      $location = $mtg_object->getLocation();

      if (!empty($location)) {
        $build['#latitude'] = Xss::filter($location->getlatitude());
        $build['#longitude'] = Xss::filter($location->getLongitude());

        $playback = $content->getPlayback();
        if ($playback instanceof PlaybackInterface && $playback->getType() == 'quest' && !empty($build['#latitude']) && !empty($build['#longitude'])) {
          // Get the street name for the current location.
          $street_name = $this->object_service->izi_apicontent_openstreet_api_reverse_geocode_street_name($location, $this);
          $build['#address'] = $street_name;
        }

        // Get video for tour quest.
        if ($playback->getType() == 'quest') {
          $build['#video'] = $this->_izi_apicontent_prepare_content_videos($content, $mtg_object);
        }

        // Load country.
        try {
          $countryUuid = $location->getCountryUuid();
          if (!$countryUuid) {
            throw new \Exception("IZI_APICONTENT_SUB_TYPE_TOUR without location country");
          }
          $country_object = $this->object_service->loadObject($countryUuid, IZI_APICONTENT_TYPE_COUNTRY, MultipleFormInterface::FORM_COMPACT, $includes);
        }
        catch (\Exception $e) {
          // We don't launch an exception to avoid unnecessary 404 errors
          // caused because of breadcrumbs. See IZT-2114.
          $country_object = NULL;
        }
        // Load city.
        try {
          $cityUuid = $location->getCityUuid();
          if (!$cityUuid) {
            throw new \Exception("IZI_APICONTENT_SUB_TYPE_TOUR without location city");
          }
          $city_object = $this->object_service->loadObject($cityUuid, IZI_APICONTENT_TYPE_CITY, MultipleFormInterface::FORM_COMPACT, $includes);
        }
        catch (\Exception $e) {
          // We don't launch an exception to avoid unnecessary 404 errors
          // caused because of breadcrumbs. See IZT-2114.
          $city_object = NULL;
        }

        if (!empty($city_object)) {
          $build['#city'] = Xss::filter($city_object->getTitle());
        }
        if (!empty($country_object)) {
          $build['#country'] = Xss::filter($country_object->getTitle());
        }
      }
    }

    // Render video for tourist attractions and exhibits details.
    if (
      $build['#mtg_type'] === IZI_APICONTENT_SUB_TYPE_TOURIST_ATTRACTION
      || $build['#mtg_type'] === IZI_APICONTENT_SUB_TYPE_EXHIBIT
    ) {
      $build['#video'] = $this->_izi_apicontent_prepare_content_videos($content, $mtg_object);
    }

    // @todo (legacy) if statement duplicated (and all Get the sponsors block duplicated)
    if ($build['#mtg_type'] === IZI_APICONTENT_SUB_TYPE_TOUR) {
      // Get the sponsors.
      $sponsors = $mtg_object->getSponsors();
      $build['#sponsors'] = [];
      if (!empty($sponsors)) {
        foreach ($sponsors as $sponsor) {
          // Get the link and the image.
          $image = $sponsor->getImages();
          $image_url = $this->object_service->izi_apicontent_media_url($image[0], $mtg_object);
          // Get the link.
          $url = $sponsor->getWebsite();
          $sponsor_name = $sponsor->getName();
          $build['#sponsors'][] = [
            'image_url' => Xss::filter($image_url),
            'url' => Xss::filter($url),
            'name' => Xss::filter($sponsor_name),
          ];
        }
      }
    }

    // Render the block with the app icons.
    $build['#izi_blocks_download_small'] = [
      '#theme' => 'izi_blocks_download_small',
      '#get_app_image' => '',
    ];

    if (!$context['child']) {
      $build['#language_selector'] = $this->izi_apicontent_build_language_selector(
        $content->getLanguageCode(),
        $mtg_object->getAvailableLanguageCodes()
      );

      /** @var \Triquanta\IziTravel\DataType\CompactPublisherInterface $publisher */
      $publisher = $mtg_object->getPublisher();
      $build['#publisher'] = $this->izi_apicontent_build_publisher_bar($publisher, $mtg_object, $context);

      // Only get children if it is not a quest.
      $playback = ($content instanceof ContentInterface) ? $content->getPlayback() : NULL;
      if ($playback instanceof PlaybackInterface && $playback->getType() == 'quest') {
        $build['#quest_message'] = [
          '#markup' => \Drupal::config('izi_apicontent.app_settings')->get('quest_app_dl_message'),
        ];
      }
      else {
        $children = $content->getChildren();
      }

      if (!empty($children)) {
        if ($mtg_object instanceof TourInterface) {
          $playback = $content->getPlayback();
          $order = $playback ? $playback->getUuids() : [];

          // For the tour we will build the children different than in other situations.
          // The wrapper theme for children will not be used.
          // Instead, the children will be directly written in the izi_mtg_tour template.
          // The full view will be handled by a different theme though.
          // Add the child theme to the context.
          $context['child_theme'] = 'izi_mtg_object_child_tour';

          $build['#tourstops'] = $this->izi_apicontent_build_attractions($children, $order, $mtg_object, $context);
          // Add the description and audioplayer here.
          $build['#tourstops']['#audioplayer'] = $build['#audioplayer'];
          $filtered_description = $content->getDescription();
          $description = $this->helpers_service->_izi_apicontent_filter_html_tags($filtered_description);
          $build['#tourstops']['#description'] = $description;
          $build['#tourstops']['#video'] = $this->_izi_apicontent_prepare_content_videos($content, $mtg_object);

          /******************************************************
           * Start Map functionality.
           ******************************************************/
          // Add the map.
          // Get the bounds and the route.
          $map_object = $mtg_object->getMap();
          $bounds = $map_object ? $map_object->getBounds() : NULL;
          $route = $map_object ? $map_object->getRoute() : NULL;

          // Extract the markers for the children.
          $tour_markers = [];
          if (!empty($build['#tourstops']['#children']['#children'])) {
            foreach ($build['#tourstops']['#children']['#children'] as $child_uuid => $child) {
              $tour_markers[$child_uuid] = $child['marker'];
              if (empty($build['#tourstops']['#children']['#children'][$child_uuid]['uuid'])) {
                unset($build['#tourstops']['#children']['#children'][$child_uuid]);
              }
              else {
                unset($build['#tourstops']['#children']['#children'][$child_uuid]['marker']);
              }
            }
          }

          $map_definition = [
            'bounds' => $bounds,
            'route' => $route,
            'markers' => $tour_markers,
          ];
          $build['#map'] = $this->maps_service->get_map_render_array($map_definition);

          /****************************************************************
           * End Map functionality.
           ****************************************************************/
        }
        elseif ($mtg_object instanceof MuseumInterface) {
          $build['#children'] = $this->izi_apicontent_build_exhibits($children, $mtg_object, $context);
        }
        elseif ($mtg_object instanceof CollectionInterface) {
          $build['#children'] = $this->izi_apicontent_build_museum_tour($children, $mtg_object, $context, $build['#audioplayer']);
        }

        // Add some basic info about MTG Object children to the javascript settings
        // These are used for example by the custom Google Analytics Play Events.
        $mtg_info_children = [];

        foreach ($children as $child) {
          if (!$child instanceof StoryNavigationInterface) {
            $mtg_info_children[$child->getUuid()] = [
              'language' => $child->getLanguageCode(),
              'title' => $child->getTitle(),
              'type' => $this->object_service->izi_apicontent_get_sub_type($child),
            ];
          }
        }
        $build['#attached']['drupalSettings']['iziMtgInfoChildren'] = $mtg_info_children;
      }

      $collections = $content->getCollections();
      if (!empty($collections)) {
        $build['#collections'] = $this->izi_apicontent_build_collections($collections, $mtg_object);
        // In the new theme, we need to something else for museumpages.
        if ($mtg_object instanceof MuseumInterface) {
          // We reuse the collection render array.
          $build['#museum_tours'] = [
            '#theme' => 'izi_museum_audiotours',
            '#title' => t('Audio tours'),
            '#audiotours' => $build['#collections']['#collections'],
          ];
          // Add the reviews of the audio tours to the collection items.
          foreach ($build['#museum_tours']['#audiotours'] as $collection_uuid => &$audiotour) {
            $rating = $this->reviews_service->izi_reviews_load_rating_and_reviews_object($collection_uuid, 0, 1);
            // Calculate the amount of stars (should be 1 to 5).
            $audiotour['rating'] = $this->reviews_service->helpers_service->_izi_reviews_calculate_starts($rating->getRatingAverage());
            $audiotour['rating_text'] = t('@amount out of 5', ['@amount' => $audiotour['rating']]);
            // Get the number of reviews. Don't get the total count here, because that's the
            // amount of reviews in the current language, whereas the score is based on
            // all the reviews.
            $audiotour['number_of_reviews'] = $rating->getReviewsCount();
            $audiotour['number_of_reviews_text'] = \Drupal::translation()->formatPlural($rating->getReviewsCount(), '@count review', '@count reviews');
          }

        }
      }

      $build['#attached']['library'][] = "izi_apicontent/izi_apicontent.mtg_new";
    }

    return $build;
  }

  /**
   * @param \Triquanta\IziTravel\DataType\AudioInterface[] $audio_items
   *   An array of audio items.
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface $parent
   *   The parent tour object.
   * @param array $context
   *   Information about the current context, see izi_apicontent_object_view().
   * @param string $theme
   *   The theme to use. Options are izi_audioplayer and izi_audioplayer_jplayer.
   *
   * @param string $js
   *
   * @return mixed[] A Drupal render array.
   *   A Drupal render array.
   * @throws \Exception
   */
  private function izi_apicontent_build_audioplayer(array $audio_items, MtgObjectInterface $parent, array $context) {

    $build = [];
    $app_config = \Drupal::config('izi_apicontent.app_settings')
      ->getRawData();
    $app_url = $app_config['app_download_page'];

    if (!empty($audio_items)) {
      $build['#attached']['drupalSettings']['jplayerSwfPath'] = "/libraries/jplayer/dist/jplayer/jquery.jplayer.swf";
      $build['#attached']['library'][] = 'izi_apicontent/izi_apicontent.izi-apicontent-jplayer';
      // If the parent object is paid content, we render
      // something else instead of the player.
      // The parent it self can always be played, even if it is paid.
      if (isset($context['purchase']) && $context['purchase']) {
		  
		 // Get the current URL.
		$current_url = Url::fromRoute('<current>');		
		$_product_info=[];
		
		$_product_info['currency']="EUR";
		$_product_info['price']=0;
		
		if (method_exists($parent, 'getPurchase')) {
			$getPurchase=$parent->getPurchase();
			$_product_info['currency']=$getPurchase->getCurrencyCode();
			$_product_info['price']=$getPurchase->getPrice();
			$_product_info['sku']=$parent->getUuid();
			$_product_info['title']=$parent->getTitle();
		}else{
			$_product_info['sku']=$context['parent_uuid'];
			$_product_info['title']=$parent->getTitle();
		} 
		
		$is_purchased = false;
		
		$sku = $_product_info['sku'];
		if(empty($sku)){
			
			$sku=$parent->getparentUuid();
			$_product_info['sku']=$parent->getparentUuid();
			/*$full_object = $this->object_service->loadObject($sku, IZI_APICONTENT_TYPE_MTG_OBJECT);
			$content_array = $full_object->getContent();
			
			$_product_info['title']=$content_array->getTitle();*/
		}  
		
		$variation_ids = $this->getVariationIdsBySku($sku);
		
		if (!empty($variation_ids)) {
			// Variation IDs found.
			foreach ($variation_ids as $variation_id) {
				if ($variation_id !== FALSE) {
					$current_user = \Drupal::currentUser();
					$variation = \Drupal\commerce_product\Entity\ProductVariation::load($variation_id);
					$is_purchased = $this->checkIfVariationPurchasedByCurrentUser($current_user, $variation); 
					
					if ($is_purchased) {
						$audio = reset($audio_items);
						$media_url = $this->object_service->izi_apicontent_media_url($audio, $parent);
						$uuid = $audio->getUuid();

						$build = array_merge($build, [
							'#theme' => "izi_audioplayer_jplayer",
							'#url' => $media_url,
							'#uuid' => $uuid,
						]);
						//$this->checkandcleanDirectories();
						// Exit the loop once a purchased variation is found.
						break;
					}
				}
			}
		}

		
		
		if(!$is_purchased){
			$login_link = Url::fromRoute('user.login', [], ['query' => ['destination' => $current_url->toString()]])->toString(); 
			
			
			$payment_init_url = Url::fromRoute('izi_apicontent.add_to_cart', [
				'sku' => $_product_info['sku'],
				'currency' => $_product_info['currency'],
				'price' => $_product_info['price'],
				'title' => $_product_info['title'],
				'return' => $current_url->toString(),
			], ['absolute' => true])->toString();

			$build = array_merge($build, [
			  '#theme' => 'izi_audioplayer_jplayer_paid',
			  '#app_url' => $app_url,
			  '#logged_in' => \Drupal::currentUser()->isAuthenticated(),
			  '#purchased_link' => $payment_init_url,
			  '#login_link' => $login_link,
			]);
		}
		
      }
      else {
        // We assume that there is only one audio item.
        $audio = reset($audio_items);
        $media_url = $this->object_service->izi_apicontent_media_url($audio, $parent);
        $uuid = $audio->getUuid();

        $build = array_merge($build, [
          '#theme' => "izi_audioplayer_jplayer",
          '#url' => $media_url,
          '#uuid' => $uuid,
        ]);
      }
    }

    return $build;
  }
  
	/**
	 * Get variation ID by SKU.
	 *
	 * @param string $sku
	 *   The SKU of the variation.
	 *
	 * @return int|false
	 *   The ID of the variation if found, or FALSE if not found.
	 */
	function getVariationIdsBySku($sku) {
		$query = \Drupal::entityQuery('commerce_product_variation')
			->condition('sku', $sku)
			->accessCheck(FALSE);
		$variation_ids = $query->execute();

		return !empty($variation_ids) ? $variation_ids : [];
	}
	
	function getVariationIdsByUrl($url) {
		$query = \Drupal::entityQuery('commerce_product_variation')
			->condition('field_tour_url', $url)
			->accessCheck(FALSE);
		$variation_ids = $query->execute();

		return !empty($variation_ids) ? $variation_ids : [];
	}


  
  
	function checkIfVariationPurchasedByCurrentUser(AccountInterface $current_user, ProductVariationInterface $variation) {
	  // Get the user ID.
	  $user_id = $current_user->id();

		// Load all orders made by the user.
		$order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
		$query = $order_storage->getQuery();
		$completed_order_ids = $query
			->condition('uid', $user_id)
			->accessCheck(FALSE)
			->condition('state', 'completed')
			->execute();


		// Load the completed orders.
		$completed_orders = $order_storage->loadMultiple($completed_order_ids);

	  // Check if any of the orders contain the specific variation.
	  foreach ($completed_orders as $order) {
		/** @var \Drupal\commerce_order\Entity\OrderInterface $order */
		foreach ($order->getItems() as $order_item) {
		  /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
		  $purchased_entity = $order_item->get('purchased_entity')->entity;
		  if ($purchased_entity && $purchased_entity->id() === $variation->id()) {
			// The variation has been purchased by the user.
			return TRUE;
		  }
		}
	  }

	  // The variation has not been purchased by the user.
	  return FALSE;
	}
  
  

  /**
   * @param \Triquanta\IziTravel\DataType\ImageInterface[] $images
   *   An array of audio items.
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface $parent
   *   The parent tour object.
   * @return mixed[]
   *   A Drupal render array.
   */
  private function izi_apicontent_build_imagegallery(array $images, MtgObjectInterface $parent, array $context = []) {
    global $base_url;
    $module_path = \Drupal::service('extension.list.module')->getPath('izi_apicontent');
    $placeholder_image_src = "{$base_url}/{$module_path}/img/placeholder-icon.png";

    $build = [
      '#theme' => 'izi_imagegallery',
      '#placeholder' => $placeholder_image_src,
    ];

    $build['#id'] = 'gallery-' . $parent->getUuid();
    if (isset($context['child']) && $context['child'] == FALSE) {
      $build['#lazy'] = FALSE;
    }

    if (count($images)) {
      foreach ($images as $image) {
        $img_url = $this->object_service->izi_apicontent_media_url($image, $parent);
        // Check if exist a title for the image and send it to the template.
        $build['#images'][] = [
          'image' => $img_url,
          'title' => (!empty($image->getTitle())) ? $image->getTitle() : '',
        ];
      }
    }
    else {
      // Create a placeholder image if there are no images.
      $build['#images'][] = [
        'image' => $placeholder_image_src,
        'title' => '',
      ];
    }

    return $build;
  }

  /**
   * Helper function, builds a render array for the opening hours of a museum.
   *
   * @param \Triquanta\IziTravel\DataType\Schedule $schedule
   * @param array $location
   *
   * @return array
   *   A Drupal render array.
   */
  private function izi_apicontent_build_openinghours(Schedule $schedule, array $location) {
    if (!empty($location)) {
      // @todo (Future): hardcoded api.timezonedb.com url and key
      $url = 'http://api.timezonedb.com/?lat=' . $location['lat'] . '&lng=' . $location['long'] . '&format=json&key=LW2SBF8LY4EX';
      $response = \Drupal::httpClient()->get($url, ['headers' => ['Accept' => 'text/plain']]);
      $result = json_decode($response->getBody(), TRUE);
      $timezoneid = $result['zoneName'];
      $gmtoffset = $result['gmtOffset'];
    }

    // Rquest time all days of a week.
    $openinghours = [];
    $openinghours[1] = $schedule->getMondaySchedule();
    $openinghours[2] = $schedule->getTuesdaySchedule();
    $openinghours[3] = $schedule->getWednesdaySchedule();
    $openinghours[4] = $schedule->getThursdaySchedule();
    $openinghours[5] = $schedule->getFridaySchedule();
    $openinghours[6] = $schedule->getSaturdaySchedule();
    $openinghours[7] = $schedule->getSundaySchedule();

    $build = [
      '#theme' => 'izi_openinghours',
      '#openinghours' => $openinghours,
      '#timezone_id' => $timezoneid,
      '#gmt_offset' => $gmtoffset,
    ];
    return $build;
  }

  /**
   * Helper function, builds a render array for the API content language selector.
   *
   * @param string $current_language
   *   The language code of the current language.
   * @param string[] $available_languages
   *   An array of language codes of available languages.
   * @param bool $allow_any
   *
   * @return mixed[]
   *   A Drupal render array.
   */
  private function izi_apicontent_build_language_selector(string $current_language, array $available_languages, $allow_any = FALSE) {

    $languages_native = $this->language_service->get_language_names();
    $languages_translated = $this->language_service->get_language_names(FALSE);
    if ($allow_any) {
      $languages_native[IZI_APICONTENT_LANGUAGE_ANY] = (string) t('Any language');
      $languages_translated[IZI_APICONTENT_LANGUAGE_ANY] = t('Any language');
    }

    $current_language_name = (string) $languages_translated[$current_language] ?? $current_language;

    $build['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['restyled_lang_dropdown'],
      ],
      'current' => [
        '#type' => 'container',
        '#tree' => TRUE,
        '#attributes' => [
          'class' => ['language-select'],
        ],
      ],
    ];

    $build['wrapper']['current']['language'] = [
      '#type' => 'container',
      'link' => [
        '#type' => 'link',
        '#url' => Url::fromUri("base:/$current_language"),
        '#title' => $current_language_name,
        '#attributes' => [
          'class' => [
            'with-dropdown-arrow',
            'title',
          ],
          'data-dropdown' => 'dropdown-1',
        ],
      ],
    ];

    $build['wrapper']['select'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'class' => ['f-dropdown'],
      ],
      'options' => [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#attributes' => ['class' => 'dropdown-menu'],
      ],
      '#attached' => [
        'library' => [
          'izi_apicontent/izi_apicontent.language_select',
        ],
      ],
    ];

    // Render the new language selector different from the old one.
    $options = [];
    foreach ($available_languages as $language_option) {
      $language_name = $languages_native[$language_option] ?? $language_option;

      // Make sure that extra params are passed when language is changed.
      $query_param = \Drupal::request()->query->all();
      unset($query_param['q']);

      $language_url = Url::fromUri(
        "base:/{$this->izi_apicontent_current_path($language_option)}",
        ['query' => $query_param]
      );
      $build['wrapper']['select']['options']['#items'][] = Link::fromTextAndUrl($language_name, $language_url)->toString();

      // Render the language selector.
      $options[$language_url->toString()] = $language_name;

      // Default language.
      if ($current_language == $language_option) {
        $default_value = $language_url->toString();
      }
    }

    $current_path = \Drupal::service('path.current')->getPath();
    $path_segments = explode("/", $current_path);
    // Ignore completely if on publisher page.
    if ($path_segments['2'] !== 'publishers') {
      // Return $options instead of $build. This is the new theme.
      if (!empty($options)) {
        if (count($options) == 1) {
          return [
            '#prefix' => '<span class="only-available-lang">',
            '#markup' => t('Only in @language', ['@language' => $current_language_name]),
            '#suffix' => '</span>',
          ];
        }
        else {
          return [
            '#theme'      => 'select',
            '#attributes' => [
              'class' => ['selectbox', 'language-select'],
            ],
            '#options'    => $options,
            '#value' => $default_value,
            '#attached' => [
              'library' => [
                'izi_apicontent/izi_apicontent.language_select_new',
              ],
            ],
          ];
        }
      }
    }
    return $build;
  }

  /**
   * Return the current URL path of the page being viewed.
   *
   * @param string $langcode
   *   (optional) Override langcode of the current path. This assumes that the
   *   2 character language code is the last segment in the URL.
   *
   * @return
   *   The current Drupal URL path. Optionally with the language code overridden.
   *
   * @see current_path()
   */
  private function izi_apicontent_current_path($langcode = '') {
    $current_path = \Drupal::service('path.current')->getPath();
    // There is no language suffix '/any'. Instead, return a path without suffix.
    if ($langcode == IZI_APICONTENT_LANGUAGE_ANY) {
      $langcode = '';
    }

    // Trim the last URL segment if it is a valid language.
    $segments = explode('/', $current_path);
    $last = end($segments);
    $content_languages = $this->language_service->get_fallback_languages();
    if (in_array($last, $content_languages)) {
      array_pop($segments);
    }

    // Append the language code, if desired.
    if (!empty($langcode)) {
      $segments[] = $langcode;
    }
    $current_path = implode('/', $segments);

    return $current_path;
  }

  /**
   * @param \Triquanta\IziTravel\DataType\CompactPublisherInterface $publisher
   *   A publisher object in compact form.
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface $parent
   *   The MTG object on which the publisher bar is placed.
   * @return mixed[]
   *   A Drupal render array.
   */
  private function izi_apicontent_build_publisher_bar(CompactPublisherInterface $publisher, MtgObjectInterface $parent, array $context) {
    $image = FALSE;
    $images = $publisher->getImages();
    if (!empty($images)) {
      $image = $this->object_service->izi_apicontent_media_url(reset($images), $parent);
    }

    // Build information for the template. The link to the publisher page always
    // requests the available content in any language, not just the currently
    // selected content language.
    $content_language = $this->language_service->izi_apicontent_get_content_language_from_url(NULL, $this);
    $build = [
      '#theme' => 'izi_publisher_bar',
      '#title' => Unicode::truncate(Xss::filter($publisher->getTitle()), 45, TRUE, TRUE),
      '#image' => $image,
      '#bar_prefix' => t('Provided by'),
      '#description' => Unicode::truncate($publisher->getSummary(), 150, TRUE, TRUE),
      '#button_label' => t('View all guides'),
      '#url' => $this->object_service->izi_apicontent_url($publisher->getUuid(), IZI_APICONTENT_TYPE_PUBLISHER, [], $content_language),
      '#brand_modifier' => 'default',
    ];

    if (isset($context['brand']) && $context['brand']) {
      $build['#brand_modifier'] = 'vip-' . strtolower($publisher->getTitle());
    }

    return $build;
  }

  /**
   * Builds a renderable array of collections.
   *
   * @param \Triquanta\IziTravel\DataType\CompactMtgObjectInterface[] $collections
   *   An array of collections.
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface $parent
   *   The parent tour object.
   *
   * @return mixed[]
   *   A Drupal render array.
   */
  private function izi_apicontent_build_collections(array $collections, MtgObjectInterface $parent) {
    global $base_url;
    $build = [];

    if (!empty($collections)) {
      $build = [
        '#theme' => 'izi_collections',
        '#title' => t('Collections'),
        '#collections' => [],
      ];

      // Make sure that extra params are passed.
      //      $query_param = $_GET;.
      // Get all $_GET variables.
      $query_param = \Drupal::request()->query->all();
      unset($query_param['q']);

      $build_collections = [];

      foreach ($collections as $collection) {
        $uuid = $collection->getUuid();
        $build_collections[$uuid]['title'] = Xss::filter($collection->getTitle());
        $build_collections[$uuid]['url'] = $this->object_service->izi_apicontent_url($collection, IZI_APICONTENT_TYPE_MTG_OBJECT, ['query' => $query_param]);

        $images = $collection->getImages();
        if ($images) {
          $image = reset($images);
          $build_collections[$uuid]['image'] = $this->object_service->izi_apicontent_media_url($image, $parent, ['size' => '240x180']);
        }
        else {
          // Collections without images are discarded.
          $module_path = \Drupal::service('extension.list.module')->getPath('izi_apicontent');
          $build_collections[$uuid]['image'] = "{$base_url}/{$module_path}/img/placeholder-icon.png";
          // $build_collections[$uuid]['image'] = $base_url . '/' . drupal_get_path('module', 'izi_apicontent') . '/img/placeholder-icon.png';
        }

      }

      // Sort the collections alphabetically by name.
      //      uasort($build_collections, $this->_cmp_array_title);.
      uasort($build_collections, [$this->helpers_service, '_cmp_array_title']);

      $build['#collections'] = $build_collections;
    }

    return $build;
  }

  /**
   * @param \Triquanta\IziTravel\DataType\CompactMtgObjectInterface[] $children
   *   An array of audio items.
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface $parent
   *   The parent tour object.
   * @param array $context
   *   Information about the current context, see izi_apicontent_object_view().
   * @return mixed[]
   *   A Drupal render array.
   * @throws \Exception
   */
  private function izi_apicontent_build_exhibits(array $children, MtgObjectInterface $parent, array $context) {
    global $base_url;

    $build = [];
    $context['child'] = TRUE;

    if (!empty($children)) {

      $build = [
        '#theme' => 'izi_children',
        '#title' => t('Exhibits'),
        '#sub_title' => t('Exhibits featured with audio'),
        '#children' => [],
        '#brand_modifier' => 'default',
      ];

      if (isset($context['brand']) && $context['brand']) {
        $build['#brand_modifier'] = 'brand';
      }
      $build['#children'] = $this->izi_apicontent_add_children_exhibits_to_render_array($children, $parent, $context);
    }

    return $build;
  }

  /**
   * Add exhibit childrens to the build array.
   * This is needed for museum pages.
   *
   * @param array $children
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface $parent
   * @param array $context
   *
   * @return mixed
   *
   * @throws \Exception
   */
  private function izi_apicontent_add_children_exhibits_to_render_array(array $children, MtgObjectInterface $parent, array $context) {
    global $base_url;
    $languagecode = \Drupal::service('izi_apicontent.language_service')
      ->get_interface_language();
    $build_children = [];

    // Make sure that extra params are passed.
    $query_param = \Drupal::request()->query->all();
    unset($query_param['q']);

    foreach ($children as $key => $child) {
      if ($child instanceof CompactExhibitInterface) {
        $exhibit_number = $child->getLocation()->getExhibitNumber();
        $child_uuid = $child->getUuid();

        // @todo (legacy) Please compare to izi_apicontent_build_attractions the building of children
        // There is code duplication.
        $alias = \Drupal::service('path_alias.manager')
          ->getAliasByPath($this->object_service->izi_apicontent_path($child));

        $build_children[$child_uuid] = [
          'title' => Xss::filter($child->getTitle()),
          'uuid' => $child_uuid,
          'exhibit_number' => $exhibit_number,
          'url' => $this->object_service->izi_apicontent_url(
            $parent,
            IZI_APICONTENT_TYPE_MTG_OBJECT,
            [
              'fragment' => $this->object_service->izi_apicontent_fragment(
                $child,
                IZI_APICONTENT_TYPE_MTG_OBJECT,
                $parent->getLanguageCode()
              ),
              'query' => $query_param,
            ]
          ),
          'hash' => "#{$alias}",
          'lang' => $parent->getLanguageCode(),
        ];

        $images = $child->getImages();
        if ($images) {
          $image = reset($images);

          $build_children[$child_uuid] += [
            'image_small' => $this->object_service->izi_apicontent_media_url($image, $child, ['size' => '240x180']),
          ];
        }
        else {
          // Children without images are discarded.
          // continue;.
          $path = \Drupal::service('extension.list.module')->getPath('izi_apicontent');
          $build_children[$child_uuid] += [
            'image_small' => $base_url . '/' . $path . '/img/placeholder-icon.png',
          ];
        }

        // If we are in a collection and we're processing the first element,
        // we need to check if it has audio to show the "reproduce collection" button.
        // For this, it's necessary to load the full element instead the compact one.
        if ($key === 0) {
          $full_object = $this->object_service->loadObject($child_uuid, IZI_APICONTENT_TYPE_MTG_OBJECT);
          $content_array = $full_object->getContent();
          $content = reset($content_array);
          $audio = $content->getAudio();
          $has_audio = !empty($audio);
          $build_children[$child_uuid]['has_audio'] = $has_audio;
        }
      }
    }

    if ($parent instanceof MuseumInterface) {
      // Sort the exhibits by their location property.
      uasort($build_children, [$this->helpers_service, '_cmp_array_exhibit_number']);
    }

    // Test if we need to update saved files.
    $update = $this->izi_apicontent_html_files_expired($parent);

    // If update is enabled.
    if ($update) {

      // Load and add the full MTG object to each tourist attraction.
      $full_objects = [];
      try {
        // Get the current languages.
        $languages = $this->language_service->get_preferred_content_languages();
        /** @var \Triquanta\IziTravel\DataType\FullMtgObjectInterface $full_object */
        $full_objects = $this->object_service->izi_apicontent_mtg_object_load_multiple(
          array_keys($build_children),
          MultipleFormInterface::FORM_FULL,
          [],
        );
      }
      catch (\Exception $e) {
        $this->izi_libizi_handle_api_exception($e, 'Exhibit objects not found');
        $this->logger
          ->error('Exhibit objects not found. IziApiContentController 2647');
      }

      /** @var \Triquanta\IziTravel\DataType\FullMtgObjectInterface $full_object */
      foreach ($full_objects as $full_object) {
        $exhibit_to_save = $this->izi_apicontent_build_object_view($full_object, NULL, $context);

        // Because we are rendering exhibits we alter the render array stemming from izi_apicontent_object_view.
        // It might be a good idea to replace this general function by specialized functions.
        // For now, we leave it like this due to time considerations.
        $exhibit_to_save['#theme'] = 'izi_mtg_object_child_exhibit';
        $exhibit_to_save['#share_url'] = $this->object_service->getChildShareUrl($full_object, $parent, $query_param);

        // We need to build an audio player.
        $content_array = $full_object->getContent();
        $content = reset($content_array);
        $exhibit_to_save['#audioplayer'] = $this->izi_apicontent_build_audioplayer(
          $content->getAudio(),
          $full_object, $context,
        );

        // We also need the image. This is assumed to be only one.
        if (!empty($exhibit_to_save['#images']['#images'])) {
          $exhibit_to_save['#image'] = reset($exhibit_to_save['#images']['#images']);
        }

        // Get exhibit video.
        $exhibit_to_save['#video'] = $this->_izi_apicontent_prepare_content_videos($content, $full_object);

        // Save the rendered child to file.
        $this->izi_apicontent_save_exhibit($exhibit_to_save);
      }

      // Update parent datestamp file if we are updating as the last thing.
      $this->izi_apicontent_save_parent_uuid_date($parent);
    }

    return $build_children;
  }

  /**
   * Test if tour needs to be re-saved.
   *
   * @param object $parent
   *
   * @return bool
   */
  protected function izi_apicontent_html_files_expired($object, $type = 'parent') {
    // Set update to FALSE.
    $update = FALSE;

    // Define current unix time.
    $time = time();

    // Set up deprecated timestamp.
    $deprecate_time = $time - 3600;

    // Different behaviour if we look for a child or a parent.
    if ($type === 'parent') {
      // Get UUID.
      $object_uuid = $object->getUuid();
      // Get parent language.
      $object_language = $object->getLanguageCode();
      // Including trailing slash.
      $object_uri_folder = $this->parentCacheDestination;
      // Set up filename.
      $object_uri_file = $object_uuid . '_' . $object_language;
    }
    else {
      // Get UUID.
      $object_uuid = $object['uuid'];
      // Get parent language.
      $object_language = $object['language'];
      // Including trailing slash.
      $object_uri_folder = $this->childCacheDestination;
      // Set up filename.
      $object_uri_file = "{$object_uuid}_{$object_language}.html";
    }

    if (!$object_uuid) {
      return FALSE;
    }
    // Set up wrapper for sites/default/files folder.
    /** @var \Drupal\Core\File\FileSystemInterface $filesystem */
    $filesystem = \Drupal::service('file_system');
    $result = $filesystem->prepareDirectory($object_uri_folder, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    if (!$result) {
      \Drupal::logger('file system')->error('The directory %directory does not exist or is not writable.', ['%directory' => $object_uri_folder]);
    }

    $update = TRUE;
    $filepath = "{$object_uri_folder}/{$object_uri_file}";
    if (file_exists($filepath)) {
      $file_modified = filectime($filepath);
      // File.
      if ($file_modified > $deprecate_time) {
        //$update = FALSE;
      }
    }

    return $update;
  }

  /**
   * Save the parent's UUID to file.
   *
   * @param object $parent
   */
  private function izi_apicontent_save_parent_uuid_date($parent) {
    $parent_uuid = $parent->getUuid();
    $language = $parent->getLanguageCode();

    try {
      $destination = $this->parentCacheDestination . $parent_uuid . '_' . $language;
      $this->checkDirectories();
      $replace = FileSystemInterface::EXISTS_REPLACE;
      $this->fileSystem->saveData($parent_uuid, $destination, $replace);
    }
    catch (FileException $e) {
      $this->logger
        ->error(
          'The UUID for parent object @parent_uuid with language @language could not be saved to file.',
          ['@parent_uuid' => $parent_uuid, '@language' => $language]
        );
    }
  }

  /**
   * Save a rendered exhibit item to file.
   *
   * @param array $child
   */
  private function izi_apicontent_save_exhibit($exhibit) {

    // Check if we have a child element.
    if (!empty($exhibit)) {
      // Render the element with the izi_mtg_object_child_tour_save template.
      $rendered_element = \Drupal::service('renderer')->render($exhibit);

      try {
        $this->checkDirectories();
        $destination = $this->childCacheDestination;
        $filename = $exhibit['#uuid'] . '_' . $exhibit['#language'] . '.html';
        $this->fileSystem->saveData($rendered_element, $destination . $filename, FileSystemInterface::EXISTS_REPLACE);
      }
      catch (FileException $e) {
        $this->logger
          ->error(
            'The UUID for child object @uuid could not be saved to file.',
            ['@uuid' => $exhibit['#uuid']]
          );
      }
    }
  }

  /**
   * Build the museum tour, which is in fact a collection.
   *
   * @param \Triquanta\IziTravel\DataType\CompactMtgObjectInterface[] $children
   *   An array of audio items.
   * @param \Triquanta\IziTravel\DataType\FullMtgObjectInterface $parent
   *   The parent tour object.
   * @param array $context
   *   Information about the current context, see izi_apicontent_object_view().
   *
   * @return mixed[]
   *   A Drupal render array.
   *
   * @throws \Exception
   */
  private function izi_apicontent_build_museum_tour(array $children, FullMtgObjectInterface $parent, array $context, $description_audio = NULL) {
    $build = [];
    $context['child'] = TRUE;

    if (!empty($children)) {

      $content_items = $parent->getContent();
      $content = $this->object_service->get_object_language_content($content_items);

      $description = '';
      if (isset($content)) {
        $description = $this->helpers_service->_izi_apicontent_filter_html_tags($content->getDescription());
      }

      $build = [
        '#theme' => 'izi_collection_children',
        '#title' => t('Tour stops'),
        '#description' => $description,
        '#description_audio' => $description_audio,
        '#children' => [],
        '#first_has_audio' => TRUE,
        '#brand_modifier' => 'default',
        '#video' => $this->_izi_apicontent_prepare_content_videos($this->object_service->get_object_language_content($parent->getContent()), $parent),
      ];

      if (isset($context['brand']) && $context['brand']) {
        $build['#brand_modifier'] = 'brand';
      }
      $build['#children'] = $this->izi_apicontent_add_children_exhibits_to_render_array($children, $parent, $context);

      // Check in the created children render array if the first has audio.
      $first_rendered_child = reset($build['#children']);
      $build['#first_has_audio'] = $first_rendered_child['has_audio'];
    }

    return $build;
  }

  /**
   * Save a renderd child item to file.
   *
   * @param array $child
   */
  protected function izi_apicontent_save_child_tour($child) {
    // Check if we have a child element.
    if (!empty($child)) {
      // Copy the minimum variables we need.
      $build = [
        '#theme' => 'izi_mtg_object_child_tour_save',
        '#title' => $child['#title'],
        '#uuid' => $child['#uuid'],
        '#audioplayer' => $child['#audioplayer'],
        '#description' => $child['#description'],
        '#previous_id' => $child['#previous_id'],
        '#next_id' => empty($child['#next_id']) ? '' : $child['#next_id'],
        '#images' => $child['#images'],
        '#video' => $child['#video'],
        '#share_url' => $child['#share_url'],
      ];

      // Render the element with the izi_mtg_object_child_tour_save template.
      $rendered_element = \Drupal::service('renderer')->render($build);

      try {
        $destination = $this->childCacheDestination . $child['#uuid'] . '_' . $child['#language'] . '.html';
        $this->checkDirectories();
        $replace = FileSystemInterface::EXISTS_REPLACE;
        $this->fileSystem->saveData($rendered_element, $destination, $replace);
      }
      catch (FileException $e) {
        $this->logger
          ->error(
            'The UUID for child object !uuid could not be saved to file.',
            ['!uuid' => $child['#uuid']]
          );
      }
    }
  }

  /**
   * @param \Triquanta\IziTravel\DataType\CompactMtgObjectInterface[] $children
   *   An array of audio items.
   * @param string[] $order
   *   An array with the attraction uuids in the desired order.
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface $parent
   *   The parent tour object.
   * @param array $context
   *   Information about the current context, see izi_apicontent_object_view().
   * @return mixed[]
   *   A Drupal render array.
   * @throws \Exception
   */
  private function izi_apicontent_build_attractions(array $children, array $order, MtgObjectInterface $parent, array $context) {
    global $base_url;
    $languagecode = \Drupal::service('izi_apicontent.language_service')
      ->get_interface_language();

    $build = [];
    $context['child'] = TRUE;

    if (!empty($children)) {
      $build = [
        '#theme' => 'izi_tour_children',
        '#title' => t('Audio tour Summary'),
        '#children' => [],
        '#brand_modifier' => 'default',
      ];

      if (isset($context['brand']) && $context['brand']) {
        $build['#brand_modifier'] = 'brand';
        $build['#title'] = '';
      }

      // Start building the list of children. The $order array is flipped and
      // used as a "frame" to populate with children.
      $build_children = array_flip($order);

      // Make sure that extra params are passed.
      $query_param = \Drupal::request()->query->all();
      unset($query_param['q']);

      // Initialize previous child. This var will be used to generate previous/next buttons.
      $previous_child = NULL;

      // Delete nav. stories and hidden tour stops from children array
      // Also, save the tourstops into cache to increase ajax requests speed later.
      $children = array_filter($children, [$this->object_service, '_is_tourist_attraction']);
      $children = array_filter($children, [$this->object_service, '_is_hidden']);
      $parent_lang = $parent->getLanguageCode();
      $cid = 'ajax_tourstops_' . $context['parent_uuid'] . '_' . $parent_lang;
      /*\Drupal::cache()->set($cid, serialize($children), time() + 3600);*/
      /*\Drupal::cache()->set($cid, serialize($children), time() + 1);*/
      \Drupal::cache()->set($cid, serialize($children), 1);

      // Only process the first bunch of tour stops.
      $filtered_children = [];
      $first_childrens_tmp = array_slice($children, 0, IZI_APICONTENT_TOURIST_ATTRACTIONS_CONTENT_AMOUNT);
      foreach ($first_childrens_tmp as $child) {
        $filtered_children[] = $child->getUuid();
      }

      // Process tour stops.
      foreach ($children as $child) {
        if ($child instanceof CompactTouristAttractionInterface) {
          if ($child->isHidden()) {
            continue;
          }

          $child_uuid = $child->getUuid();
          $build_children[$child_uuid] = [];

          if (in_array($child_uuid, $filtered_children)) {
            $alias = \Drupal::service('path_alias.manager')
              ->getAliasByPath($this->object_service->izi_apicontent_path($child));
            $child_lang = $child->getLanguageCode();
            $build_children[$child_uuid] = [
              'title' => Xss::filter($child->getTitle()),
              'uuid' => $child_uuid,
              'parent_uuid' => $parent->getUuid(),
              'url' => $this->object_service->izi_apicontent_url($parent, IZI_APICONTENT_TYPE_MTG_OBJECT, [
                'fragment' => $this->object_service->izi_apicontent_fragment($child, IZI_APICONTENT_TYPE_MTG_OBJECT, $child_lang),
                'query' => $query_param,
              ]),
              'hash' => $alias,
              'language' => $child_lang,
              'child_index' => array_search($child_uuid, $filtered_children) + 1,
            ];

            $images = $child->getImages();
            if ($images) {
              $image = reset($images);

              $build_children[$child_uuid] += [
                'image_small' => $this->object_service->izi_apicontent_media_url($image, $child, ['size' => '120x90']),
              ];
            }
            else {
              // Children without images are discarded.
              $module_path = \Drupal::service('extension.list.module')->getPath('izi_apicontent');
              $build_children[$child_uuid] += [
                'image_small' => $base_url . '/' . $module_path . '/img/placeholder-icon.png',
              ];
            }
          }

          // Set the next and previous id's.
          if (!empty($previous_child)) {
            $previous_uuid = $previous_child->getUuid();
            $build_children[$child_uuid]['#previous_id'] = $previous_uuid;
            $build_children[$previous_uuid]['#next_id'] = $child_uuid;
          }
          // It is the first child, and in that case we have to create a previous link
          // to the summary, which will be 'tour_details_first'.
          else {
            $build_children[$child_uuid]['#previous_id'] = 'tour_details_first';
          }

          // Set the previous child to this child.
          /** @var  \Triquanta\IziTravel\DataType\CompactTouristAttractionInterface $previous_child */
          $previous_child = $child;

          // Add a marker for use later.
          $build_children[$child_uuid]['marker'] = $this->izi_apicontent_get_marker_information($child);
        }
      }

      // Discard all elements that are not arrays, ie. that have not been
      // populated with a tourist attraction.
      $build_children = array_filter($build_children, 'is_array');
      $first_element = (current($build_children));

      // Mark last element as 'last' to avoid unneeded ajax requests.
      end($build_children);
      $key = key($build_children);
      reset($build_children);
      $build_children[$key]['last_child'] = TRUE;

      // Test if we need to update saved files.
      $update = $this->izi_apicontent_html_files_expired($first_element, 'child');

      // If update is enabled.
      if ($update) {
        // Load and add the full MTG object to each tourist attraction.
        $full_objects = [];
        try {
          $full_objects = $this->object_service->izi_apicontent_mtg_object_load_multiple(
            $filtered_children,
            MultipleFormInterface::FORM_FULL,
            []
          );
        }
        catch (\Exception $e) {
          $this->izi_libizi_handle_api_exception($e, 'Attraction objects not found');
          $this->logger
            ->error('Attraction objects not found');
        }

        /** @var \Triquanta\IziTravel\DataType\FullMtgObjectInterface $full_object */
        foreach ($full_objects as $full_object) {
          $tourstop_to_save = $this->izi_apicontent_build_object_view($full_object, NULL, $context);
          $full_object_uuid = $full_object->getUuid();
          // Transfer the previously created previous and next variables to the full view.
          if (!empty($build_children[$full_object_uuid]['#previous_id'])) {
            $tourstop_to_save['#previous_id'] = $build_children[$full_object_uuid]['#previous_id'];
          }
          if (!empty($build_children[$full_object_uuid]['#next_id'])) {
            $tourstop_to_save['#next_id'] = $build_children[$full_object_uuid]['#next_id'];
          }

          // Get the language for the current object.
          $language = $full_object->getLanguageCode();
          $tourstop_to_save['#language'] = $language;

          // Add video to childrens.
          $content = $this->object_service->get_object_language_content($full_object->getContent());
          $tourstop_to_save['#video'] = $this->_izi_apicontent_prepare_content_videos($content, $full_object);

          // Add share links to children.
          // Set the canonical URL and the og:url for Facebook share button.
          $url = $this->object_service->izi_apicontent_path_without_language($full_object, IZI_APICONTENT_TYPE_MTG_OBJECT);
          $site_language = $this->language_service->get_interface_language();
          if ($url) {
            $url = "{$base_url}/{$site_language}{$url}";
            $full_object->canonical_url = $url;
          }

          $tourstop_to_save['#share_url'] = $this->object_service->getChildShareUrl($full_object, $parent, $query_param);

          // Save the rendered child to file.
          $this->izi_apicontent_save_child_tour($tourstop_to_save);
        }
      }

      $build['#children']['#theme'] = 'izi_tour_children_list';
      $build['#children']['#children'] = $build_children;
    }

    return $build;
  }

  /**
   * Prepare video button if the content have a video.
   *
   * @param $content
   *   The content object.
   * @param $parent_content
   *   The parent content object to build the link.
   *
   * @return array|false
   *   HTML with the button or FALSE if the content don't have any video.
   */
  protected function _izi_apicontent_prepare_content_videos($content, $parent_content): bool|array {
    if (!empty($content->getVideos())) {
      $videos = $content->getVideos();

      if ($videos[0]->getType() == 'youtube') {
        $video_url = $this->helpers_service->_izi_apicontent_parse_youtube_url(current($videos)->getUrl());
        $video_url = ($video_url) ? current($video_url)['url'] : FALSE;
      }
      else {
        $video_url = $this->object_service->izi_apicontent_media_url($videos[0], $parent_content);
      }

      $link = Link::fromTextAndUrl(
        t('Watch video'),
        Url::fromUri($video_url),
      )->toRenderable();

      $link['#attributes'] = [
        'class' => [
          'js-fancybox-video', 'fancybox.iframe',
        ],
      ];
    }

    return (!empty($link)) ? $link : FALSE;
  }

  /**
   * Get a marker array for an object.
   *
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface $object
   *
   * @return array
   */
  private function izi_apicontent_get_marker_information(MtgObjectInterface $object) {
    $location = [];
    $location['lat'] = $object->getLocation()->getLatitude();
    $location['lng'] = $object->getLocation()->getLongitude();
    $marker = [
      'location' => $location,
      'id' => $object->getUuid(),
    ];
    return $marker;
  }

  /**
   * Renders a full publisher object. Not intended to be called directly, but
   * through izi_apicontent_object_view();.
   *
   * @param \Triquanta\IziTravel\DataType\FullPublisherInterface $publisher
   *   A full Publisher object retrieved from the API.
   * @param \Triquanta\IziTravel\DataType\PublisherContentInterface $content
   *   A content object in the correct language.
   * @param array $build
   *   The base render array, to be expanded.
   * @param array $context
   *   Information about the current context, see izi_apicontent_object_view().
   *
   * @return mixed[]
   *   A Drupal render array.
   */
  protected function izi_apicontent_view_publisher(FullPublisherInterface $publisher, PublisherContentInterface $content, array $build, array $context) {
    $build['#summary'] = empty($content->getSummary()) ? '' : Xss::filter($content->getSummary());
    $description = $content->getDescription();
    $description = $this->helpers_service->_izi_apicontent_filter_html_tags($description);
    $description = $this->helpers_service->_izi_apicontent_filter_url($description);
    $build['#description'] = $description;
    $build['#title'] = Xss::filter($content->getTitle());
    $build['#title_attribute'] = $this->helpers_service->_izi_apicontent_prepare_html_attribute($content->getTitle());

    if ($publisher->getContactInformation()) {
      $build['#website_url'] = $publisher->getContactInformation()->getWebsiteUrl();
      $build['#facebook_url'] = $publisher->getContactInformation()->getFacebookUrl();
      $build['#twitter_url'] = $publisher->getContactInformation()->getTwitterUrl();
      $build['#google_plus_url'] = $publisher->getContactInformation()->getGooglePlusUrl();
      $build['#instagram_url'] = $publisher->getContactInformation()->getInstagramUrl();
      $build['#youtube_url'] = $publisher->getContactInformation()->getYouTubeUrl();
      $build['#vkontakte_url'] = $publisher->getContactInformation()->getVKUrl();
    }

    // Add language selector.
    $preferred_content_language = $this->language_service->get_preferred_language();
    $available_languages = $this->object_service->izi_apicontent_get_publisher_content_languages($publisher->getUuid());
    // But only add the language_selector if at least 1 language is available.
    if (count($available_languages) > 0) {
      array_unshift($available_languages, IZI_APICONTENT_LANGUAGE_ANY);
      $build['#language_selector'] = $this->izi_apicontent_build_language_selector($preferred_content_language, $available_languages, TRUE);
      // Customize language selector for this page.
      $build['#language_selector']['wrapper']['current']['#prefix'] = '<div class="language-text">' . t('Content language') . ': ';
      $build['#language_selector']['wrapper']['current']['#suffix'] = '</div>';
      $build['#language_selector']['wrapper']['#attributes']['class'][] = 'menu-width';
    }

    $images = $content->getImages();
    foreach ($images as $image) {
      $type = $image->getType();
      if ($type == 'brand_logo') {
        $build['#brand_logo_url'] = $this->object_service->izi_apicontent_media_url($image, $publisher);
      }
      elseif ($type == 'brand_cover') {
        $build['#brand_cover_url'] = $this->object_service->izi_apicontent_media_url($image, $publisher);
      }
    }
    $path = \Drupal::service('extension.list.module')->getPath('izi_apicontent');
    if (!isset($build['#brand_cover_url'])) {
      $build['#brand_cover_url'] = base_path() . $path . '/img/default_cover-89f16428b16165a7fd30f535af95749e.jpg';
    }
    if (!isset($build['#brand_logo_url'])) {
      $build['#brand_logo_url'] = base_path() . $path . '/img/default_logo-606c9cffb749540e970d23056981a651.png';
    }

    // Build publisher content. Set the limit one higher than what we actually
    // display, to determine if we need a "show more" button.
    $limit = IZI_APICONTENT_PUBLISHER_CONTENT_AMOUNT + 1;
    if ($preferred_content_language == IZI_APICONTENT_LANGUAGE_ANY) {
      $request_languages = $this->language_service->get_preferred_content_languages();
    }
    else {
      $request_languages = [$preferred_content_language];
    }
    $publisher_content = $this->object_service->izi_apicontent_publisher_content_load($publisher->getUuid(), $limit, 0, $request_languages);
    $load_more_access = FALSE;
    if (count($publisher_content) == $limit) {
      array_pop($publisher_content);
      $load_more_access = TRUE;
    }
    $build['#publisher_content'] = $this->izi_apicontent_build_publisher_content($publisher_content);

    $build['#more_button'] = [
      '#access' => $load_more_access,
      'link' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('<current>'),
        '#title' => t('Show more'),
        '#attributes' => [
          'class' => ['btn-primary', 'btn-show-more'],
          'data-role' => 's-output-show-more',
          'data-offset' => IZI_APICONTENT_PUBLISHER_CONTENT_AMOUNT,
          'data-publisher-id' => $publisher->getUuid(),
          'data-filter-language' => $preferred_content_language,
        ],
      ],
    ];
    $build['#attached']['library'][] = 'izi_apicontent/izi_apicontent.publisher';

    return $build;
  }

  /**
   * @param \Triquanta\IziTravel\DataType\CompactMtgObjectInterface[] $content
   *   An array of content items published by this publisher.
   *
   * @return mixed[]
   *   A Drupal render array.
   * @throws \Exception
   */
  protected function izi_apicontent_build_publisher_content(array $content) {
    $build = [];
    // We want to reuse some code from the izi_search module here, but we cannot
    // make it an actual dependency in the info file, because that would result in
    // a circular dependency.
    if (!\Drupal::moduleHandler()->moduleExists('izi_search')) {
      return $build;
    }

    foreach ($content as $object) {
      if ($object instanceof CompactMtgObjectInterface) {
        $build[] = $this->search_service->izi_search_build_object_teaser($object);
      }
    }

    return $build;
  }

  /**
   * Will prepare required directories before reading/writing.
   *
   * @return void
   */
   
   
   protected function checkDirectories() {
	  
    if (!is_dir($this->childCacheDestination)) {
      $destination = $this->childCacheDestination;
      $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
    }
    if (!is_dir($this->parentCacheDestination)) {
      $destination = $this->parentCacheDestination;
      $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
    }
  }
   
  protected function checkandcleanDirectories() {
	// Clear the contents of the child cache destination directory.
	$this->clearDirectoryContents($this->childCacheDestination);

	// Clear the contents of the parent cache destination directory.
	$this->clearDirectoryContents($this->parentCacheDestination);
  }
  
  function clearDirectoryContents($directory) {
    $file_storage = \Drupal::service('file_system');
    if ($handle = opendir($directory)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                $file_path = $directory . '/' . $file;
                if (is_file($file_path)) {
                    $file_storage->unlink($file_path);
                }
            }
        }
        closedir($handle);
    }
  }

  /**
   * Handles Libizi API exceptions.
   *
   * This exception handling suppresses end-user messages regarding access denied
   * or not found errors when fetching data from the izi.travel API. Use this
   * exception handling only for child or other minor data elements. For
   * data objects that contain the major page content where the user should see
   * an access denied or page not found message this handling should not
   * be used. Instead, rely on the high level handling in _izi_libizi_exception_handler().
   *
   * @param \Exception $exception
   *   Exception to be handled.
   * @param null $message
   *   Exception message.
   * @param array $variables
   *   Variables for exception message.
   */
  protected function izi_libizi_handle_api_exception(\Exception $exception, $message = NULL, array $variables = []) {

    if ($exception instanceof IziLibiziAccessDeniedException) {
      $this->logger->error('No access: ' . $message, $variables);
    }
    elseif ($exception instanceof IziLibiziNotFoundException) {
      $this->logger->error('Not found: ' . $message, $variables);
    }
    else {
      _drupal_exception_handler($exception);
    }
  }

}
