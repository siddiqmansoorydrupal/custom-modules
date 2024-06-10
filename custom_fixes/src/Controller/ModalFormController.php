<?php

namespace Drupal\custom_fixes\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * ModalFormExampleController class.
 */
class ModalFormController extends ControllerBase
{

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The ModalFormExampleController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder)
  {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Callback for opening the modal form.
   */
  public function openModalForm($id)
  {


    $response = new AjaxResponse();


    $parameters = ['product_id' => $id];

    // Get the modal form using the form builder.
    $modal_form = $this->formBuilder->getForm('Drupal\custom_fixes\Form\RequestUpdate', $parameters);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('My Modal Form', $modal_form, ['width' => '800']));

    return $response;
  }

  /**
   * Callback for opening the modal form.
   */
  public function openModalFormReplicate($id)
  {
    $response = new AjaxResponse();
    $parameters = ['product_id' => $id];

    // Get the modal form using the form builder.
    $modal_form = $this->formBuilder->getForm('Drupal\custom_fixes\Form\RequestUpdateReplicate', $parameters);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('Request Update Replicate', $modal_form, ['width' => '500']));

    return $response;
  }

  /**
   * Callback for opening the modal form.
   */
  public function downloadPdf()
  { 

    $url = Url::fromUserInput('/print/pdf/commerce_order/' . \Drupal::request()->query->get('file'))->toString();
    // Create a RedirectResponse to the current URL.
     
    // Send the response to perform the redirect.
    //return $response;
    // $response = new RedirectResponse($url);
    // $response->send(); 
    header("Refresh:1; url=$url");
    $build = [
      '#markup' => $this->t('<div class="download-container form-wrapper form-group" id="edit-container"><h3 style="text-align: center;">Your download will start in few seconds... <br> If not, click the button below.</h3>
      <div class="download-pdf-wrap"><a class="btn btn-lg btn-primary" href="' . $url . '" >Download</a></div>'),
    ];
    return $build;  

  
    



  } 


    /**
   * Callback for opening the modal form for Customersss.
   */
  public function NotifyCustomerModalForm($uid)
  {

    $response = new AjaxResponse();
    $parameters = ['uid' => $uid];
    // Get the modal form using the form builder.
    $modal_form = $this->formBuilder->getForm('Drupal\custom_fixes\Form\NotifyCustomer', $parameters);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('My Modal Form', $modal_form, ['width' => 'auto']));

    return $response;
  }

}