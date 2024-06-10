<?php

namespace Drupal\izi_ads\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * IZI Ads Exclusion form.
 *
 * @property \Drupal\izi_ads\IziAdsExclusionInterface $entity
 */
class IziAdsExclusionForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the izi ads exclusion.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\izi_ads\Entity\IziAdsExclusion::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->getPath(),
      '#description' => $this->t('Path to be excluded. It should be the aliased path with no domain eg: <em>/en/path-to-object</em>'),
      '#required' => TRUE,
    ];

    $form['expires'] = [
      '#type' => 'date',
      '#title' => $this->t('Expires'),
      '#date_date_format' => 'd-m-Y',
      '#default_value' => $this->entity->getExpires(),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new izi ads exclusion %label.', $message_args)
      : $this->t('Updated izi ads exclusion %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
