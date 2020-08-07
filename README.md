# barcode_scanner

Drupal 8 module that takes a barcode and locates a data record for it.  

First it checks the local site.

 * **If found**, it loads that record and offers a choice to update or increase/decrease stock.
 * **If not found**, it uses a plugin to reach out to a barcode service such as https://barcodelookup.com.
   * **If found**, it should open a new content form and pre-load it with the data from the service and allow the user to review, edit and save.
   * **If not found**, it should open a new empty content form for the user to populate manually and save.