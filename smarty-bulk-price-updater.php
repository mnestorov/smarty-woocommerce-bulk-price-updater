<?php
/**
 * Plugin Name: SM - WooCommerce Bulk Price Updater
 * Plugin URI:  https://smartystudio.net/smarty-bulk-price-updater
 * Description: 
 * Version:     1.0.0
 * Author:      Smarty Studio | Martin Nestorov
 * Author URI:  https://smartystudio.net
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

if (!function_exists('smarty_enqueue_scripts')) {
    function smarty_enqueue_scripts($hook) {
        // Only add to the WooCommerce>Settings>Bulk Update page
        if ('woocommerce_page_smarty-bulk-price-updater' !== $hook) {
            return;
        }

        wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'));
        wp_add_inline_script('select2', 'jQuery(document).ready(function($) { $(".select2").select2(); });');
    }
    add_action('admin_enqueue_scripts', 'smarty_enqueue_scripts');
}

if (!function_exists('smarty_price_update_callback')) {
    function smarty_price_update_callback() { 
        if (isset($_POST['price_increase_submit'])) {
            error_log('Increase submitted');
            smarty_increase_product_price();
        }
        if (isset($_POST['price_decrease_submit'])) {
            error_log('Decrease submitted');
            smarty_decrease_product_price();
        } ?>
        <div class="wrap">
            <h1><?php echo __('Bulk Price Updater | Settings', 'smarty-bulk-price-updater'); ?></h1>
            <form method="post">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="product_skus"><?php echo __('Select Product SKUs (optional):', 'smarty-bulk-price-updater'); ?></label></th>
                            <td>
                                <select class="select2 smarty_select_products" name="product_skus[]" multiple="multiple" style="width: 100%;">
                                    <?php
                                    $args = array(
                                        'post_type' => 'product',
                                        'posts_per_page' => -1
                                    );
                                    $products = new WP_Query($args);
                                    if ($products->have_posts()) : 
                                        while ($products->have_posts()) : $products->the_post();
                                            $product = wc_get_product(get_the_ID());
                                            if ($product->get_sku()) {
                                                echo '<option value="' . esc_attr($product->get_sku()) . '">' . esc_html($product->get_sku()) . '</option>';
                                            }
                                        endwhile;
                                    endif; wp_reset_postdata();
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="product_categories"><?php echo __('Select Product Categories:', 'smarty-bulk-price-updater'); ?></label></th>
                            <td>
                                <select class="select2 smarty_select_products" name="product_categories[]" multiple="multiple" style="width: 100%;">
                                    <?php
                                    $categories = get_terms('product_cat', array('hide_empty' => false));
                                    if (!empty($categories) && !is_wp_error($categories)) {
                                        foreach ($categories as $category) {
                                            echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="price_increase"><?php echo __('Price Increase (%):', 'smarty-bulk-price-updater'); ?></label></th>
                            <td><input type="number" name="price_increase" id="price_increase" value="0" min="0" max="100"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="price_decrease"><?php echo __('Price Decrease (%):', 'smarty-bulk-price-updater'); ?></label></th>
                            <td><input type="number" name="price_decrease" id="price_decrease" value="0" min="0" max="100"></td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" name="price_increase_submit" class="button button-primary" value="<?php echo __('Increase Prices', 'smarty-bulk-price-updater'); ?>">
                    <input type="submit" name="price_decrease_submit" class="button button-secondary" value="<?php echo __('Decrease Prices', 'smarty-bulk-price-updater'); ?>">
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('.smarty_select_products').select2({
                    placeholder: "Select products where the bundleds will be displayed",
                    allowClear: true
                });
            });
        </script><?php
    }
}

if (!function_exists('smarty_add_custom_menu')) {
    /**
     * Create the new dashboard menu.
     */
    function smarty_add_custom_menu() {
        add_submenu_page(
            'woocommerce',                  // The slug of the main WooCommerce menu
            'Bulk Price Updater',           // The title of your custom page
            'Bulk Price Updater',           // The menu title
            'manage_options',               // Capability
            'smarty-bulk-price-updater',    // Menu slug
            'smarty_price_update_callback'  // Callback function
        );
    }
    add_action('admin_menu', 'smarty_add_custom_menu');
}

