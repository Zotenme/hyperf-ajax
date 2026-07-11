# Product catalog example

This example shows a Hyperf controller backed by Blade templates, two filters
and an AJAX “Show more” pagination flow.

Copy the files into the matching application namespaces/locations:

- `ProductCatalog.php` → `app/Catalog/ProductCatalog.php`;
- `ProductCatalogController.php` → `app/Controller/ProductCatalogController.php`;
- `CatalogPartialRenderer.php` → `app/Ajax/CatalogPartialRenderer.php`;
- `views/` → the configured Hyperf view directory;
- merge `dependencies.php` and `routes.php` into the corresponding application configs.

Install/configure `hyperf/view`, publish the Hyperf Ajax frontend assets, then
open `/catalog`.

The filter request replaces the current product page and pagination control.
“Show more” requests the same Blade product partial in append mode and replaces
only the pagination control with its next-page state.
