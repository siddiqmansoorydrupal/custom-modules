<?php

namespace Drupal\izi_commerce\Plugin\rest\resource;

use Drupal\cognito\Aws\CognitoInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\Plugin\rest\resource\EntityResourceAccessTrait;
use Drupal\rest\Plugin\rest\resource\EntityResourceValidationTrait;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\rest\ResourceResponse;
use Drupal\Component\Serialization\Json;
use Drupal\izi_commerce\Services\CommerceCredits;

/**
 * User redeem resource.
 *
 * @RestResource(
 *   id = "user_redeem_resource",
 *   label = @Translation("User redeem resource"),
 *   uri_paths = {
 *     "create" = "user/redeem",
 *   },
 * )
 */
class UserRedeemResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The cognito service.
   *
   * @var \Drupal\cognito\Aws\Cognito
   */
  protected $cognito;

  /**
   * Drupal\izi_commerce\Services\CommerceCredits definition.
   * 
   * @var Drupal\izi_commerce\Services\CommerceCredits
   */
  protected $commerceCredits;

  /**
   * Constructs a new UserRegistrationResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\cognito\Aws\CognitoInterface $cognito
   *   The Cognito service.
   * @param Drupal\izi_commerce\Services\CommerceCredits $commerce_credits
   *   The CommerceCredits service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountInterface $current_user, CognitoInterface $cognito, CommerceCredits $commerce_credits) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->cognito = $cognito;
    $this->commerceCredits = $commerce_credits;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('cognito.aws'),
      $container->get('izi_commerce.credits')
    );
  }

  /**
   * Redeem the the credit points.
   * 
   * @return 201 on successful redeem
   */
  public function post(Request $request) {
    $request_content = $request->getContent();
    $request_body = Json::decode($request_content);
    $result = $this->commerceCredits->validateAccessToken($request_body['accessToken']);
    // in case of invalide access token
    if (!$result['is_valid']) {
      $error_result = [
        'error' => $result['result']
      ];
      return new ResourceResponse($error_result, 403); 
    }

    // Check if the sufficient point is available and not expired.
    $credit_info = $this->commerceCredits->getCredits($request_body['mail']);
    if (empty($credit_info)) {
      $error_result = [
        'error' => 'No record found.'
      ];
      return new ResourceResponse($error_result, 200); 
    }

    // Check available credit point is less that credit redeem point
    if ($credit_info['credits'] < $request_body['creditPoint']) {
      $error_result = [
        'error' => 'You do not have enough credit points to use for a redemption.'
      ];
      return new ResourceResponse($error_result, 200);
    }
     
    $redeem = $this->commerceCredits->redeemCredits($request_body);
    if ($redeem) {
      $credit_after_update = $this->commerceCredits->getCredits($request_body['mail']);
      $redeem_response = [
        'creditBalance' => $credit_after_update['credits'],
        'error' => ''
      ];
    }
    $response = new ResourceResponse($redeem_response, 201);
    
    return $response;
  }

}
