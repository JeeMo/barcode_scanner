<?php

namespace Drupal\barcode_scanner;

use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\Core\File\FileSystemInterface;

/**
 * Attempts to locate given barcode.
 *
 * First, it checks the local site:
 * - **If found locally**, Return the edit URL.
 * - **If not found**, Use BarcodeLookup.com (API) to search for the barcode.
 *   - **If found on API** Add it to local as a scanned_item entity.
 *     - Return edit URL to new entity.
 *   - **If not found** Return add URL for scanned_item for manual entry.
 */
class BarcodeFinder {

    /**
     * Taxonmy machine_name for product categories returned by API.
     */
    private const VID = 'product_categories';

    /**
     * @var $config
     */
    private $config;

    /**
     * @inheritdoc
     */
    public function __construct() {
        $this->config = \Drupal::service('config.factory')->getEditable('barcode_scanner.settings');
    }

    /**
     * Search logic for barcode's scanned_item.
     *
     * @param string $barcode
     *   Submitted barcode string from Lookup Form.
     * @param string $action
     *   Submitted action string from Lookup Form.
     *
     * @return bool|\Drupal\Core\Url|string
     *   The URL for the scanned_item entity.
     */
    public function get($barcode, $action) {
        // Check for barcode in content.
        if (!($url = $this->find_from_db($barcode, $action))) {
            if (!($url = $this->find_from_barcodelookup($barcode))) {
                $url = $this->create_new($barcode);
            }
        }
        return $url;
    }

