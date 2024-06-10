<?php

namespace Drupal\stripe_pay\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Configure stripe settings.
 */
class StripeConfig extends ConfigFormBase
{
    /**
     * Drupal\Core\Extension\ModuleHandler definition.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * The entity type bundle info.
     *
     * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
     */
    protected $entityTypeBundleInfo;

    /**
     * Constructs a SocialSharingSettingsForm object.
     *
     * @param \Drupal\Core\Extension\ModuleHandler              $module_handler
     *   The factory for configuration objects.
     * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
     *   The entity type bundle info.
     */
    public function __construct(ModuleHandler $module_handler, EntityTypeBundleInfoInterface $entity_type_bundle_info)
    {
        $this->moduleHandler = $module_handler;
        $this->entityTypeBundleInfo = $entity_type_bundle_info;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('module_handler'),
            $container->get('entity_type.bundle.info')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'stripe_pay_config';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
          'stripe_pay.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $stripe_pay_settings = $this->config('stripe_pay.settings');

        $form['stripe_configuration'] = [
          '#type' => 'details',
          '#title' => $this->t('Stripe Configuration'),
          '#open' => true,
        ];

        $form['stripe_configuration']['test_mode'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Test Mode'),
          '#default_value' => $stripe_pay_settings->get("test_mode"),
        ];

        $form['stripe_configuration']['currency_code'] = [
          '#type' => 'select',
          '#title' => $this->t('Currency code'),
          '#required' => true,
          '#options' => stripe_currency_codes(),
          '#default_value' => $stripe_pay_settings->get("currency_code")??'USD',
          '#description' => $this->t('Stripe currency code.'),
        ];

        $form['stripe_configuration']['test'] = [
          '#type' => 'details',
          '#title' => $this->t('Test Configuration'),
          '#open' => $stripe_pay_settings->get("test_mode"),
        ];

        $form['stripe_configuration']['test']['publishable_key_test'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Publishable key'),
          '#default_value' => $stripe_pay_settings->get("publishable_key_test"),
          '#description' => $this->t('Test publishable key'),
        ];

        $form['stripe_configuration']['test']['secret_key_test'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Secret key'),
          '#default_value' => $stripe_pay_settings->get("secret_key_test"),
          '#description' => $this->t('Test secret key'),
        ];

        $form['stripe_configuration']['live'] = [
          '#type' => 'details',
          '#title' => $this->t('Live Configuration'),
          '#open' => !$stripe_pay_settings->get("test_mode"),
        ];

        $form['stripe_configuration']['live']['publishable_key_live'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Publishable key'),
          '#default_value' => $stripe_pay_settings->get("publishable_key_live"),
          '#description' => $this->t('Live publishable key'),
        ];

        $form['stripe_configuration']['live']['secret_key_live'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Secret key'),
          '#default_value' => $stripe_pay_settings->get("secret_key_live"),
          '#description' => $this->t('Live secret key'),
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        \Drupal::messenger()->addMessage($this->t('Your configuration has been saved'));

        $config = $this->config('stripe_pay.settings');
        $currency_code = $form_state->getValue('currency_code');
        $test_mode = $form_state->getValue('test_mode');
        $publishable_key_test = $form_state->getValue('publishable_key_test');
        $secret_key_test = $form_state->getValue('secret_key_test');
        $publishable_key_live = $form_state->getValue('publishable_key_live');
        $secret_key_live = $form_state->getValue('secret_key_live');
        $config->set('currency_code', $currency_code);
        $config->set('test_mode', $test_mode);
        $config->set('publishable_key_test', $publishable_key_test);
        $config->set('secret_key_test', $secret_key_test);
        $config->set('publishable_key_live', $publishable_key_live);
        $config->set('secret_key_live', $secret_key_live);
        $config->save();
    }

}
