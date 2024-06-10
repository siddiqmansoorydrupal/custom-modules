<?php

namespace Drupal\izi_libizi;

use Drupal\izi_libizi\Exception\IziLibiziAccessDeniedException;
use Drupal\izi_libizi\Exception\IziLibiziGeneralException;
use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;
use Triquanta\IziTravel\Client\ClientException;
use Triquanta\IziTravel\Client\RequestHandlerInterface;

/**
 * Provides a request handler that will kill the request in case of errors.
 */
class ClientExceptionHandlingRequestHandler implements RequestHandlerInterface {

  /**
   * The wrapped request handler.
   *
   * @var \Triquanta\IziTravel\Client\RequestHandlerInterface
   */
  protected $requestHandler;

  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\IziTravel\Client\RequestHandlerInterface $request_handler
   *   The wrapped request handler.
   */
  public function __construct(RequestHandlerInterface $request_handler) {
    $this->requestHandler = $request_handler;
  }

  /**
   *
   */
  public function get($urlPath, array $parameters = []) {
    try {
      return $this->requestHandler->get($urlPath, $parameters);
    }
    catch (ClientException $e) {
      // Get the earliest exception.
      while ($e->getPrevious()) {
        $e = $e->getPrevious();
      }

      if ($e instanceof GuzzleClientException) {

        if ($e->getCode() == 403) {
          throw new IziLibiziAccessDeniedException();
        }

        // The izi.TRAVEL MTG API returns HTTP code 422 when the requested UUID
        // could not be found.
        if (in_array($e->getCode(), [404, 422])) {
          throw new IziLibiziNotFoundException();
        }
      }

      // Throw a general exception for all remaining cases.
      throw new IziLibiziGeneralException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   *
   */
  public function post($urlPath, array $parameters = [], $body) {
    try {
      return $this->requestHandler->post($urlPath, $parameters, $body);
    }
    catch (ClientException $e) {
      // Get the earliest exception.
      while ($e->getPrevious()) {
        $e = $e->getPrevious();
      }

      if ($e instanceof GuzzleClientException) {

        if ($e->getCode() == 403) {
          throw new IziLibiziAccessDeniedException();
        }

        // The izi.TRAVEL MTG API returns HTTP code 422 when the requested UUID
        // could not be found.
        if (in_array($e->getCode(), [404, 422])) {
          throw new IziLibiziNotFoundException();
        }
      }

      // Throw a general exception for all remaining cases.
      throw new IziLibiziGeneralException($e->getMessage(), $e->getCode(), $e);
    }
  }

}
