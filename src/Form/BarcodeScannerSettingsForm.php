<?php

namespace Drupal\barcode_scanner\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm.
 */
class BarcodeScannerSettingsForm extends ConfigFormBase {

  /**
   * @var $config
   */
  private $config;

  /**
   * @inheritdoc
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $this->config = $config_factory->getEditable('barcode_scanner.settings');
  }

  /**
   * @inheritdoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('config.factory')
      );
  }

  /**
   * @inheritdoc
   */
  protected function getEditableConfigNames() {
    return [
      'barcode_scanner.settings',
    ];
  }

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return 'barcode_scanner_settings';
  }

  /**
   * @inheritdoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['barcodelookup_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Barcode Lookup API Key'),
      '#description' => $this->t('This requires a paid subscription to barcodelookup.com.'),
      '#default_value' => $this->config->get('barcodelookup_api_key'),
    ];
    $form['barcodelookup_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Barcode Lookup API URL'),
      '#description' => $this->t('Please replace the api_key in the url with "%api_key" and replace the barcode value with "%barcode".'),
      '#default_value' => $this->config->get('barcodelookup_api_url'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * @inheritdoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $url = $form_state->getValue('barcodelookup_api_key');
    if ($url !== '') {
      if (substr_count($form_state->getValue('barcodelookup_api_url'), '%barcode') !== 1) {
        $form_state->setErrorByName('barcodelookup_api_url', $this->t("Token '%barcode' should be used once."));
      }
      if (substr_count($form_state->getValue('barcodelookup_api_url'), '%api_key') !== 1) {
        $form_state->setErrorByName('barcodelookup_api_url', $this->t("Token '%api_key' should be used once."));
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('barcode_scanner.settings')
      ->set('barcodelookup_api_key', $form_state->getValue('barcodelookup_api_key'))
      ->set('barcodelookup_api_url', $form_state->getValue('barcodelookup_api_url'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
