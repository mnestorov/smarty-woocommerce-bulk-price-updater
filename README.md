# SM - WooCommerce Bulk Price Updater

[![Licence](https://img.shields.io/badge/LICENSE-GPL2.0+-blue)](./LICENSE)

- **Developed by:** Martin Nestorov 
    - Explore more at [nestorov.dev](https://github.com/mnestorov)
- **Plugin URI:** https://github.com/mnestorov/smarty-woocommerce-bulk-price-updater

## Overview

This plugin provides a robust solution for WooCommerce store administrators to bulk update product prices efficiently. It supports updating prices based on specific SKUs, product categories, or both, offering flexibility in managing product pricing strategies.

## Features

- **Bulk Price Increase**: Apply a percentage-based price increase across selected products.
- **Bulk Price Decrease**: Apply a percentage-based price decrease to reduce prices quickly.
- **Sale Price Adjustment**: Add or adjust sale prices by a specified percentage relative to the regular price.
- **Selective Price Update**: Update prices based on SKUs, categories, or a combination of both.
- **Sale Price Removal**: Clear sale prices from selected products to quickly end promotions.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/smarty-bulk-price-updater` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

### Price Update Logic

- **SKU ${\textsf{\color{lightgreen}(Optional)}}$**: If specified, prices are updated only for products with the selected SKUs. **_However, a category must also be selected; otherwise, no changes will be applied_**.
- **Categories ${\textsf{\color{red}(Required)}}$**: The selection of categories is mandatory. If no SKUs are selected, prices update for all products within the selected categories.
- **SKU and Categories**: If both are selected, **prices update only for products with the specified SKUs within the selected categories**. If the SKU is selected but no category is selected, even if a percentage change is specified, **the prices will not change due to the requirement of category selection**.

### Sale Price Calculation

- **Sale Price**: Calculated by applying the specified percentage decrease to the regular price. For example, a `10%` sale adjustment on a `$100` product sets the sale price at `$90`.

### Operational Flow

1. Select SKUs and/or categories.
2. Specify the percentage for price increase, decrease, or sale price adjustment.
3. Submit changes to apply updates across selected products.

## Requirements

- WordPress 4.7+ or higher.
- WooCommerce 5.1.0 or higher.
- PHP 7.2+

## TODO

- **Batch Processing**: Implement batch processing to handle large datasets without performance impacts.
- **Undo Last Change**: Add functionality to revert the last applied price change.
- **Advanced Filters**: Enhance selection options with more granular filters such as attributes or custom fields.

## Changelog

For a detailed list of changes and updates made to this project, please refer to our [Changelog](./CHANGELOG.md).

## Contributing

Contributions are welcome. Please follow the WordPress coding standards and submit pull requests for any enhancements.

---

## License

This project is released under the [GPL-2.0+ License](http://www.gnu.org/licenses/gpl-2.0.txt).
