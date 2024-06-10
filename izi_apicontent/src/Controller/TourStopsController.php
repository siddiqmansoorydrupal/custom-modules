<?php

namespace Drupal\izi_apicontent\Controller;

use Drupal\Component\Utility\Xss;
use Symfony\Component\HttpFoundation\Response;
use Triquanta\IziTravel\DataType\MultipleFormInterface;
use Triquanta\IziTravel\DataType\PaidDataInterface;
use Triquanta\IziTravel\DataType\Purchase;

/**
 *
 */
class TourStopsController extends IziApicontentController {

  /**
   * AJAX menu callback to render the content of one tour stop.
   *
   * @param $uuid
   * @param $next_id
   * @param $prev_id
   * @param $lang
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function izi_apicontent_get_tour_stop_details($uuid = FALSE, $prev_id = '0', $next_id = '0', $lang) {

	
	$this->childCacheDestination = $this->getChildCacheDestination();
	$this->parentCacheDestination = $this->getParentCacheDestination();

    $details = '';
    // Get content for stored tour details.
	
	// Check if '__' exists in the string
	if (strpos($uuid, '__') !== false) {
		// Explode the string by '__' delimiter
		$parts = explode('__', $uuid);

		// Check if second value exists
		
		
		if (is_array($parts) && array_key_exists(0,$parts)) {
			$uuid=$parts[0];
		}
		
		if (is_array($parts) && array_key_exists(1,$parts)) {
			$parent_uuid=$parts[1];
			$variation_ids = $this->getVariationIdsBySku($parent_uuid);	
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
	
    $wrapper = \Drupal::service('stream_wrapper_manager')
      ->getViaUri($this->childCacheDestination);
    if ($uuid && $wrapper) {
      $path = $wrapper->realpath() . "/{$uuid}_{$lang}.html";
      if (file_exists($path)) {
        $details = file_get_contents($path);
      }
    }
    if ($prev_id === '0') {
      $prev_id = NULL;
    }
    if ($next_id === '0') {
      $next_id = NULL;
    }
    $build = [
      '#theme' => 'izi_apicontent_tour_stop_ajax',
      '#details' => $details,
      '#previous_id' => $prev_id,
      '#next_id' => $next_id,
    ];
    $html = \Drupal::service('renderer')->renderRoot($build);
    $response = new Response();
    return $response->setContent($html);
  }

  /**
   * Publisher content AJAX load more page callback.
   *
   * @param string $uuid
   *   The publisher's UUID.
   * @param int $offset
   *   The offset of the batch of content items retrieved.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Drupal render array with publisher content (and read more link if needed).
   */
  public function izi_apicontent_tourstops_ajax_load_more($parent_uuid, $number_of_needed_tourstop, $lang) {
    global $base_url;
    // Try to get content from cache.
    $cid = 'ajax_tourstops_' . $parent_uuid . '_' . $lang;
    $cache = \Drupal::cache()->get($cid);
    $request_time = \Drupal::time()->getRequestTime();
    if ($cache && $request_time < $cache->expire) {
      if (!empty($cache->data)) {
        $filtered_children = unserialize($cache->data);
        $parent = $parent_uuid;
      }
    }
    else {
      // Load the full tour.
      $parent = $this->object_service->loadObject(
        $parent_uuid,
        IZI_APICONTENT_TYPE_MTG_OBJECT,
        MultipleFormInterface::FORM_FULL,
        ['children']
      );
      $content = $parent->getContent();
      $content = reset($content);

      // Get its children.
      $children = $content->getChildren();

      // Avoid navigation stories and hidden tourist attractions.
      $filtered_children = array_filter($children, [$this->object_service, '_is_tourist_attraction']);
      $filtered_children = array_filter($filtered_children, [$this->object_service, '_is_hidden']);

      // And set the cache with the children.
      \Drupal::cache()->set($cid, serialize($filtered_children), time() + 3600);

    }

    // Get the last child uuid to assign it a 'last' class to avoid unneeded ajax requests.
    $last_child = end($filtered_children);
    $last_child_uuid = $last_child->getUuid();

    // Calc the first child to show. By example, if we load 20 contents at once
    // and we request the 57th, this should return the 41st.
    $first_child_needed = (int) (($number_of_needed_tourstop - 1) / IZI_APICONTENT_TOURIST_ATTRACTIONS_CONTENT_AMOUNT) * IZI_APICONTENT_TOURIST_ATTRACTIONS_CONTENT_AMOUNT + 1;

    // Delete unneeded children from filtered children array.
    $filtered_children = array_slice($filtered_children, $first_child_needed - 1, IZI_APICONTENT_TOURIST_ATTRACTIONS_CONTENT_AMOUNT);

    // Process these children. The first part of this
    // process is to return the html list.
    $build['#theme'] = 'izi_tour_children_list';

    // Make sure that extra params are passed.
    $query_param = $_GET;
    unset($query_param['q']);

    $previous_child = NULL;

    // Generate the.
    foreach ($filtered_children as $key => $child) {
      // Initialize some vars.
      $child_uuid = $child->getUuid();
      $child_lang = $child->getLanguageCode();
      $url = $this->object_service->izi_apicontent_url($parent_uuid, IZI_APICONTENT_TYPE_MTG_OBJECT, [
        'fragment' => $this->object_service->izi_apicontent_fragment($child, IZI_APICONTENT_TYPE_MTG_OBJECT, $child_lang),
        'query' => $query_param,
      ], $lang);

      // Fill the render array with the child data.
      $build['#children'][$child_uuid] = [
        'title' => Xss::filter($child->getTitle()),
        'uuid' => $child_uuid,
        'parent_uuid' => $parent_uuid,
        'url' => $url,
        'hash' => '#' . \Drupal::service('path_alias.manager')
          ->getAliasByPath($this->object_service->izi_apicontent_path($child)),
        'language' => $child_lang,
        'child_index' => $first_child_needed + $key,
      ];

      // Get its thumbnails.
      $images = $child->getImages();
      if ($images) {
        $image = reset($images);

        $build['#children'][$child_uuid] += [
          'image_small' => $this->object_service->izi_apicontent_media_url($image, $child, ['size' => '120x90']),
        ];
      }
      else {
        $build['#children'][$child_uuid] += [
          'image_small' => $base_url . '/' . \Drupal::service('extension.list.module')->getPath('izi_apicontent') . '/img/placeholder-icon.png',
        ];
      }

      // Set the next and previous id's.
      if (!empty($previous_child)) {
        $previous_uuid = $previous_child->getUuid();
        $build['#children'][$child_uuid]['#previous_id'] = $previous_uuid;
        $build['#children'][$previous_uuid]['#next_id'] = $child_uuid;
      }
      // If it's the first child, and in that case we have to create a previous link
      // to the summary, which will be 'tour_details_first'.
      else {
        $build['#children'][$child_uuid]['#previous_id'] = 'tour_details_first';
      }

      // Set the previous child to this child.
      $previous_child = $child;
    }

    // Set the 'last child' flag.
    if (!empty($build['#children'][$last_child_uuid])) {
      $build['#children'][$last_child_uuid]['last_child'] = TRUE;
    }

    // Reformat this array to ease the subsequent process.
    $filtered_children = array_keys($build['#children']);

    // Discard all elements that are not arrays, ie. that have not been
    // populated with a tourist attraction.
    $first_element = current($build['#children']);

    // Test if we need to update saved files.
    $update = $this->izi_apicontent_html_files_expired($first_element, 'child');

    // If update is enabled.
    if ($update) {
      // Set context as child.
      $context = ['child' => TRUE];

      // Fixed issue for tour page show all paid if parent is paid.
      $parent_object = $this->object_service->loadObject($parent_uuid, IZI_APICONTENT_TYPE_MTG_OBJECT, MultipleFormInterface::FORM_COMPACT);
      /** @var \Triquanta\IziTravel\DataType\PaidDataInterface $parent */
      if ($parent_object instanceof PaidDataInterface) {
        $purchase = $parent_object->getPurchase();
        if ($purchase instanceof Purchase) {
          if ($purchase->getPrice()) {
            $context['purchase'] = TRUE;
          }
        }
      }

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
      }

      /** @var \Triquanta\IziTravel\DataType\FullMtgObjectInterface $full_object */
      foreach ($full_objects as $full_object) {
        $tourstop_to_save = $this->izi_apicontent_build_object_view($full_object, NULL, $context);
        $full_object_uuid = $full_object->getUuid();

        // Transfer the previously created previous and next variables to the full view.
        if (!empty($build['#children'][$full_object_uuid]['#previous_id'])) {
          $tourstop_to_save['#previous_id'] = $build['#children'][$full_object_uuid]['#previous_id'];
        }
        if (!empty($build['#children'][$full_object_uuid]['#next_id'])) {
          $tourstop_to_save['#next_id'] = $build['#children'][$full_object_uuid]['#next_id'];
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
          global $base_url;
          $url = $base_url . "/$site_language" . $url;
          $full_object->canonical_url = $url;
        }

        $tourstop_to_save['#share_url'] = $this->object_service->getChildShareUrl($full_object, $parent_object, $query_param);

        // Save the rendered child to file.
        $this->izi_apicontent_save_child_tour($tourstop_to_save);
      }
    }

    $html = \Drupal::service('renderer')->renderRoot($build);
    $response = new Response();
    return $response->setContent($html);
  }

}
