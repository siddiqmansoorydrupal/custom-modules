<?php

namespace Drupal\izi_apicontent\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

class AddToCartController extends IziApicontentController {

  protected $cartProvider;
  protected $entityTypeManager;
  protected $messenger;

  public function __construct(CartProviderInterface $cartProvider, EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger) {
    $this->cartProvider = $cartProvider;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  public function addToCart(RouteMatchInterface $route_match) {
	  
	// Check if the user is logged in.
    if (\Drupal::currentUser()->isAnonymous()) {
        // If not logged in, redirect to the login page with a destination parameter.
        $current_url = \Drupal::request()->getRequestUri();
        $destination = Url::fromUri('internal:' . $current_url)->toString();
        $url = Url::fromRoute('user.login', [], ['query' => ['destination' => $destination]]);
        return new RedirectResponse($url->toString());
    }  
	
	
    try {
      // Retrieve query parameters.
      $query_params = \Drupal::request()->query;
      $sku = $query_params->get('sku');
	  
	  
	  
	  $currency = $query_params->get('currency');
      $price = $query_params->get('price');
      $title = $query_params->get('title');
      $return = $query_params->get('return');
	  
	  

      // Check if the product already exists.
      $product_storage = $this->entityTypeManager->getStorage('commerce_product');
      $products = $product_storage->loadByProperties(['type' => 'default', 'title' => $title]);
      
      if (empty($products)) {
        // Create a custom product if not found.
        $product = Product::create([
          'type' => 'default',
          'title' => $title,
          'status' => 1,
        ]);
        $product->save();
      } else {
        $product = reset($products);
      }
	  
		$variation = $this->entityTypeManager->getStorage('commerce_product_variation')->loadByProperties(['sku' => $sku]);

		if (!$variation) {
			// Create the price object.
		  $price_obj = new \Drupal\commerce_price\Price($price, $currency);

		  // Create the product variation.
		  $variation = ProductVariation::create([
			'type' => 'default',
			/*'sku' => time() . "_" . $sku,*/
			'sku' => $sku,
			'title' => $title,
			'status' => 1,
			'price' => $price_obj,
			'attribute_values' => [],
			'product_id' => $product->id(),
		  ]);

		  // Set the field_tour_link field.
		  $variation->set('field_tour_link', ['title' => $title, 'uri' => $return]);
		  $variation->set('field_tour_url', $return);
		  
		  // Save the variation and product.
		  $variation->save();
		  
		  $variation = $this->entityTypeManager->getStorage('commerce_product_variation')->loadByProperties(['sku' => $sku]);
		}
		$current_user = \Drupal::currentUser();

		// Check if any variations were found.
		if (!empty($variation)) {
			$variation = reset($variation);
			$variation_id = $variation->id();
		}
		$variation = \Drupal\commerce_product\Entity\ProductVariation::load($variation_id);
		
		$is_purchased = $this->checkIfVariationPurchasedByCurrentUser($current_user, $variation); 
		
		if ($is_purchased) {
			$response = new RedirectResponse($return);
			$response->send();
			return true;
		}
		
		
      // Get the store ID.
      $store_id = $product->get('stores')->target_id ?? 1;
      $store = $this->entityTypeManager->getStorage('commerce_store')->load($store_id);

      // Get or create the cart.
      $cart = $this->cartProvider->getCart('default', $store);
      if (!$cart) {
        $cart = $this->cartProvider->createCart('default', $store);
      }
	  
	  
		if ($cart) {
			foreach ($cart->getItems() as $cartItem) {
				// Get the purchased product variation from the cart item.
				$purchased_variation = $cartItem->get('purchased_entity')->entity;
				// Check if the SKU matches.
				if ($purchased_variation->getSku() === $sku) {
					// If the same SKU is found, you can handle it here.
					// For example, you can update the quantity or simply return without adding the product again.
					$this->messenger->addWarning($this->t('Tour already exists in the cart.'));
					return $this->redirect('commerce_checkout.form', ['commerce_order' => $cart->id()]);
				}
			}
		}
	  
	  	
      

      // Add the product variation to the cart.
      $cart_manager = \Drupal::service('commerce_cart.cart_manager');
      $cart_manager->addEntity($cart, $variation);
      
      // Redirect to the cart page.
	  return $this->redirect('commerce_checkout.form', ['commerce_order' => $cart->id()]);
      return $this->redirect('commerce_cart.page');
    } catch (\Exception $e) {
      // Handle exceptions.
      $this->messenger->addError($this->t('An error occurred: @message', ['@message' => $e->getMessage()]));
    }
    // Redirect to the cart page in case of error.
    return $this->redirect('commerce_cart.page');
  }
}
