<?php

namespace Drupal\izi_apicontent\Controller;

use Drupal\Core\Controller\ControllerBase; // Add this line

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\commerce_price\Entity\Currency;

/**
 * Controller for displaying currency modal.
 */
class IziApicurrencyController extends ControllerBase {


  /**
   * Displays the currency modal.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function currencyModal() {
    // HTML content for the currency modal.
	$dynamic_currency=[];
	// Get the enabled currencies configuration.
	
	$currencies = Currency::loadMultiple();
	$selected_currency_code = \Drupal::service('commerce_currency_resolver.current_currency')->getCurrency();

	$dynamic_currency = [];
	$top_currency = [];

	foreach ($currencies as $currency) {
		// Access currency properties.
		$currency_code = $currency->getCurrencyCode();
		$currency_name = $currency->label();
		$currency_symbol = $currency->getSymbol();

		// Check if this currency is selected
		$selected = "";
		if($currency_code === $selected_currency_code){
			$selected = "active";
		}
		
		
		if(in_array($currency_code,['AUD','USD','GBP','EUR','CAD','ZAR'])){
			$top_currency[] = [
				'name' => $currency_name,
				'code' => $currency_code,
				'symbol' => $currency_symbol,
				'selected' => $selected // Add selected property
			];
			
		}else{
			// Do something with the currency information.
			$dynamic_currency[] = [
				'name' => $currency_name,
				'code' => $currency_code,
				'symbol' => $currency_symbol,
				'selected' => $selected // Add selected property
			];
		}
		
	}
	
	// Loop through the enabled currencies and print them.
	foreach ($commerce_enabled_currencies as $currency) {
	  //if ($currency != '0') {		  
		$dynamic_currency[]=['name' => $currency, 'code' => $currency, 'symbol' => $currency];
	  //}
	}

	
	
	
    $content = [
      '#theme' => 'izi_apicontent_currency_modal',
      '#items' => [
        [
          'label' => 'Top Currencies',
          'currencies' => $top_currency,
        ],
		[
          'label' => 'Other Currencies',
          'currencies' => $dynamic_currency,
        ],/*
		[
          'label' => 'Top Currencies',
          'currencies' => [
            ['name' => 'Australian Dollar', 'code' => 'AUD', 'symbol' => 'A$'],
            ['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$'],
            ['name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£'],
            ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€'],
            ['name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => '$'],
            ['name' => 'South African Rand', 'code' => 'ZAR', 'symbol' => 'R'],
          ],
        ],
        [
          'label' => 'Other Currencies',
          'currencies' => [
            ['name' => 'Swiss Franc', 'code' => 'CHF', 'symbol' => 'CHF'],
            ['name' => 'New Zealand Dollar', 'code' => 'NZD', 'symbol' => '$'],
            ['name' => 'Swedish Krona', 'code' => 'SEK', 'symbol' => 'kr'],
            ['name' => 'Russian Ruble', 'code' => 'RUB', 'symbol' => '₽'],
            ['name' => 'Danish Krone', 'code' => 'DKK', 'symbol' => 'kr'],
            ['name' => 'Israeli New Shekel', 'code' => 'ISL', 'symbol' => '₪'],
            ['name' => 'Brazilian Real', 'code' => 'BRL', 'symbol' => 'R$'],
            ['name' => 'Singapore Dollar', 'code' => 'SGD', 'symbol' => 'S$'],
            ['name' => 'Indian Rupee', 'code' => 'INR', 'symbol' => '₹'],
          ],
        ],*/
      ],
    ];

    // Create an AJAX response with the modal dialog.
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(t('Select Currency'), $content, ['width' => '800']));
    return $response;
  }

}
