<?php

namespace Drupal\izi_credit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Entity\Currency;
use Drupal\Core\Database\Database;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\ValidationException;
use Drupal\file\Entity\File;
use Drupal\Core\Url;

class ResellerCouponForm extends FormBase {

  public function getFormId() {
    return 'izi_credit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $selected_currency_code = $this->getCurrentCurrencyCode();
    $selected_currency_symbol = $this->getCurrencySymbol($selected_currency_code);

    $current_user_id = \Drupal::currentUser()->id();
    $products = $this->getResellerProducts($current_user_id);

    $selected_products = $form_state->getValue('products', []);

    $form['products'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select Products'),
      '#options' => [],
      '#default_value' => array_keys(array_filter($selected_products)),
    ];

    foreach ($products as $product) {
      $variation = $product->getDefaultVariation();
      $price = $variation->getPrice();
      $current_price = $this->convertPrice($price, $selected_currency_code);
      $form['products']['#options'][$variation->id()] = $product->getTitle() . ' - ' . $current_price;
    }

    $totalUsablePoints = $this->getTotalUsablePoints();
    $coin_value_amount = $this->calculateCoinValue($totalUsablePoints, $selected_currency_code);

    $form['credits'] = [
      '#markup' => $this->t('You have izi Credits at the moment') . " : <b> " . $totalUsablePoints . " ( " . $coin_value_amount . " ) </b> ",
    ];

    // Add the Expire Date field
    $form['expiry_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Expire Date'),
      '#default_value' => \Drupal::service('date.formatter')->format(strtotime('+1 month'), 'custom', 'Y-m-d'),
      '#required' => TRUE,
      '#description' => $this->t('Select the expiration date for the coupon.'),
    ];

