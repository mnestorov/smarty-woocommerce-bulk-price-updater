<?php
/**
 * Plugin Name: SM - WooCommerce Bulk Price Updater
 * Plugin URI:  https://github.com/mnestorov/smarty-woocommerce-bulk-price-updater
 * Description: A robust solution for WooCommerce store administrators to bulk update product prices efficiently.
 * Version:     1.0.0
 * Author:      Smarty Studio | Martin Nestorov
 * Author URI:  https://github.com/mnestorov
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 3.5.0
 * WC tested up to: 9.0.2
 * Requires Plugins: woocommerce
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
    }
    add_action('admin_enqueue_scripts', 'smarty_enqueue_scripts');
}

if (!function_exists('smarty_add_custom_menu')) {
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

if (!function_exists('smarty_price_update_callback')) {
    function smarty_price_update_callback() {
        error_log('Form submitted, checking which submit button was pressed');
        error_log('Form data: ' . print_r($_POST, true));

        if (isset($_POST['smarty_bpu_submit'])) {
            error_log('Update submitted');
    
            // Save form values
            smarty_save_form_values();
    
            if (!empty($_POST['smarty_bpu_price_increase']) && floatval($_POST['smarty_bpu_price_increase']) != 0) {
                smarty_increase_product_price();
            }
    
            if (!empty($_POST['smarty_bpu_price_decrease']) && floatval($_POST['smarty_bpu_price_decrease']) != 0) {
                smarty_decrease_product_price();
            }
    
            // Handle sale price percentage adjustment independently
            if (isset($_POST['smarty_bpu_sale_price_percentage']) && $_POST['smarty_bpu_sale_price_percentage'] != 0) {
                smarty_apply_sale_price_percentage();
            }
        } ?>
        <div class="wrap">
            <h1><?php echo __('Bulk Price Updater | Settings', 'smarty-bulk-price-updater'); ?></h1>
            <form method="post">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="smarty_bpu_product_skus"><?php echo __('Select Product SKUs (optional):', 'smarty-bulk-price-updater'); ?></label></th>
                            <td>
                                <select class="select2 smarty_select_products" name="smarty_bpu_product_skus[]" multiple="multiple" style="width: 100%;">
                                    <?php
                                        $selected_skus = get_option('smarty_bpu_product_skus', array());
                                        $skus_rendered = array();
                                        $args = array(
                                            'post_type' => array('product', 'product_variation'),
                                            'posts_per_page' => -1
                                        );
                                        $products = new WP_Query($args);
                                        if ($products->have_posts()) {
                                            while ($products->have_posts()) : $products->the_post();
                                                $product = wc_get_product(get_the_ID());
                                                if ($product->is_type('variable')) {
                                                    foreach ($product->get_children() as $child_id) {
                                                        $variation = wc_get_product($child_id);
                                                        if ($variation->get_sku() && !in_array($variation->get_sku(), $skus_rendered)) {
                                                            $is_selected = in_array($variation->get_sku(), $selected_skus) ? 'selected' : '';
                                                            echo '<option value="' . esc_attr($variation->get_sku()) . '" ' . $is_selected . '>' . esc_html($variation->get_sku()) . '</option>';
                                                            $skus_rendered[] = $variation->get_sku();
                                                        }
                                                    }
                                                } else {
                                                    if ($product->get_sku() && !in_array($product->get_sku(), $skus_rendered)) {
                                                        $is_selected = in_array($product->get_sku(), $selected_skus) ? 'selected' : '';
                                                        echo '<option value="' . esc_attr($product->get_sku()) . '" ' . $is_selected . '>' . esc_html($product->get_sku()) . '</option>';
                                                        $skus_rendered[] = $product->get_sku();
                                                    }
                                                }
                                            endwhile;
                                        }
                                        wp_reset_postdata();
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smarty_bpu_product_categories"><?php echo __('Select Product Categories:', 'smarty-bulk-price-updater'); ?></label></th>
                            <td>
                                <select class="select2 smarty_select_products" name="smarty_bpu_product_categories[]" multiple="multiple" style="width: 100%;">
                                    <?php
                                    $selected_categories = get_option('smarty_bpu_product_categories', array());
                                    $categories = get_terms('product_cat', array('hide_empty' => false));
                                    if (!empty($categories) && !is_wp_error($categories)) {
                                        foreach ($categories as $category) {
                                            $is_selected = in_array($category->term_id, $selected_categories) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($category->term_id) . '" ' . $is_selected . '>' . esc_html($category->name) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smarty_bpu_price_increase"><?php echo __('Price Increase (%):', 'smarty-bulk-price-updater'); ?></label></th>
                            <td><input type="number" name="smarty_bpu_price_increase" id="smarty_bpu_price_increase" value="<?php echo esc_attr(get_option('smarty_bpu_price_increase', '0')); ?>" min="0" max="70" style="width: 100px;"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smarty_bpu_price_decrease"><?php echo __('Price Decrease (%):', 'smarty-bulk-price-updater'); ?></label></th>
                            <td><input type="number" name="smarty_bpu_price_decrease" id="smarty_bpu_price_decrease" value="<?php echo esc_attr(get_option('smarty_bpu_price_decrease', '0')); ?>" min="0" max="70" style="width: 100px;"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smarty_bpu_sale_price_percentage"><?php echo __('Sale Price Percentage Adjustment (%):', 'smarty-bulk-price-updater'); ?></label></th>
                            <td><input type="number" name="smarty_bpu_sale_price_percentage" id="smarty_bpu_sale_price_percentage" value="<?php echo esc_attr(get_option('smarty_bpu_sale_price_percentage', '0')); ?>" min="-99" max="99" style="width: 100px;"></td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" name="smarty_bpu_submit" class="button button-primary" value="<?php echo __('Save Changes', 'smarty-bulk-price-updater'); ?>">
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

if (!function_exists('smarty_increase_product_price')) {
    function smarty_increase_product_price() {
        error_log("Function called: smarty_increase_product_price");
        $selected_skus = get_option('smarty_bpu_product_skus', array());
        $selected_categories = get_option('smarty_bpu_product_categories', array());
        $price_increase = floatval(get_option('smarty_bpu_price_increase', '0'));

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $selected_categories,
                    'include_children' => false
                )
            )
        );

        $products = new WP_Query($args);
        $update_error_occurred = false;
        error_log('Number of Products Found: ' . $products->found_posts);

        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
    
                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $child_id) {
                        $child_product = wc_get_product($child_id);
                        $sku = $child_product->get_sku();
                        if (in_array($sku, $selected_skus)) {
                            $regular_price = floatval(get_post_meta($child_id, '_regular_price', true));
                            $new_price = $regular_price * (1 + ($price_increase / 100));
                            update_post_meta($child_id, '_regular_price', $new_price);
                            update_post_meta($child_id, '_sale_price', '');
                            $child_product->set_regular_price($new_price);
                            $child_product->set_sale_price('');
                            $child_product->save();
                            error_log("Updated Variation ID: $child_id | New Price: $new_price");
                        }
                    }
                } else {
                    $sku = $product->get_sku();
                    if (in_array($sku, $selected_skus)) {
                        $regular_price = floatval(get_post_meta($product_id, '_regular_price', true));
                        $new_price = $regular_price * (1 + ($price_increase / 100));
                        update_post_meta($product_id, '_regular_price', $new_price);
                        update_post_meta($product_id, '_sale_price', '');
                        $product->set_regular_price($new_price);
                        $product->set_sale_price('');
                        $product->save();
                        error_log("Updated Product ID: $product_id | New Price: $new_price");
                    }
                }
            }
        } else {
            error_log('No products found matching the criteria');
        }

        if ($update_error_occurred) {
            set_transient('smarty_bulk_price_update_errors', 'Some products could not be updated.', 45);
            echo '<div class="notice notice-error is-dismissible"><p>Some products could not be updated.</p></div>';
        } else {
            set_transient('smarty_bulk_price_update_notice', 'Product prices have been updated successfully!', 45);
            echo '<div class="notice notice-success is-dismissible"><p>Product prices have been updated successfully.</p></div>';
        }
    }
}

if (!function_exists('smarty_decrease_product_price')) {
    function smarty_decrease_product_price() {
        error_log("Function called: smarty_decrease_product_price");
        $selected_skus = get_option('smarty_bpu_product_skus', array());
        $selected_categories = get_option('smarty_bpu_product_categories', array());
        $price_decrease = floatval(get_option('smarty_bpu_price_decrease', '0'));
    
        $args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $selected_categories,
                    'include_children' => false
                )
            )
        );
    
        $products = new WP_Query($args);
        $update_error_occurred = false;
        error_log('Number of Products Found: ' . $products->found_posts);
    
        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
    
                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $child_id) {
                        $child_product = wc_get_product($child_id);
                        $sku = $child_product->get_sku();
                        if (in_array($sku, $selected_skus)) {
                            $regular_price = floatval(get_post_meta($child_id, '_regular_price', true));
                            $new_price = $regular_price * (1 - ($price_decrease / 100));
                            update_post_meta($child_id, '_regular_price', $new_price);
                            update_post_meta($child_id, '_sale_price', '');
                            $child_product->set_regular_price($new_price);
                            $child_product->set_sale_price('');
                            $child_product->save();
                            error_log("Updated Variation ID: $child_id | New Price: $new_price");
                        }
                    }
                } else {
                    $sku = $product->get_sku();
                    if (in_array($sku, $selected_skus)) {
                        $regular_price = floatval(get_post_meta($product_id, '_regular_price', true));
                        $new_price = $regular_price * (1 - ($price_decrease / 100));
                        update_post_meta($product_id, '_regular_price', $new_price);
                        update_post_meta($product_id, '_sale_price', '');
                        $product->set_regular_price($new_price);
                        $product->set_sale_price('');
                        $product->save();
                        error_log("Updated Product ID: $product_id | New Price: $new_price");
                    }
                }
            }
        } else {
            error_log('No products found matching the criteria');
        }
    
        if ($update_error_occurred) {
            set_transient('smarty_bulk_price_update_errors', 'Some products could not be updated.', 45);
            echo '<div class="notice notice-error is-dismissible"><p>Some products could not be updated.</p></div>';
        } else {
            set_transient('smarty_bulk_price_update_notice', 'Prices decreased successfully!', 45);
            echo '<div class="notice notice-success is-dismissible"><p>Prices decreased successfully.</p></div>';
        }
    }
}

if (!function_exists('smarty_apply_sale_price_percentage')) {
    function smarty_apply_sale_price_percentage() {
        error_log('Starting sale price adjustment');
        $selected_skus = get_option('smarty_bpu_product_skus', array());
        $selected_categories = get_option('smarty_bpu_product_categories', array());
        $sale_price_percentage = floatval(get_option('smarty_bpu_sale_price_percentage', '0'));
    
        error_log('Selected SKUs: ' . implode(', ', $selected_skus));
        error_log('Selected Categories: ' . implode(', ', $selected_categories));
        error_log('Sale Price Percentage: ' . $sale_price_percentage);

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $selected_categories,
                    'include_children' => false
                )
            )
        );

        $products = new WP_Query($args);
        $update_error_occurred = false;

        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
    
                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $child_id) {
                        $child_product = wc_get_product($child_id);
                        $sku = $child_product->get_sku();
                        if (in_array($sku, $selected_skus)) {
                            $regular_price = floatval(get_post_meta($child_id, '_regular_price', true));
                            $new_sale_price = $regular_price * (1 - ($sale_price_percentage / 100));
                            update_post_meta($child_id, '_sale_price', $new_sale_price);
                            $child_product->set_sale_price($new_sale_price);
                            $child_product->save();
                            error_log("Updated Variation ID: $child_id | New Sale Price: $new_sale_price");
                        }
                    }
                } else {
                    $sku = $product->get_sku();
                    if (in_array($sku, $selected_skus)) {
                        $regular_price = floatval(get_post_meta($product_id, '_regular_price', true));
                        $new_sale_price = $regular_price * (1 - ($sale_price_percentage / 100));
                        update_post_meta($product_id, '_sale_price', $new_sale_price);
                        $product->set_sale_price($new_sale_price);
                        $product->save();
                        error_log("Updated Product ID: $product_id | New Sale Price: $new_sale_price");
                    }
                }
            }
        } else {
            error_log('No products found matching the criteria');
        }

        if ($update_error_occurred) {
            set_transient('smarty_bulk_price_update_errors', 'Some products could not be updated.', 45);
            echo '<div class="notice notice-error is-dismissible"><p>Some products could not be updated.</p></div>';
        } else {
            set_transient('smarty_bulk_price_update_notice', 'Sale prices adjusted successfully!', 45);
            echo '<div class="notice notice-success is-dismissible"><p>Sale prices adjusted successfully.</p></div>';
        }
    }
}

if (!function_exists('smarty_save_form_values')) {
    function smarty_save_form_values() {
        update_option('smarty_bpu_product_skus', isset($_POST['smarty_bpu_product_skus']) ? $_POST['smarty_bpu_product_skus'] : array());
        update_option('smarty_bpu_product_categories', isset($_POST['smarty_bpu_product_categories']) ? $_POST['smarty_bpu_product_categories'] : array());
        update_option('smarty_bpu_price_increase', isset($_POST['smarty_bpu_price_increase']) ? $_POST['smarty_bpu_price_increase'] : '0');
        update_option('smarty_bpu_price_decrease', isset($_POST['smarty_bpu_price_decrease']) ? $_POST['smarty_bpu_price_decrease'] : '0');
        update_option('smarty_bpu_sale_price_percentage', isset($_POST['smarty_bpu_sale_price_percentage']) ? $_POST['smarty_bpu_sale_price_percentage'] : '0');

        // Clear transients
        delete_transient('smarty_bulk_price_update_notice');
        delete_transient('smarty_bulk_price_update_errors');
    }
}

if (!function_exists('smarty_get_form_values')) {
    function smarty_get_form_values() {
        return array(
            'product_skus' => get_option('smarty_bpu_product_skus', array()),
            'product_categories' => get_option('smarty_bpu_product_categories', array()),
            'price_increase' => get_option('smarty_bpu_price_increase', '0'),
            'price_decrease' => get_option('smarty_bpu_price_decrease', '0'),
            'sale_price_percentage' => get_option('smarty_bpu_sale_price_percentage', '0'),
        );
    }
}

function smarty_admin_notices() {
    if ($notice = get_transient('smarty_bulk_price_update_notice')) {
        echo "<div class='notice notice-success is-dismissible'><p>{$notice}</p></div>";
        delete_transient('smarty_bulk_price_update_notice');
        error_log('Displaying success notice and deleting transient.');
    }
    if ($error = get_transient('smarty_bulk_price_update_errors')) {
        echo "<div class='notice notice-error is-dismissible'><p>{$error}</p></div>";
        delete_transient('smarty_bulk_price_update_errors');
        error_log('Displaying error notice and deleting transient.');
    }
}
add_action('admin_notices', 'smarty_admin_notices');
