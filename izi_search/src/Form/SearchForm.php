<?php

namespace Drupal\izi_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a izi_search form.
 */
class SearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_search_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attributes'] = [
      'class' => ['izi-search-form'],
    ];

    $form['inputs'] = [
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];
    $form['inputs']['#tree'] = TRUE;

    $form['inputs']['fulltext'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['izi-search-input'],
        'placeholder' => t('Search our audio guide collection (enter location or keyword)'),
      ],
    ];
    // Get path arguments.
    $route_match = \Drupal::routeMatch();
    if ($route_match->getRouteName() === 'izi_search.search_results') {
      $form['inputs']['fulltext']['#default_value'] = $route_match->getParameter('search');
    }

    $form['inputs']['submit'] = [
      '#type' => 'submit',
    // @todo (legacy) check if this works with multilingual, ajax often breaks here
      '#value' => t('Search'),
      '#attributes' => [
        'tabindex' => '-1',
      ],
    ];

    // Determine where we are and render the 'goto' links.
    $form['inputs']['suggestions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['suggestions-wrapper'],
      ],
    ];

    $form['#attached']['library'][] = 'izi_search/izi_search.block';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Use user submitted string from search form to create url to result page.
    $redirect_route = 'izi_search.search_results';
    // Do not sanitize user input here, it will be sanitized in the page callback.
    // Replace some characters as they may break the URL.
    $search = strtr($form_state->getValue(['inputs', 'fulltext']), '/', ' ');
    $form_state->setRedirect($redirect_route, ['search' => $search]);
  }

}
