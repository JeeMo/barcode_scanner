<?php

namespace Drupal\barcode_scanner\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\barcode_scanner\BarcodeFinder;

/**
 * Barcode Scanner Lookup Form.
 */
class BarcodeScannerLookupForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return 'barcode_scanner_lookup_form';
  }

  /**
   * @inheritdoc
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
      '#description' => $this->t(
          'Submit the barcode to your barcode lookup service.'
      ),
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * @inheritdoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $finder = new BarcodeFinder();
    $url = $finder->get($form_state->getValue('barcode'));
    return $form_state->setRedirectUrl($url);
  }

}