    // Add the actions and the submit button
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Generate Coupon'),
      ],
    ];

    // Adding the HTML table with non-empty coupon codes
    $form['coupon_table'] = [
      '#type' => 'markup',
      '#markup' => $this->getCouponTableHtml($current_user_id),
    ];

    return $form;
}




  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_currency_code = $this->getCurrentCurrencyCode();
    $totalUsablePoints = $this->getTotalUsablePoints();
    $coin_value_amount = $this->calculateCoinValue($totalUsablePoints, $selected_currency_code);

    $variation_ids = array_filter($form_state->getValue('products'));
    $variations = ProductVariation::loadMultiple($variation_ids);
    $products = [];

    foreach ($variations as $variation) {
      $product = $variation->getProduct();
      if ($product) {
        $products[$variation->id()] = $product;
      }
    }

    $total_price = 0;
    foreach ($products as $product) {
      $variation = $product->getDefaultVariation();
      $price = $variation->getPrice();
      $total_price += $price->getNumber();
    }

    // Check if total price exceeds coin value amount
    if ($total_price > $coin_value_amount->getNumber()) {
      \Drupal::messenger()->addError($this->t('The total price of the selected products exceeds your available izi Credits.'));
      $form_state->setRebuild();
      return;
    }

    $reseller_uid = \Drupal::currentUser()->id();
    $connection = \Drupal::database();

    $coupon_code = $this->generateUniqueCouponCode();
    $created = \Drupal::time()->getRequestTime();
    $expiry_date = $form_state->getValue('expiry_date');

    foreach ($products as $product_id=>$product) {

	  $variation = $product->getDefaultVariation();
	  $price = $variation->getPrice();
      $connection->insert('izi_credit')
        ->fields([
          'product_id' => $product_id,
          'reseller_uid' => $reseller_uid,
          'coupon_code' => $coupon_code,
          'created' => $created,
          'expiry_date' => strtotime($expiry_date),
          'tour_price' => $price->getNumber(),
        ])
        ->execute();
    }

    \Drupal::messenger()->addStatus($this->t('Coupon created with code: @code', ['@code' => $coupon_code]));
 }


  private function getCurrentCurrencyCode() {
    return \Drupal::service('commerce_currency_resolver.current_currency')->getCurrency();
  }

  private function getCurrencySymbol($currency_code) {
    $currency = Currency::load($currency_code);
    return $currency->getSymbol();
  }

  private function getResellerProducts($reseller_uid) {
    $connection = Database::getConnection();
    $query = $connection->select('izi_credit', 'ic')
      ->fields('ic', ['product_id'])
      ->condition('ic.reseller_uid', $reseller_uid)
      ->condition('ic.coupon_code', "")
      ->execute();

    $variation_ids = $query->fetchCol();
    $variations = ProductVariation::loadMultiple($variation_ids);
    $products = [];

    foreach ($variations as $variation) {
      $product = $variation->getProduct();
      if ($product) {
        $products[$product->id()] = $product;
      }
    }

    return $products;
  }

  private function convertPrice(Price $price, $currency_code) {
    return \Drupal::service('commerce_currency_resolver.calculator')->priceConversion($price, $currency_code);
  }

  private function getTotalUsablePoints() {
    $arrNidPoints = availableUsablePoints();
    return round($arrNidPoints['total_usable_points']);
  }

  private function calculateCoinValue($totalUsablePoints, $currency_code) {
    $coin_value = $totalUsablePoints / 10;
    $coin_value = new Price($coin_value, 'EUR');
    return $this->convertPrice($coin_value, $currency_code);
  }

  private function generateUniqueCouponCode($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $coupon_code = '';
    for ($i = 0; $i < $length; $i++) {
      $coupon_code .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $coupon_code;
  }

  private function generateQrCode($coupon_code) {
    
    // Initialize the writer
    $writer = new PngWriter();

    try {
        // Create QR code
        $qrCode = new QrCode($coupon_code);
        $qrCode->setEncoding(new Encoding('UTF-8'))
            ->setSize(300)
            ->setMargin(10)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        // Write QR code to PNG
        $result = $writer->write($qrCode);

        // Save QR code to a file
        file_put_contents('public://qr_codes/' . $coupon_code . '.png', $result->getString());
    } catch (ValidationException $e) {
        // Log the error and handle it gracefully
        \Drupal::logger('izi_credit')->error('QR Code generation error: @error', ['@error' => $e->getMessage()]);
    }
  }

  public function getCouponTableHtml($reseller_uid) {
    $connection = Database::getConnection();
    $query = $connection->select('izi_credit', 'ic')
        ->fields('ic', ['cid', 'coupon_code', 'created', 'expiry_date', 'used'])
        ->groupBy('ic.coupon_code')
        ->condition('ic.reseller_uid', $reseller_uid)
        ->condition('ic.coupon_code', '', '!=');

    $rows = $query->execute()->fetchAll();

    if (empty($rows)) {
        return '<p>No coupons available.</p>';
    }

    $header = ['QR Code', 'Coupon Code', 'Tours', 'Created', 'Expire', 'Delete'];
    $rows_html = '';

    foreach ($rows as $row) {
        // Generate QR code for coupon code
        $this->generateQrCode($row->coupon_code); 


		$_query = $connection->select('izi_credit', 'ic')
        ->fields('ic', ['product_id'])
        ->condition('ic.reseller_uid', $reseller_uid)
        ->condition('ic.coupon_code', $row->coupon_code);
		$_rows = $_query->execute()->fetchAll();

		$product_inform ='';

		foreach ($_rows as $_rows) {
			$product_variation = ProductVariation::load($_rows->product_id);
      
			if ($product_variation) {
				$product = $product_variation->getProduct();
				if ($product) {
					$product_title = $product->getTitle();
					$price = $product_variation->getPrice();
					
					if(!empty($product_inform)){
						$product_inform .='<br>';
					}
					$product_inform .='<br>';					
					$product_inform .= '<a class="" href="#">'.$product_title.'</a> <b>Price:</b> ' . $price;
					
				}
				
			}
		}
        
        

        // Inside your method
		$qr_code_uri = 'public://qr_codes/' . $row->coupon_code . '.png';
		// Get the file system path to the public files directory
		$public_files_path = \Drupal::service('file_system')->realpath("public://");
		// Replace "public://" with the public files directory path
		$qr_code_path = str_replace('public://', $public_files_path . '/', $qr_code_uri);

		// Get the default language
		$language = \Drupal::languageManager()->getDefaultLanguage();
		// Get the base URL of the site without language prefix
		$base_url = Url::fromRoute('<front>', [], ['language' => NULL])->setAbsolute()->toString();
		// Generate the URL relative to the base URL
		$qr_code_url = $base_url . substr($qr_code_path, strlen(DRUPAL_ROOT));
		$qr_code_url = substr($qr_code_path, strlen(DRUPAL_ROOT));

		$delete_url = Url::fromRoute('izi_credit.delete_coupon', ['coupon_code' => $row->coupon_code])->toString();

        $rows_html .= '<tr>';
        $rows_html .= '<td><img src="' . $qr_code_url . '" alt="QR Code" width="100px"></td>';
        
		
		if($row->used==1){
			$rows_html .= '<td><span class="strike">' . $row->coupon_code . '</span><br><span class="red-aleart">This Coupon code has been already redeemed</span></td>';
		}else{
			$rows_html .= '<td>' . $row->coupon_code . '</td>';
		}
		
		
		$rows_html .= '<td>' . $product_inform . '</td>';
        
        $rows_html .= '<td>' . date('Y-m-d H:i:s', $row->created) . '</td>';
        $rows_html .= '<td>' . date('Y-m-d H:i:s', $row->expiry_date) . '</td>';
		
		if($row->used==1){
			$rows_html .= '<td></td>';
		}else{
			$rows_html .= '<td><a class="checkconfirm" href="' . $delete_url . '" onclick="return confirm(\'Are you sure you want to delete this coupon?\');">Delete</a></td>';
		}
		
		
		
        $rows_html .= '</tr>';
    }

    $table_html = '<table>';
    $table_html .= '<thead><tr><th>' . implode('</th><th>', $header) . '</th></tr></thead>';
    $table_html .= '<tbody>' . $rows_html . '</tbody>';
    $table_html .= '</table>';

    return $table_html;
 }

 public function deleteCoupon($coupon_code) {
    $connection = Database::getConnection();
    $connection->delete('izi_credit')
      ->condition('coupon_code', $coupon_code)
      ->execute();
    \Drupal::messenger()->addStatus($this->t('Coupon with code @code has been deleted.', ['@code' => $coupon_code]));
 }

}
