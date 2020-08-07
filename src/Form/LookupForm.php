<?php

namespace Drupal\barcode_scanner\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class LookupForm.
 */
class LookupForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lookup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['barcode'] = [
      '#type' => 'number',
      '#title' => $this->t('Barcode'),
      '#description' => $this->t('Enter the barcode you wish to look up.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
      '#min' => 1,
      '#step' => 1,

    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#title' => $this->t('Look Up'),
      '#description' => $this->t('Submit the barcode to your barcode lookup service.'),
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Number field in form handles this.
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $barcode = $form_state->getValue('barcode');

    //Check for barcode in content
    //If found, display and ask whether to add or subtract 1 from quantity.
    //If not found, check barcodelookup.com
    //If found, display API results and ask whether to add to inventory.
    //If not found, redirect to 'add content of type item' form.

    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      \Drupal::messenger()->addMessage($key . ': ' . ($key === 'text_format'?$value['value']:$value));
    }
  }

}
