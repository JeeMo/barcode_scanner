<?php

namespace Drupal\barcode_scanner\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Class LookupForm.
 */
class LookupForm extends FormBase {

  public const VID = 'product_categories';

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
    // Load the current user.
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $uid= $user->get('uid')->value;

    //If found, display and ask whether to add or subtract 1 from quantity.
    $entities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'scanned_item',
      'field_barcode' => $barcode,
      'uid' => $uid,
      ]);
    if ($entities) {
      $entity = array_shift($entities);

      $url = \Drupal\Core\Url::fromRoute('entity.node.edit_form', ['node' => $entity->id()]);
      return $form_state->setRedirectUrl($url);
    } else {
      $str = "Not found in local system, checking API.";
      \Drupal::messenger()->addMessage($str);

      //If not found, check barcodelookup.com
      $data = $this->get_data($barcode);

      //If found, display API results and ask whether to add to inventory.
      $response = array();
      $response = json_decode($data);

      $product = $response->products[0];

      if ($response) {
        // Prep CATEGORIES
        $category = $this->save_categories($product->category);

        // Prep IMAGES
        $ext = $this->get_image_ext($product->images[0]);
        $data = file_get_contents($product->images[0]);
        $fileRepository = \Drupal::service('file.repository');
        $file = $fileRepository->writeData($data, 'public://'. $barcode . '.' . $ext, FileSystemInterface::EXISTS_REPLACE);

        // For now, automatically add it.
        $node = \Drupal::entityTypeManager()->getStorage('node')->create([
          'type' => 'scanned_item',
          'title' => $product->title,
          'field_barcode' => $product->barcode_number,
          'body' => $product->description,
          'field_size' => $product->size,
          'field_weight' => $product->weight,
          'field_length' => $product->length,
          'field_height' => $product->height,
          'field_width' => $product->width,
          'field_brand' => $product->brand,
          'field_category' => $category,
          'field_product_image' => [
            'target_id' => $file->id(),
            'alt'       => $product->description,
            'title'     => $product->brand,
          ],

        ]);

        if ($node->save()) {
          $url = \Drupal\Core\Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]);
        }
      } else {
        //If not found, redirect to 'add content of type item' form.
        $url = \Drupal\Core\Url::fromRoute('entity.node.add_form');
      }
    }
    return $form_state->setRedirectUrl($url);
  }

  /**
   * Return the filename extension from an image URL.
   *
   * @param $str
   * @return string|null
   */
  public function get_image_ext($str) {
    $tokens = explode("/", $str);
    $filename = array_pop($tokens);
    $tokens = explode(".", $filename);
    return array_pop($tokens);
  }

  /**
   * Break these out and set them up.
   *
   * @param $categories
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|\Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|Term|mixed|null
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save_categories($categories) {
    $tokens = explode(' > ', $categories);
    $parent = NULL;
    foreach ($tokens as $tag) {
      //Check if tag already exists
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $tag, 'vid' => static::VID]);
      $count = count($terms);
      if ($count > 1) {
        //Categories should be unique.
        $this->logger('barcode_scanner')->alert(t('Duplicate terms for: "%tag"', ['%tag' => $tag]));
        $current_taxonomy_term = array_shift($terms);
        //TODO: Check if the remaining terms have any products attached.
        //IF so, remove the remaining term from the product and replace with $current_taxonomy_term
        //Delete the duplicate terms.
      } else {
        if ($count == 0) {
          //If does not exist, Create new tag with correct previous tag as parent.
          $new_term = Term::create([
            'vid' => static::VID,
            'name' => $tag,
            'parent' => $parent ?? NULL,
          ]);

          $new_term->enforceIsNew();
          $new_term->save();
          $current_taxonomy_term = $new_term;
        } else {
          $current_taxonomy_term = array_shift($terms);
        }
      }
      //Save Parent for next term.
      $parent = $current_taxonomy_term;
    }
    // Return the final tag in the list to assign to the product.
    return $current_taxonomy_term;
  }


  /**
   * Use cURL to pull data from barcodelookup.com API.
   *
   * @param $barcode
   * @return bool|string
   */
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
