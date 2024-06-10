<?php

namespace Drupal\izi_reviews\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\StatusMessages;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Drupal\izi_libizi\Libizi;
use Drupal\izi_reviews\Ajax\IziReviewsPostErrorCommand;
use Drupal\izi_reviews\Ajax\IziReviewsPostSuccessCommand;
use Drupal\izi_reviews\Ajax\IziReviewsRemoveReviewForm;
use Drupal\izi_reviews\Ajax\IziReviewsRestoreReviewForm;
use Drupal\izi_reviews\HelpersService;
use Drupal\izi_reviews\ReviewsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\IziTravel\DataType\MultipleFormInterface;
use Triquanta\IziTravel\DataType\ReviewPostable;

/**
 * Provides a izi_reviews form.
 */
class ReviewForm extends FormBase {

  /**
   * The izi_libizi.libizi service.
   *
   * @var \Drupal\izi_libizi\Libizi
   */
  protected Libizi $libizi;

  /**
   * Review service.
   *
   * @var \Drupal\izi_reviews\ReviewsService
   */
  protected ReviewsService $reviews_service;

  /**
   * Helpers service.
   *
   * @var \Drupal\izi_reviews\HelpersService
   */
  protected HelpersService $helpers_service;

  /**
   * The IziObjectService service.
   *
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected IziObjectService $object_service;

  /**
   * The izi_apicontent.language_service service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected LanguageService $language_service;

  /**
   * ModalFormContactController constructor.
   *
   * @param \Drupal\izi_libizi\Libizi $libizi
   * @param \Drupal\izi_reviews\ReviewsService $reviews_service
   * @param \Drupal\izi_reviews\HelpersService $helpers_service
   */
  public function __construct(
    Libizi $libizi,
    ReviewsService $reviews_service,
    HelpersService $helpers_service,
    IziObjectService $object_service,
    LanguageService $language_service
  ) {
    $this->reviews_service = $reviews_service;
    $this->helpers_service = $helpers_service;
    $this->libizi = $libizi;
    $this->object_service = $object_service;
    $this->language_service = $language_service;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('izi_libizi.libizi'),
      $container->get('izi_reviews.reviews_service'),
      $container->get('izi_reviews.helpers_service'),
      $container->get('izi_apicontent.izi_object_service'),
      $container->get('izi_apicontent.language_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_reviews_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uuid = NULL) {

    /** @var \Drupal\honeypot\HoneypotService $honeyPot */
    $honeyPot = \Drupal::service('honeypot');
    $honeyPot->addFormProtection($form, $form_state, ['honeypot', 'time_restriction']);

    // Set form theme to use template.
    $form['#theme'] = 'izi_reviews_post_review_form';

    // Set Wrapper to enable form ajax.
    $form['#prefix'] = '<div class="izi_reviews_post_review_form_wrapper">';
    $form['#suffix'] = '</div>';

    $form['#attributes'] = [
      'class' => [
        'form',
        'form--reviews',
      ],
    ];

    $form['stars'] = [
      // @todo (legacy) change rating type.
      '#type' => 'hidden',
      '#required' => FALSE,
      '#title' => t('I rate this tour'),
      '#attributes' => [
        'class' => ['form__rating__input'],
        'id' => 'edit-stars',
      ],
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#required' => FALSE,
      '#title' => t('Your name'),
      '#attributes' => [
        'class' => ['form__control'],
      ],
    ];
    $form['text'] = [
      '#type' => 'textarea',
      '#required' => FALSE,
      '#title' => t('Comment (optional)'),
      '#cols' => 60,
      '#rows' => 5,
      '#resizable' => FALSE,
      '#attributes' => [
        'class' => ['form__control'],
      ],
    ];
    // Set language, needed for AJAX.
    // See izi_reviews_post_review() for more info.
    $form['lang'] = [
      '#type' => 'hidden',
      '#default_value' => $this->language_service->get_preferred_language('lang', TRUE, $uuid),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Place review'),
      '#ajax' => [
        'callback' => [$this, 'izi_reviews_post_review_form_submit_ajax'],
        'event' => 'click',
        'keypress' => TRUE,
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
      '#attributes' => [
        'class' => [
          'button',
          'button--cta',
        ],
      ],
    ];
    // @todo Add honeypot protection.
    if (function_exists('honeypot_add_form_protection')) {
      honeypot_add_form_protection($form, $form_state, ['honeypot', 'time_restriction']);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo (legacy) Change error texts after feedback Joost/Adrei.
    $values = $form_state->getValues();

    // (legacy)
    // Check the cookie.
    // Cookie doesn't work for ajax, or there should be cookies for every page.
    // This is kind of ugly so we think about another solution.
    //  if (isset($_COOKIE[IZI_REVIEWS_COOKIE_NAME])) {
    //    form_set_error('name', t('You can only post a review once!'));
    //  }
    // When leaving a name and/or text require to give a rating.
    if (empty($values['stars'])) {
      $form_state->setErrorByName('stars', t('Rating is required when entering text and/or name.'));
    }
    // Be sure a number is submitted.
    elseif (!is_numeric($values['stars'])) {
      $form_state->setErrorByName('stars', t('Stars needs to be a number.'));
    }

    // Check string lengths.
    if (mb_strlen($values['name']) > 128) {
      $form_state->setErrorByName('name', t('The name cannot contain more than 128 characters.'));
    }
    if (mb_strlen($values['text']) > 500) {
      $form_state->setErrorByName('text', t('The review cannot contain more than 500 characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $uuid = $form_state->getBuildInfo()['args'][0];

    $review = [];

    $review['name'] = !empty($values['name']) ? $this->helpers_service->_izi_reviews_sanitize_string($values['name']) : NULL;
    $review['text'] = !empty($values['text']) ? $this->helpers_service->_izi_reviews_sanitize_string($values['text']) : NULL;
    $review['rating'] = !empty($values['stars']) ? $this->helpers_service->_izi_reviews_calculate_rating((int) $values['stars']) : NULL;

    // Submit to the API and add the result to the form_state to be able to use it in the AJAX submit callback.
    // Add to 'values' to make sure it is not persistent between submits.
    $review['text'] = str_replace('%', '', $review['text']);
    try {
      $form_state->setValue('review', $this->izi_reviews_post_review($uuid, $review, $values['lang']));
      $this->messenger()->addStatus($this->t('The message has been sent.'));
    }
    catch (\Exception $e) {
      watchdog_exception('izi_reviews', $e);
      $this->messenger()->addStatus($this->t('Sorry, your review cannot be published at the moment. Please, try again later.'), 'error');
      return NULL;
    }
  }

  /**
   * Post a review.
   *
   * @param string $uuid
   * @param array $review
   *
   * @return \Triquanta\IziTravel\DataType\ReviewPostResponse|void
   *
   * @throws \Exception
   */
  private function izi_reviews_post_review($uuid, array $review, $language = NULL) {

    if (empty($review)) {
      return NULL;
    }

    // Get the object to review (parent).
    // Note object is loaded for the interface language in case of an AJAX callback.
    $object_to_review = $this->object_service->loadObject(
      $uuid,
      IZI_APICONTENT_TYPE_MTG_OBJECT,
      MultipleFormInterface::FORM_COMPACT
    );
    // Hash is identical per language.
    $hash = $object_to_review->getRevisionHash();
    // Set language from object (In case of AJAX always set $language manually).
    if (empty($language)) {
      $language = $object_to_review->getLanguageCode();
    }
    // Create new postable review object.
    $postable_review = new ReviewPostable();
    $postable_review->setContentUuid($uuid);
    // $postable_review->setContentUuid('testfail'); Uncomment to test fail.
    $postable_review->setContentHash($hash);
    $postable_review->setLanguage($language);
    // Loop over user entered properties.
    foreach ($review as $key => $value) {
      switch ($key) {
        case 'rating':
          $postable_review->setRating($value);
          break;

        case 'text':
          $postable_review->setReviewText($value);
          break;

        case 'name':
          $postable_review->setReviewName($value);
          break;
      }
    }
    // Post the review.
    $post_request = $this->libizi->getLibiziClient()->postReviewByUid($postable_review);
    return $post_request->execute();
  }

  /**
   * Post review form submit function AJAX.
   */
  public function izi_reviews_post_review_form_submit_ajax($form, $form_state) {

    $response = new AjaxResponse();
    $renderer = \Drupal::service('renderer');

    $form_id = Html::cleanCssIdentifier($form['#form_id']);

    if (!empty($form_state->getValue('review'))) {
      // Create postable review.
      /** @var \Triquanta\IziTravel\DataType\Review $newReview */
      $newReview = $form_state->getValue('review')->getPostedReview();
      // Append new comment via custom JS callback function.
      // Only insert review if we have some text.
      $review_name = $newReview->getReviewName();
      $review_text = $newReview->getReviewText();
      if (!empty($review_name) || !empty($review_text)) {
        // Append new comment via custom JS callback function.
        $rendered_reviews = $renderer->render(
          $this->helpers_service->_izi_reviews_list_reviews_render([$newReview])
        );
        $response->addCommand(new IziReviewsPostSuccessCommand($rendered_reviews));
      }
      $response->addCommand(new IziReviewsRemoveReviewForm());
      $success = TRUE;
    }
    else {
      $messages = StatusMessages::renderMessages('error');
      $rendered_mesg = $renderer->render($messages);
      $response->addCommand(new IziReviewsPostErrorCommand($rendered_mesg));
    }
    if (!$success) {
      // Custom function that we will call ourselves in JS.
      $response->addCommand(new IziReviewsRestoreReviewForm());
    }
    return $response;
  }

}
