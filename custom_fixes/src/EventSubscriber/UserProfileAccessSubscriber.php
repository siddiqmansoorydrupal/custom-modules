<?php



// src/EventSubscriber/UserProfileAccessSubscriber.php



namespace Drupal\custom_fixes\EventSubscriber;



use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Symfony\Component\HttpKernel\Event\RequestEvent;

use Symfony\Component\HttpKernel\KernelEvents;

use Symfony\Component\HttpFoundation\RedirectResponse;

use Drupal\Core\Session\AccountProxyInterface;

use Drupal\Core\Url;



/**

 * Event subscriber to restrict user profile access.

 */

class UserProfileAccessSubscriber implements EventSubscriberInterface {



  protected $currentUser;



  public function __construct(AccountProxyInterface $current_user) {

    $this->currentUser = $current_user;

  }



  public function checkUserProfileAccess(RequestEvent $event) {

    $request = $event->getRequest();

    $path = $request->getPathInfo();

	

	$new_path=explode("/",$path);

	if(array_key_exists(1,$new_path) && $new_path[1]=='user' && array_key_exists(2,$new_path) && $new_path[2]=='password'){
		if (!$this->currentUser->isAnonymous()) {
			
			$url = Url::fromRoute('user.page');
			$response = new RedirectResponse($url->toString().'/'.$this->currentUser->id());
			$event->setResponse($response);
			$response->send();
		}
	}

	if(array_key_exists(1,$new_path) && $new_path[1]=='user' && array_key_exists(2,$new_path) && is_numeric($new_path[2]) && $new_path[2]!=0){

		$userId = $new_path[2];

		$this->currentUser->id();

		

		if ($this->currentUser->isAnonymous()) {

		  // Generate the URL for the user login page.
		  $url = Url::fromRoute('user.login');

		  // Set the destination query parameter to the desired destination.
		  $url->setOption('query', ['destination' => $destination]);

		  // Get the final URL string.
		  $login_url = $url->toString();

		  // Create a RedirectResponse with the login URL.
		  //$response = new RedirectResponse($login_url);

		  // Set the response to redirect to the login URL.
		  //$event->setResponse($response);


		}else{

			if (!$this->currentUser->hasPermission('administer users') && $this->currentUser->id() !== $userId) {

				// Redirect to access denied page.
				
				/*$url = Url::fromRoute('system.403');*/
				$url = Url::fromRoute('user.page');
				/*echo $url->toString();
				die;*/

				$response = new RedirectResponse($url->toString().'/'.$this->currentUser->id());
				$event->setResponse($response);
				$response->send();
				

			}

		}

	}

  }



  /**

   * {@inheritdoc}

   */

  public static function getSubscribedEvents() {

    $events[KernelEvents::REQUEST][] = ['checkUserProfileAccess'];

    return $events;

  }

}

