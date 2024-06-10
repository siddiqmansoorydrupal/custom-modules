<?php

namespace Drupal\izi_libizi;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Triquanta\IziTravel\Client\Client;
use Triquanta\IziTravel\Client\DevelopmentRequestHandler;
use Triquanta\IziTravel\Client\ProductionRequestHandler;
use Triquanta\IziTravel\Client\StagingRequestHandler;

/**
 * Service description.
 */
class Libizi {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  const HUMAN_READABLE_ENVIRONMENT = [
    'test' => 'Testing',
    'stage' => 'Staging',
    'prod' => 'Production',
  ];

  // /**
  //   * The HTTP client.
  //   *
  //   * @var \GuzzleHttp\ClientInterface
  //   */
  //  protected ClientInterface $client;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The Drupal State API service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * Constructs a Libizi object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal State API service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    ClientInterface $client,
    EventDispatcherInterface $event_dispatcher,
    StateInterface $state,
    LoggerChannelFactoryInterface $logger,
    TranslationInterface $string_translation
  ) {
    $this->client = $client;
    $this->eventDispatcher = $event_dispatcher;
    $this->state = $state;
    $this->logger = $logger;
    $this->stringTranslation = $string_translation;
  }

  /**
   *
   */
  public function getLibiziClient() {

    // Get environment and API key.
    $environment = $this->getCurrentEnvironment();
    $api_key = $this->getApiKey($environment);

    // Build the client and its dependencies.
    $event_dispatcher = new EventDispatcher();
    $timeout = 10;
    $http_client_config = [
      'timeout' => $timeout,
    ];
    $http_client = new HttpClient($http_client_config);

    $password = \Drupal::request()->query->get('passcode');

    $request_handler_class = $this->getRequestHandlerClassByEnvironment($environment);
    $request_handler = new $request_handler_class(
      $event_dispatcher,
      $http_client,
      $api_key,
      $password
    );

    $request_handler = new ClientExceptionHandlingRequestHandler($request_handler);
    $client = new Client($request_handler);

    return $client;
  }

  /**
   *
   */
  public function getCurrentEnvironment() {
    /* TODO: define if we analyze domain. For now we use prod */
    return 'prod';
  }

  /**
   *
   */
  public function getHumanReadableEnvironment($environment = NULL) {
    if (!$environment) {
      $environment = $this->getCurrentEnvironment();
    }
    $human_readable_environment = self::HUMAN_READABLE_ENVIRONMENT[$environment];
    return $this->t($human_readable_environment);
  }

  /**
   *
   */
  private function getApiKey($environment) {
    // Get the api key from state service.
    return $this->state->get("izi_libizi:api_key_$environment");
  }

  /**
   *
   */
  private function getRequestHandlerClassByEnvironment($environment) {
    $request_handler_classes = [
      'test' => DevelopmentRequestHandler::class,
      'stage' => StagingRequestHandler::class,
      'prod' => ProductionRequestHandler::class,
    ];
    return $request_handler_classes[$environment];
  }

  /**
   *
   */
  public function getApiStatus() {
    $environment = $this->getCurrentEnvironment();
    // We need the request handler to get the api base url.
    $event_dispatcher = new EventDispatcher();
    $client = new HttpClient([
      'http_errors' => FALSE,
    ]);
    $request_handler_class = $this->getRequestHandlerClassByEnvironment($environment);
    $request_handler = new $request_handler_class($event_dispatcher, $client, NULL, NULL);
    $base_uri = $request_handler->getBaseUrl();
    $api_key = $this->getApiKey($environment);

    // Random resource just to check status.
    $resource_uri = 'languages/used';

    $response = $client->request('GET', $base_uri . $resource_uri, [
      'headers' => ['X-IZI-API-KEY' => $api_key],
    ]);

    return [
      'environment' => $this->getHumanReadableEnvironment($environment),
      'code' => $response->getStatusCode(),
      'message' => $response->getReasonPhrase(),
    ];
  }

}
