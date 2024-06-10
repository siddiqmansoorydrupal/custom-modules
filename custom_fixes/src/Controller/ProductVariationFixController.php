<?php

namespace Drupal\custom_fixes\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class ProductVariationFixController.
 */
class ProductVariationFixController extends ControllerBase
{

  /**
   * Main.
   *
   * @return string
   *   Return Hello string.
   */
  public function main()
  {

    $prod = \Drupal\commerce_product\Entity\Product::load(480);
    d_limit($prod,3);

    exit;
    $output = [];

    $output['title']['#markup'] = '<h1>' . $this->t('Product Variation Fix') . '</h1>';


    $output['description'] = [
      '#markup' => '<p>' . $this->t('This module is used to fix errors or show errors.') . '</p>',
    ];

   

    $output['variants'] = $this->getBrokenVariants();
    $output['products'] = $this->getBrokenProducts();

    // exit;

    return $output;
  }

  private function getBrokenVariants()
  {
    $variations = \Drupal\commerce_product\Entity\ProductVariation::loadMultiple();

    $broken = [];

    foreach ($variations as $variation) {
      $vid = $variation->id();
      $product = $variation->getProduct();
      if (!$product) {
        $broken[$vid] = $variation;
      }
      // $variation->delete();
    }
    // return;


    $form['mytable'] = array(
      '#type' => 'table',
      '#header' => array(t('ID'), t('Label'),t("sku") ,  t('Operations')),
      '#caption' => "Broken variations ".count($broken),
      '#empty' => $this->t("No broken elements available."),
      '#tableselect' => TRUE,
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'mytable-order-weight',
        ),
      ),
    );

    foreach ($broken as $id => $entity) {
      // TableDrag: Mark the table row as draggable.
      $form['mytable'][$id]['#attributes']['class'][] = 'draggable';
      // TableDrag: Sort the table row according to its existing/configured weight.
    
      $form['mytable'][$id]['id'] = array(
        '#plain_text' => $entity->id(),
      );
      // Some table columns containing raw markup.
      $form['mytable'][$id]['label'] = array(
        '#plain_text' => $entity->label(),
      );
      // Some table columns containing raw markup.
      $form['mytable'][$id]['sku'] = array(
        '#plain_text' => $entity->sku->getValue()[0]['value'],
      );
  
  
      // Operations (dropbutton) column.
      $form['mytable'][$id]['operations'] = array(
        '#type' => 'operations',
        '#links' => array(),
      );
      $form['mytable'][$id]['operations']['#links']['edit'] = array(
        'title' => t('Edit'),
        'url' => \Drupal\Core\Url::fromRoute('<front>', array('id' => $id)),
      );
      
      $form['mytable'][$id]['operations']['#links']['delete'] = array(
        'title' => t('Delete'),
        'url' => \Drupal\Core\Url::fromRoute('<front>', array('id' => $id)),
      );
    }

    return $form;
  }

  private function getBrokenProducts()
  {

    $products = \Drupal\commerce_product\Entity\Product::loadMultiple();

    $broken = [];

    foreach($products as $product)
    {
      $variationsIDs = $product->getVariationIds();
      
      $id = $product->id();
      $broken[$id] = $product;
      if (!count($variationsIDs)) {
        
      }
    }
    
    $form['mytable'] = array(
      '#type' => 'table',
      '#header' => array(t('ID'), t('Label'), t('Operations')),
      '#caption' => "Broken variations ".count($broken)." OF TOTAL ".count($products),
      '#empty' => $this->t("No broken elements available."),
      '#tableselect' => TRUE,
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'mytable-order-weight',
        ),
      ),
    );
    foreach ($broken as $id => $entity) {
      // TableDrag: Mark the table row as draggable.
      $form['mytable'][$id]['#attributes']['class'][] = 'draggable';
      // TableDrag: Sort the table row according to its existing/configured weight.
    
      $form['mytable'][$id]['id'] = array(
        '#plain_text' => $entity->id(),
      );
      // Some table columns containing raw markup.
      $form['mytable'][$id]['label'] = array(
        '#plain_text' => $entity->label(),
      );
     
      // Some table columns containing raw markup.
      $form['mytable'][$id]['sku'] = array(
        '#plain_text' => $entity->label(),
      );
     
  
  
      // Operations (dropbutton) column.
      $form['mytable'][$id]['operations'] = array(
        '#type' => 'operations',
        '#links' => array(),
      );
      $form['mytable'][$id]['operations']['#links']['edit'] = array(
        'title' => t('Edit'),
        'url' => \Drupal\Core\Url::fromRoute('<front>', array('id' => $id)),
      );
      
      $form['mytable'][$id]['operations']['#links']['delete'] = array(
        'title' => t('Delete'),
        'url' => \Drupal\Core\Url::fromRoute('<front>', array('id' => $id)),
      );
    }


    return $form;
  }

}
