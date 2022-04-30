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
    //Check for barcode in content.
    $barcode = $form_state->getValue('barcode');
    $str = "";
    //If found, display and ask whether to add or subtract 1 from quantity.
    $entities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'scanned_item',
      'field_barcode' => $barcode,
      ]);
    if ($entities) {
      // Found in our system.
      $str .= "Found in our system.  Add or Subtract?  Also offer option to edit item fields.";
    } else {
      $str .= "Not found in local system, checking API.";
      //If not found, check barcodelookup.com
      $data = $this->get_data($barcode);

      //If found, display API results and ask whether to add to inventory.
      $response = array();
      $response = json_decode($data);
      if ($response) {
        $str .= '<strong>Barcode Number:</strong> ' . $response->products[0]->barcode_number . '<br><br>';
        $str .=  '<strong>Title:</strong> ' . $response->products[0]->title . '<br><br>';
        $str .= '<strong>Entire Response:</strong><pre>';
        $str .= print_r($response->products, TRUE);
        $str .= '</pre>';

        // For now, automatically add it.
        $node = \Drupal::entityTypeManager()->getStorage('node')->create([
          'type' => 'scanned_item',
          'title' => $response->products[0]->title,
          'field_barcode' => $response->products[0]->barcode_number,
          'body' => $response->products[0]->description,
        ]);
        //$node->save();
        /* TODO: Don't save it yet, offer choices.*/
      } else {
        //If not found, redirect to 'add content of type item' form.
        $str .= "Nothing found by API";
      }
    }



    // Display result.
    /*
    foreach ($form_state->getValues() as $key => $value) {
      \Drupal::messenger()->addMessage($key . ': ' . ($key === 'text_format'?$value['value']:$value));
    }
*/


    \Drupal::messenger()->addMessage($str);

  }

  public function get_data($barcode) {

    $api_key = 'dioqrp8uxkc4scvz1n1isb4ixlp59c';
    $url = 'https://api.barcodelookup.com/v3/products?barcode=' . $barcode . '&formatted=y&key=' . $api_key;

    $ch = curl_init(); // Use only one cURL connection for multiple queries

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
  }
}
