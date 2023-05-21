<?php

namespace Drupal\Tests\barcode_scanner\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Let's make sure that our system works by default.
 *
 * @group barcode_scanner
 */
class BarcodeScannerTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['barcode_scanner'];

  /**
   * Define the defaultTheme since 'classy' no longer exists.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A simple user.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * @inheritdoc
   */
  public function setUp(): void {
    parent::setUp();
    $this->user = $this->drupalCreateUser(
          [
            'administer nodes',
          ]
      );
  }

  /**
   * BarcodeFinder is the core functionality of this module.
   */
  public function testBarcodeFinder() {
    // TODO: This still needs to verify BarcodeFinder is dependable.
    $this->assertSame('true', 'true', 'Still needs to be built out.');
  }

  /**
   * Tests that the default config exists.
   */
  public function testDefaultConfigExist() {
    // Login.
    $this->drupalLogin($this->user);

    $expected_content_types = [
      'scanned_item',
      'inventory_item',
    ];
    foreach ($expected_content_types as $type) {
      // Generator test:
      $this->drupalGet('node/add/' . $type);
      $this->assertSession()->statusCodeEquals(200);
    }

    $expected_taxonomies = [
      'generic_settings',
      'product_categories',
      'stock_units',
    ];
    foreach ($expected_taxonomies as $taxonomy) {
      $this->drupalGet('admin/structure/taxonomy/manage/' . $taxonomy . '/overview');
      $this->assertSession()->statusCodeEquals(200);
    }
  }

  /**
   * Tests the API config form.
   */
  public function testConfigForm() {
    // Login.
    $this->drupalLogin($this->user);

    // Access config page.
    $this->drupalGet('admin/config/system/barcode_scanner');
    $this->assertSession()->statusCodeEquals(200);
    // Test the form elements exist and have defaults.
    $config = $this->config('barcode_scanner.settings');
    $this->assertSession()->fieldValueEquals(
          '',
          $config->get('barcodelookup_api_key'),
      );
    $this->assertSession()->fieldValueEquals(
          '',
          $config->get('barcodelookup_api_url'),
      );

    // Test form validation of %barcode.
    $this->drupalPostForm(
          NULL, [
            'barcodelookup_api_key' => 'Abcc1233',
            'barcodelookup_api_url' => 'https://api.barcodelookup.com/v3/products?barcode=&formatted=y&key=%api_key',
          ], t('Save configuration')
      );
    $this->assertSession()->pageTextContains("Token '%barcode' should be used once.");

    // Test form validation of %api_key.
    $this->drupalPostForm(
          NULL, [
            'barcodelookup_api_key' => 'Abcc1233',
            'barcodelookup_api_url' => 'https://api.barcodelookup.com/v3/products?barcode=%barcode&formatted=y&key=',
          ], t('Save configuration')
      );
    $this->assertSession()->pageTextContains("Token '%api_key' should be used once.");

    // Test form submission.
    $this->drupalPostForm(
          NULL, [
            'barcodelookup_api_key' => 'Abcc1233',
            'barcodelookup_api_url' => 'https://api.barcodelookup.com/v3/products?barcode=%barcode&formatted=y&key=%api_key',
          ], t('Save configuration')
      );
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Test the new values are there.
    $this->drupalGet('admin/config/system/barcode_scanner');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
          'barcodelookup_api_key',
          'Abcc1233',
      );
    $this->assertSession()->fieldValueEquals(
          'barcodelookup_api_url',
          'https://api.barcodelookup.com/v3/products?barcode=%barcode&formatted=y&key=%api_key',
      );

    // Can you unset them if you no longer want to use the API?
    $this->drupalPostForm(
      NULL, [
        'barcodelookup_api_key' => '',
        'barcodelookup_api_url' => '',
      ], t('Save configuration')
    );
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Test the new values are there.
    $this->drupalGet('admin/config/system/barcode_scanner');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'barcodelookup_api_key',
      '',
    );
    $this->assertSession()->fieldValueEquals(
      'barcodelookup_api_url',
      '',
    );
  }

}
