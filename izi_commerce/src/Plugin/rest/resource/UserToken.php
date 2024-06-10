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
use Drupal\user\Entity\User;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Credentials\Credentials;
use Aws\Handler\GuzzleV6\GuzzleHandler;

use GuzzleHttp\Client;
use Drupal\Core\Site\Settings;


/**
 * Get user credits resource by Access Token
 *
 * @RestResource(
 *   id = "user_generate_token",
 *   label = @Translation("User generate token"),
 *   uri_paths = {
 *     "create" = "user/generate-token",
 *   },
 * )
 */
class UserToken extends ResourceBase {
	
  /**
   * Return the credit points.
   */
  public function post(Request $request) {
	  
	$request_content = $request->getContent();  
	$request_body = Json::decode($request_content);
	 
	 
	if(!array_key_exists('mail',$request_body) || empty($request_body['mail']) ){
		$error_result = [
			'error' => "mail is required"
		];
		return new ResourceResponse($error_result, 403); 
	}
	
	if(!array_key_exists('password',$request_body) || empty($request_body['password']) ){
		$error_result = [
			'error' => "password is required"
		];
		return new ResourceResponse($error_result, 403); 
	}
	
	
	try {
		
		$username = $request_body['mail'];
		$password = $request_body['password'];
		$client = new CognitoIdentityProviderClient($this->settings = Settings::get('cognito'));

		$secretHash = $this->generateSecretHash($username, $this->settings['client_id'], $this->settings['SecretAccessKey']);
		
		$result = $client->adminInitiateAuth([
			'AuthFlow' => 'ADMIN_NO_SRP_AUTH',
			'AuthParameters' => [
				'USERNAME' => $username,
				'PASSWORD' => $password,
				'SECRET_HASH' => $secretHash,
			],
			'ClientId' => $this->settings['client_id'],
			'UserPoolId' => $this->settings['user_pool_id'],
		]);
		$accessToken = $result['AuthenticationResult']['AccessToken'];
		$response = new ResourceResponse(['accessToken'=>$accessToken], 200);		
	}
	catch (\Aws\Exception\AwsException $e) {		
		
		$error_result = [
			'error' => "An error occurred: ".$e->getAwsErrorCode(),
			/*'error_core' => "An error occurred: ".$e->getAwsErrorCode(),*/
			'HTTP_Status_Code' => "An error occurred: ".$e->getStatusCode(),
		];	
		return new ResourceResponse($error_result, 403); 
	}
	catch (\Exception $e) {
		
		$error_result = [
			'error' => "An error occurred: $e->getMessage()"
		];
		return new ResourceResponse($error_result, 403); 
    }
	return $response; 
		
		$error_result = [
			'error' => "No user found for"
		];
		return new ResourceResponse($error_result, 403); 
	}

	function generateSecretHash($username, $clientId, $clientSecret) {
		$message = $username . $clientId;
		$hashed = hash_hmac('sha256', $message, $clientSecret, true);
		$secretHash = base64_encode($hashed);
		return $secretHash;
	}
}