    private function get_data($url, $ch) {

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    /**
     * Use cURL to pull data from barcodelookup.com API.
     *
     * If found, save data to a new scanned_item entity.
     * I was going to split this off into a Drupal Plugin,
     * but the API is so simple at this stage that I didn't bother.
     * I can always come back and do that if needed.
     *
     * @param string $barcode
     *   Submitted barcode.
     *
     * @return bool|\Drupal\Core\Url|string
     *   The URL for the scanned_item entity.
     */
    private function find_from_barcodelookup(string $barcode) {
        // Let the user know what is happening.
        $str = "Searching API.";
        \Drupal::messenger()->addMessage($str);

        // Load the API configuration.
        $api_key = $this->config->get('barcodelookup_api_key');
        $api_url = $this->config->get('barcodelookup_api_url');

        // Has the API config been completed?
        if ($api_key && $api_url) {

            // $api_key = 'ENTER YOUR API KEY HERE';
            // $url = 'https://api.barcodelookup.com/v3/products?barcode=' . $barcode . '&formatted=y&key=' . $api_key;
            $url = str_replace(['%api_key', '%barcode'], [$api_key, $barcode], $api_url);

            $ch = curl_init();

            $data = $this->get_data($url, $ch);

            // Notify user that the barcode doesn't exist locally.
            $str = "Not found in local system, checking API.";
            \Drupal::messenger()->addMessage($str);

            // Was the API reachable?
            if ($data) {
                // Decode the cURL response.
                $response = [];
                $response = json_decode($data);

                // Was the barcode located and the product returned?
                if (($response) && (isset($response->products[0]))) {
                    // Default to the first result.
                    $product = $response->products[0];

                    // Save any returned CATEGORIES to the static::VID taxonomy.
                    $category = $this->_save_categories($product->category);

                    // Prep and save product IMAGES
                    $ext = $this->_get_image_ext($product->images[0]);
                    $data = file_get_contents($product->images[0]);
                    $fileRepository = \Drupal::service('file.repository');
                    $file = $fileRepository->writeData($data, 'public://' . $barcode . '.' . $ext, FileSystemInterface::EXISTS_REPLACE);

                    // Setup the new scanned_item.
                    $node = \Drupal::entityTypeManager()->getStorage('node')->create(
                        [
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
                                'alt' => $product->description,
                                'title' => $product->brand,
                            ],

                        ]
                    );

                    // Save the new scanned_item.
                    if ($node->save()) {
                        // Return the edit URL for new scanned_item so the form can redirect to it.
                        $url = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]);
                    }
                }
                return $url;
            }
            // Communicate possible problem with the API to the user.
            $str = "No results from the API:  Is your subscription @ barcodelookup.com active?";
            \Drupal::messenger()->addMessage($str);
        }
        else {
            // Let the user know they need to set up the API.
            // TODO: Add a link to the Barcode Scanner Settings form
            $str = "API is not configured.";
            \Drupal::messenger()->addMessage($str);
        }
        return FALSE;
    }

    /**
     * Barcode does not exist so create a new one.
     *
     * @param string $barcode
     *   Submitted barcode.
     *
     * @return \Drupal\Core\Url
     *   Return add URL for scanned_item.
     */
    private function create_new(string $barcode) {
        // Let the user know what is happening.
        $str = "Existing product not found locally or via API: Let's create a new one!";
        \Drupal::messenger()->addMessage($str);

        // Build the add new scanned_item url.
        $url = Url::fromRoute('node.add', ['node_type' => 'scanned_item']);
        return $url;
    }

    /**
     * Check if the barcode exists locally first.
     *
     * @param string $barcode
     *   Submitted barcode.
     * @param string $action
     *   Submitted action.
     *
     * @return \Drupal\Core\Url|false
     *   Edit URL for scanned_item with this barcode or false.
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    private function find_from_db(string $barcode, string $action) {
        // Save selected action for next use.
        $this->config
            ->set('barcode_scanner_action', $action)
            ->save();

        // If you aren't over-communicating, you are under-communicating.
        $str = "Searching for barcode in local system.";
        \Drupal::messenger()->addMessage($str);

        // Load the current user.
        // TODO: Monetize by making usable by different users on the same system.
        $user = User::load(\Drupal::currentUser()->id());
        $uid = $user->get('uid')->value;

        // Seach locally for barcode.
        $entities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(
            [
                'type' => 'scanned_item',
                'field_barcode' => $barcode,
                'uid' => $uid,
            ]
        );

        // If found, display for editing and modifying inventory.
        if ($entities) {
            $str = "Barcode Located.";
            \Drupal::messenger()->addMessage($str);

            $entity = array_shift($entities);
            if ($action !== 'No Change') {
                // Add/Subtract from inventory.
                $qty = $entity->field_quantity_in_stock->value;
                $qty = ($action == 'Add') ? $qty + 1 : $qty - 1;
                $entity->field_quantity_in_stock->value = $qty;
                $entity->save();
                $str = "Quantity Updated.";
                \Drupal::messenger()->addMessage($str);

                $url = Url::fromRoute('barcode_scanner.lookup_form', []);
                return $url;
            }
            // Setup edit URL and return.
            $url = Url::fromRoute('entity.node.edit_form', ['node' => $entity->id()]);
            return $url;
        }
        return FALSE;
    }

    /**
     * Return the filename extension from an image URL.
     *
     * @param string $str
     *   Image filename from API.
     *
     * @return string|null
     */
    private function _get_image_ext(string $str): string {
        $tokens = explode("/", $str);
        $filename = array_pop($tokens);
        $tokens = explode(".", $filename);
        return array_pop($tokens);
    }

    /**
     * Break these out and save them to the taxonomy if unique.
     *
     * @param string $categories
     *   Product categories returned by API.
     *
     * @return \Drupal\Core\Entity\ContentEntityBase|\Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|Term|mixed|null
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    private function _save_categories(string $categories): mixed {
        $tokens = explode(' > ', $categories);
        $parent = NULL;
        foreach ($tokens as $tag) {
            // Check if tag already exists
            $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $tag, 'vid' => static::VID]);
            $count = count($terms);
            if ($count > 1) {
                // Categories should be unique.
                $this->logger('barcode_scanner')->alert(t('Duplicate terms for: "%tag"', ['%tag' => $tag]));
                $current_taxonomy_term = array_shift($terms);
                // TODO: Check if the remaining terms have any products attached.
                // IF so, remove the remaining term from the product and replace with $current_taxonomy_term
                // Delete the duplicate terms.
            }
            else {
                if ($count == 0) {
                    // If does not exist, Create new tag with correct previous tag as parent.
                    $new_term = Term::create(
                        [
                            'vid' => static::VID,
                            'name' => $tag,
                            'parent' => $parent ?? NULL,
                        ]
                    );

                    $new_term->enforceIsNew();
                    $new_term->save();
                    $current_taxonomy_term = $new_term;
                }
                else {
                    $current_taxonomy_term = array_shift($terms);
                }
            }
            // Save Parent for next term.
            $parent = $current_taxonomy_term;
        }
        // Return the final tag in the list to assign to the product.
        return $current_taxonomy_term;
    }

}
