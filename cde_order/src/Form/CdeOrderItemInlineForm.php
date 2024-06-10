<?php

namespace Drupal\cde_order\Form;

use Drupal\commerce_order\Form\OrderItemInlineForm;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines the inline form for order items.
 */
class CdeOrderItemInlineForm extends OrderItemInlineForm {

  /**
   * {@inheritdoc}
   */
  public function getTableFields($bundles) {
    $fields = parent::getTableFields($bundles);

    $fields['label'] = [
      'type' => 'callback',
      'callback' => [static::class, 'getOrderItemlabel'],
      'label' => t(''),
      'weight' => 1,
    ];
    $fields['box_count'] = [
      'type' => 'callback',
      'callback' => [static::class, 'getBoxCount'],
      'label' => t('Box Count'),
      'weight' => 2,
    ];
    $fields['unit_price']['label'] = t('Price Per Box');
    $fields['unit_price']['weight'] = 3;
    $fields['quantity']['weight'] = 4;
    $fields['total_pieces'] = [
      'type' => 'callback',
      'callback' => [static::class, 'getTotalPieces'],
      'label' => t('Total Pieces'),
      'weight' => 5,
    ];
    $fields['total'] = [
      'type' => 'callback',
      'callback' => [static::class, 'getTotal'],
      'label' => t('Total'),
      'weight' => 6,
    ];

    unset($fields['type']);
    return $fields;
  }

  /**
   * Returns the product ID for the purchased entity.
   */
  public static function getOrderItemlabel($entity, $variables) {
    $purchased_entity = $entity->getPurchasedEntity();

    $product = NULL;
    if ($purchased_entity instanceof ProductVariationInterface) {
      $product = $purchased_entity->getProduct();
    }
    elseif ($purchased_entity instanceof ProductInterface) {
      $product = $purchased_entity;
    }

    if (!$product) {
      return '-';
    }

    $field_supplier_sku = $product->get('field_supplier_sku')->value;
    $link_title = $product->getTitle() . ' (' . $field_supplier_sku . ')';
    $link_url = Url::fromRoute('entity.commerce_product.edit_form', ['commerce_product' => $product->id()]);

    if ($product->get('field_category_taxonomy')->isEmpty()) {
      return '<div class="long-description">' . Link::fromTextAndUrl($link_title, $link_url)->toString() . '</div>';
    }

    $field_category_taxonomy = $product->get('field_category_taxonomy')->referencedEntities()[0];
    $path = '/order-online/' . str_replace(' ', '-', strtolower($field_category_taxonomy->getName()));
    $link_url = $path;
    if (!$product->get('field_tele_part')->isEmpty()) {
      $link_url = $path . '/' . $product->get('field_tele_part')->value;
    }

    return '<div class="long-description">' . Link::fromTextAndUrl($link_title, Url::fromUserInput($link_url))->toString() . '</div>';
  }

  /**
   * Returns the product ID for the purchased entity.
   */
  public static function getBoxCount($entity, $variables) {
    $purchased_entity = $entity->getPurchasedEntity();

    $product = NULL;
    if ($purchased_entity instanceof ProductVariationInterface) {
      $product = $purchased_entity->getProduct();
    }
    elseif ($purchased_entity instanceof ProductInterface) {
      $product = $purchased_entity;
    }

    if (!$product) {
      return '-';
    }

    return number_format($product->get('field_quantity_per_box')->getString());
  }

  /**
   * Returns the product ID for the purchased entity.
   */
  public static function getTotalPieces($entity, $variables) {
    $purchased_entity = $entity->getPurchasedEntity();

    $product = NULL;
    if ($purchased_entity instanceof ProductVariationInterface) {
      $product = $purchased_entity->getProduct();
    }
    elseif ($purchased_entity instanceof ProductInterface) {
      $product = $purchased_entity;
    }

    if (!$product) {
      return '-';
    }

    $qty = $entity->getQuantity();
    $field_quantity_per_box = $product->get('field_quantity_per_box')->value;
    return number_format($field_quantity_per_box * $qty);
  }

  /**
   * Returns the product ID for the purchased entity.
   */
  public static function getTotal($entity, $variables) {
    $price = $entity->getTotalPrice();
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');

    return $currency_formatter->format($price->getNumber(), $price->getCurrencyCode());
  }

}
