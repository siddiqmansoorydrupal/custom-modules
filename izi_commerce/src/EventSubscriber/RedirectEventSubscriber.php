<?php

namespace Drupal\izi_commerce\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Subscribes to the Kernel Request event and redirects to the use login page
 * when the user is not logged in and try to access cart page and tour credit page.
 */
class RedirectEventSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;


  /**
   * RedirectEventSubscriber constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   */
  public function __construct(AccountProxyInterface $currentUser) {
    $this->currentUser = $currentUser;
  }

  /**
   * Redirect user to user login page if user try to access the
   * tour credit page and cart.
   */
  public function onResponseRedirect(ResponseEvent $event) {
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    if($this->currentUser->id() == 0) {
      if ($path == '/tour-credits' || $path == '/cart') {
        $redirect_url = Url::fromRoute('user.login')->toString();
        if (isset($redirect_url)) {
          $event->setResponse(new RedirectResponse($redirect_url));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents():array {
    return [
      KernelEvents::RESPONSE => ['onResponseRedirect', -10],
      //KernelEvents::REQUEST => ['onRequest', -10],
    ];
  }


  /**
   * On the request.
   */
  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();
  }

}