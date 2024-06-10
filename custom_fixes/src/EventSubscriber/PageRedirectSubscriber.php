<?php

namespace Drupal\custom_fixes\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\taxonomy\Entity\Term;

/**
 * Class PageRedirectSubscriber.
 */
class PageRedirectSubscriber implements EventSubscriberInterface
{

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $events[KernelEvents::RESPONSE][] = ['onResponse'];
        return $events;
    }




    public function onResponse(ResponseEvent $event)
    {
        // Check if this is the desired page.
        if (\Drupal::routeMatch()->getRouteName() == 'entity.commerce_product.canonical') {
            $current_path = \Drupal::service('path.current')->getPath();
            $arg = explode('/', $current_path);
            if ($arg[1] == 'product' && is_numeric($arg[2])) {
                if (!empty($arg[2])) {
                    $productID = $arg[2];
                    $product = \Drupal\commerce_product\Entity\Product::load($productID);
                    $tele_part = $product->get('field_tele_part')->value;
                    $category = $product->get('field_category_taxonomy')->getValue();
                    $response = $event->getResponse();
                    $response->setStatusCode(302);
                    if ($category && $tele_part) {
                        $product_category = Term::load($category[0]['target_id'])->get('name')->value;
                        $path = '/order-online/' . str_replace(' ', '-', strtolower($product_category));
                        $redirect_path = $path . '/' . $tele_part;
                        
                        $response->headers->set('Location', $redirect_path);
                    } else {
                       /* $response->headers->set('Location', '/home');*/
                    } 
                }
            }
        }
    }
}