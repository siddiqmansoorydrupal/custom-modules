<?php

namespace Drupal\stripe_pay\Plugin\Field\FieldFormatter;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'stripe_payment' formatter.
 *
 * @FieldFormatter(
 *   id = "stripe_payment_default_formatter",
 *   module = "stripe_pay",
 *   label = @Translation("Default Formatter"),
 *   field_types = {
 *     "stripe_payment"
 *   }
 * )
 */
class StripePaymentDefaultFormatter extends FormatterBase
{
    protected $default_unit = "USD";

    /**
      * {@inheritdoc}
      */
    public static function defaultSettings()
    {
        $stripe_pay_config = \Drupal::config('stripe_pay.settings');

        $currency_code = $stripe_pay_config->get('currency_code')??'USD';

        return [
          'currency_code' => $currency_code,
          'price_format' => 'format1',
          'show_quantity' => false,
          'button_text' => 'Pay Now',
        ] + parent::defaultSettings();
    }

     /**
       * {@inheritdoc}
       */
     public function settingsForm(array $form, FormStateInterface $form_state)
     {
         $stripe_pay_config = \Drupal::config('stripe_pay.settings');

         $currency_code = $stripe_pay_config->get('currency_code')??'USD';

         $currency_sign = stripe_currency_sign($currency_code);
         $element = parent::settingsForm($form, $form_state);
         $currency_codes = stripe_currency_codes();
         $price_formats = stripe_price_formats($currency_code, $currency_sign);

         $element['price_format'] = [
           '#type' => 'select',
           '#title' => $this->t('Price Format'),
           '#description' => $this->t('Select the price format'),
           '#options' => $price_formats,
           '#default_value' => $this->getSetting('price_format')??'',
           '#required' => false
         ];

         $element['show_quantity'] = [
           '#type' => 'checkbox',
           '#title' => $this->t('Show quantity input field'),
           '#description' => $this->t('Display quantity field'),
           '#default_value' => $this->getSetting('show_quantity')??false,
         ];

         $element['button_text'] = [
           '#type' => 'textfield',
           '#title' => $this->t('Button Text'),
           '#description' => $this->t('Enter the button text'),
           '#default_value' => $this->getSetting('button_text')??'Pay Now',
           '#required' => true
         ];

         return $element;
     }

     /**
      * {@inheritdoc}
      */
     public function settingsSummary()
     {
         $stripe_pay_config = \Drupal::config('stripe_pay.settings');

         $currency_code = $stripe_pay_config->get('currency_code');

         $price_format = $this->getSetting('price_format');
         $show_quantity = $this->getSetting('show_quantity');
         $button_text = $this->getSetting('button_text');

         $show_quantity = $show_quantity ? 'True' : 'False';

         $currency_sign = stripe_currency_sign($currency_code);
         $price_formats = stripe_price_formats($currency_code, $currency_sign);
         $price_format = $price_formats[$price_format];

         $summary_content = 'Currency code: '.$currency_code.'<br>';
         $summary_content .= 'Price format: '.$price_format.'<br>';
         $summary_content .= 'Show quantity input field: '.$show_quantity.'<br>';
         $summary_content .= 'Button Text: '.$button_text.'<br>';

         // Implement settings summary.
         $summary[] = $this->t($summary_content);

         return $summary;
     }

       /**
        * Builds a renderable array for a field value.
        *
        * @param \Drupal\Core\Field\FieldItemListInterface $items
        *   The field values to be rendered.
        * @param string $langcode
        *   The language that should be used to render the field.
        *
        * @return array
        *   A renderable array for $items, as an array of child elements keyed by
        *   consecutive numeric indexes starting from 0.
        */
       public function viewElements(FieldItemListInterface $items, $langcode)
       {
           $elements = array();
           $stripe_pay_config = \Drupal::config('stripe_pay.settings');

           $currency_code = $stripe_pay_config->get('currency_code');

           $price_format = $this->getSetting('price_format');
           $show_quantity = $this->getSetting('show_quantity');
           $button_text = $this->getSetting('button_text');

           $currency_sign = stripe_currency_sign($currency_code);

           $current_url = \Drupal::request()->getUri();

           foreach ($items as $delta => $item) {
               $node = $item->getEntity();
               $price = $item->stripe_payment;

               // Get the node title.
               $title = $node->label();
               $nid = $node->id();

               $stripe_payment_data = array(
                 'nid' => $nid,
                 'title' => $title,
                 'price' => $price,
                 'current_url' => $current_url,
                 'currency_code' => $currency_code,
                 'show_quantity' => $show_quantity,
                 'price_format' => $price_format,
                 'button_text' => $button_text,
                 'currency_sign' => $currency_sign,
               );

               $other_data['suffix'] = [];
               $elements[$delta] = array(
                 '#theme' => 'stripe_payment',
                 '#stripe_payment_data' => $stripe_payment_data,
                 '#other_data' => $other_data,
               );
           }

           $payment_init_url = Url::fromRoute('stripe_pay.payment_init', [], ['absolute' => true])->toString();

           $stripe_pay_config = \Drupal::config('stripe_pay.settings');

           $currency_code = $stripe_pay_config->get('currency_code');
           $test_mode = $stripe_pay_config->get('test_mode');
           $publishable_key = '';
           if($test_mode) {
               $publishable_key = $stripe_pay_config->get('publishable_key_test');
           } else {
               $publishable_key = $stripe_pay_config->get('publishable_key_live');
           }

           $elements['#attached']['library'][] = 'stripe_pay/stripe_pay';
           $elements['#attached']['drupalSettings']['payment_init_url'] = $payment_init_url;
           $elements['#attached']['drupalSettings']['stripe_pk'] = $publishable_key;

           return $elements;
       }
}