if (!function_exists('smarty_decrease_product_price')) {
    function smarty_decrease_product_price() {
        // Logging start of function
        error_log("Function called: smarty_decrease_product_price");

        $selected_skus = isset($_POST['product_skus']) ? $_POST['product_skus'] : array();
        $selected_categories = isset($_POST['product_categories']) ? $_POST['product_categories'] : array();
        $price_decrease = isset($_POST['price_decrease']) ? (float) $_POST['price_decrease'] : 0;

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(),
            'meta_query' => array()
        );

        // Add SKUs and categories to query if they are specified
        if (!empty($selected_skus)) {
            $args['meta_query'][] = array(
                'key' => '_sku',
                'value' => $selected_skus,
                'compare' => 'IN'
            );
        }

        if (!empty($selected_categories)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $selected_categories,
                'include_children' => false
            );
        }

        $products = new WP_Query($args);

        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product = wc_get_product(get_the_ID());

                if (!$product) continue;

                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $child_id) {
                        $variation = wc_get_product($child_id);
                        if (!$variation) continue;

                        $current_price = (float) $variation->get_price();
                        $new_price = $current_price * (1 - ($price_decrease / 100));

                        // Update the variation prices
                        update_post_meta($child_id, '_price', $new_price);
                        update_post_meta($child_id, '_regular_price', $new_price);
                        $variation->set_price($new_price);
                        $variation->set_regular_price($new_price);
                        $variation->set_sale_price(''); // Clear any sale price
                        if (!$variation->save()) {
                            error_log("Failed to save variation with ID " . $child_id);
                        }
                    }
                } else {
                    // This part handles simple products
                    $current_price = (float) $product->get_price();
                    $new_price = $current_price * (1 - ($price_decrease / 100));

                    update_post_meta(get_the_ID(), '_price', $new_price);
                    update_post_meta(get_the_ID(), '_regular_price', $new_price);
                    $product->set_price($new_price);
                    $product->set_regular_price($new_price);
                    $product->set_sale_price(''); // Clear any sale price
                    if (!$product->save()) {
                        error_log("Failed to save product with ID " . get_the_ID());
                    }

                    if (!$variation->save() || !$product->save()) {
                        set_transient('smarty_update_errors', 'Failed to update some products.', 45);
                    }
                }
            }

            // If everything went well:
            set_transient('smarty_admin_notice', 'Prices decreased successfully!', 45);
        }
    }
}

if (!function_exists('smarty_increase_product_price')) {
    /**
     * Check for products and variations then loop through all and 
     * increase price by defined amount.
     */
    function smarty_increase_product_price() {
        $selected_skus = isset($_POST['product_skus']) ? $_POST['product_skus'] : array();
        $selected_categories = isset($_POST['product_categories']) ? $_POST['product_categories'] : array();

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(),
            'meta_query' => array()
        );

        if (!empty($selected_skus)) {
            $args['meta_query'][] = array(
                'key' => '_sku',
                'value' => $selected_skus,
                'compare' => 'IN'
            );
        }

        if (!empty($selected_categories)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'id',
                'terms' => $selected_categories,
                'include_children' => false
            );
        }

        $products = new WP_Query($args);

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'offset' => 0,
        );
        $products = new WP_Query( $args );
        if ( $products->have_posts() ) {
            while ( $products->have_posts() ) {
                $products->the_post();
                $product = wc_get_product( get_the_ID() );
                if ( ! $product ) {
                    continue;
                }
                if ( $product->is_type( 'variable' ) ) {
                    foreach ( $product->get_children() as $child_id ) {
                        $variation = wc_get_product( $child_id );
                        if ( ! $variation ) {
                            continue;
                        }
                        $price = $variation->get_price();
                        if ( ! is_numeric( $price ) ) {
                            echo '<script>console.error("Price is not numeric for variation ID ' . esc_js( $child_id ) . '");</script>';
                            continue;
                        }
                        $new_price = $price * ( 1 + ( $_POST['price_increase'] / 100 ) );
                        update_post_meta( $child_id, '_price', $new_price );
                        $variation->set_regular_price( $new_price );
                        $variation->set_sale_price( $new_price );
                        $variation->set_price( $new_price );
                        if ( ! $variation->save() ) {
                            echo '<script>console.error("Error saving variation ID ' . esc_js( $child_id ) . ': ' . esc_js( wc_get_notices_error_messages() ) . '");</script>';
                        }
                    }
                } else {
                    $price = $product->get_price();
                    if ( ! is_numeric( $price ) ) {
                        echo '<script>console.error("Price is not numeric for product ID ' . esc_js( get_the_ID() ) . '");</script>';
                        continue;
                    }
                    $new_price = $price * ( 1 + ( $_POST['price_increase'] / 100 ) );
                    update_post_meta( get_the_ID(), '_price', $new_price );
                    $product->set_regular_price( $new_price );
                    $product->set_sale_price( $new_price );
                    $product->set_price( $new_price );
                    if ( ! $product->save() ) {
                        echo '<script>console.error("Error saving product ID ' . esc_js( get_the_ID() ) . ': ' . esc_js( wc_get_notices_error_messages() ) . '");</script>';
                    }
                }
            }
        }

        echo '<div class="notice notice-success is-dismissible"><p>Product prices have been updated successfully.</p></div>';
    }
}

if (!function_exists('smarty_admin_notices')) {
    function smarty_admin_notices() {
        if ($notice = get_transient('smarty_bulk_price_update_notice')) {
            echo "<div class='notice notice-success is-dismissible'><p>{$notice}</p></div>";
            delete_transient('smarty_bulk_price_update_notice');
        }
        if ($error = get_transient('smarty_bulk_price_update_errors')) {
            echo "<div class='notice notice-error is-dismissible'><p>{$error}</p></div>";
            delete_transient('smarty_bulk_price_update_errors');
        }
    }
    add_action('admin_notices', 'smarty_admin_notices');
}