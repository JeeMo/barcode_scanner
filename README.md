# Barcode Scanner

During the pandemic-related toilet paper shortages, I decided I wanted to keep a year's worth of household goods on hand.

Because I might be a bit OCD and I just happen to have a [90's Radio Shack :CueCat](https://en.wikipedia.org/wiki/CueCat) hanging around, I decided I needed an inventory system based on barcodes.

**Working:**
* Scan a barcode to add or subtract from my inventory.
  * You can also just type in the barcode number manually if you don't have a :CueCat.

**In Progress:**
* Specify my preferred stock count.

**Constantly Evolving:**
* Documentation.
* Functional PHPUnit test cases.

**TODO:**
* Generate shopping lists.
  * Provide links to favorite shopping sites.
  * Provide quantity for the shopping list
  * Show products that are below the stock count.

### Search Algorithm

Checks the local site for the submitted barcode:
 * **If found**, it loads that record and offers a choice to update or increase/decrease stock.
 * **If not found**, it uses cURL to reach out to a barcode API service @ [https://barcodelookup.com](https://barcodelookup.com).
   * **If found**, it should open a new content form and pre-load it with the data from the service and save.
   * **If not found**, it should open a new empty content form for the user to populate manually and save.

## Getting Started:

This is a Drupal module compatible with D8+.

Spin up a Drupal site: https://www.drupal.org/download
Lando is great for local dev: https://docs.lando.dev/getting-started/

Normally, I would recommend using Composer to download a module but this module is not published on Drupal or Packagist.  You could set up Composer to pull from my repo directly or just manually download it and extract to web/modules/.

* You will need to go to admin/modules.
* Filter for 'barcode'.
* Check the box and click 'Install'.
* If you have an API Key, return to the home page and click the 'Barcode Scanner API Settings' link in the main navigation.
  * Save your settings there.
* Return to the home page and click the 'Barcode Scanner' link in the main navigation.
* Enter a barcode and submit.

### Basic Testing
`lando test web/modules/barcode_scanner/`
