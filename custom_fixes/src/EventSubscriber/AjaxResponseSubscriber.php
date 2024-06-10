<?php

namespace Drupal\custom_fixes\EventSubscriber;

use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Url;
use Drupal\views\Ajax\ViewAjaxResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
//use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\taxonomy\Entity\Term;

/**
 * Response subscriber to handle AJAX responses.
 */
class AjaxResponseSubscriber implements EventSubscriberInterface {

  /**
   * Renders the ajax commands right before preparing the result.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The response event, which contains the possible AjaxResponse object.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    // Only alter views ajax responses.
    if (!($response instanceof ViewAjaxResponse)) {
      return;
    }

    $view = $response->getView();

    // Only alter commands if view is ours.
    if ($view->storage->id() != 'advance_search') {
      return;
    }

    $filters = $event->getRequest()->query->all();
    if (isset($filters['query'])) {
      $fulltext = trim($filters['query'], ' ');
      if (!empty($fulltext)) {
        if (is_numeric($fulltext)) {
          $query = \Drupal::entityQuery('commerce_product');
          $query->condition('field_tele_part', $fulltext);
		  $query->accessCheck(FALSE);
          $product_ids = $query->execute();
        }else{
          $query = \Drupal::entityQuery('commerce_product');
          $query->condition('field_supplier_sku', $fulltext);
		  $query->accessCheck(FALSE);
          $product_ids = $query->execute();
          if (empty($product_ids)) {
            $query = \Drupal::entityQuery('commerce_product');
            $query->condition('field_part_cross_reference', $fulltext);
			$query->accessCheck(FALSE);
            $product_ids = $query->execute();
          }
        }

        if (!empty($product_ids)) {
          $productID = reset($product_ids);
          $product = \Drupal\commerce_product\Entity\Product::load($productID);
          $tele_part = $product->get('field_tele_part')->value;
          $category = $product->get('field_category_taxonomy')->getValue();
          if ($category) {
            $product_category = Term::load($category[0]['target_id'])->get('name')->value;
            $path = '/order-online/' . str_replace(' ', '-', strtolower($product_category));
            $redirect_path = $path . '/' . $tele_part;
            $url = url::fromUserInput($redirect_path);
            $command = new RedirectCommand($url->toString());
            $response->addCommand($command);
          }
        }
      }
    }

    // $this->alterCommands($commands);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => [['onResponse']]];
  }

}