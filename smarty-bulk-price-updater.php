<?php
/**
 * Plugin Name: SM - WooCommerce Bulk Update Prices
 * Plugin URI:  https://smartystudio.net/smarty-bulk-update-prices
 * Description: 
 * Version:     1.0.0
 * Author:      Smarty Studio | Martin Nestorov
 * Author URI:  https://smartystudio.net
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

function enqueue_select2_jquery() {
    wp_enqueue_style('select2css', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', false, null);
    wp_enqueue_script('select2', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
    wp_add_inline_script('select2', 'jQuery(document).ready(function($) { $(".select2").select2(); });');
}
add_action('admin_enqueue_scripts', 'enqueue_select2_jquery');

function price_increase_callback() {
    if ( isset( $_POST['price_increase'] ) && isset( $_POST['product_skus'] ) && isset( $_POST['product_categories'] ) ) {
        increase_product_price();
    }
    ?>
    <h2>Enter percentage amount to update selected product and variation prices:</h2>
    <form method="post">
        <label for="product_skus">Select Product SKUs:</label>
        <select class="select2" name="product_skus[]" multiple="multiple" style="width: 100%;">
            <?php
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1
            );
            $products = new WP_Query($args);
            if ($products->have_posts()) : 
                while ($products->have_posts()) : $products->the_post();
                    $product = wc_get_product(get_the_ID());
                    echo '<option value="' . $product->get_sku() . '">' . $product->get_sku() . '</option>';
                endwhile;
            endif;
            ?>
        </select>

        <label for="product_categories">Select Product Categories:</label>
        <select class="select2" name="product_categories[]" multiple="multiple" style="width: 100%;">
            <?php
            $categories = get_terms('product_cat');
            foreach ($categories as $category) {
                echo '<option value="' . $category->term_id . '">' . $category->name . '</option>';
            }
            ?>
        </select>

        <label for="price_increase">Price Increase (%):</label>
        <input type="number" name="price_increase" id="price_increase" value="10" min="0" max="100">
        <input type="submit" value="Update Prices">
    </form>
    <?php
}

// Create the new dashboard menu
function add_custom_menu() {
    add_menu_page(
        'Bulk Price Update',
        'Bulk Price Update',
        'manage_options',
        'update-product-price',
        'price_increase_callback'
    );
}

// Add custom menu to dashboard
add_action( 'admin_menu', 'add_custom_menu' );

// When form info is submitted, amount of increase is sent, and function is triggered.
function price_increase_callback() {
    if ( isset( $_POST['price_increase'] ) && isset( $_POST['product_skus'] ) && isset( $_POST['product_categories'] ) ) {
        increase_product_price();
    }
    ?>
    <h2>Enter percentage amount to update selected product and variation prices:</h2>
    <form method="post">
        <label for="product_skus">Select Product SKUs:</label>
        <select class="select2" name="product_skus[]" multiple="multiple" style="width: 100%;">
            <?php
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1
            );
            $products = new WP_Query($args);
            if ($products->have_posts()) : 
                while ($products->have_posts()) : $products->the_post();
                    $product = wc_get_product(get_the_ID());
                    echo '<option value="' . $product->get_sku() . '">' . $product->get_sku() . '</option>';
                endwhile;
            endif;
            ?>
        </select>

        <label for="product_categories">Select Product Categories:</label>
        <select class="select2" name="product_categories[]" multiple="multiple" style="width: 100%;">
            <?php
            $categories = get_terms('product_cat');
            foreach ($categories as $category) {
                echo '<option value="' . $category->term_id . '">' . $category->name . '</option>';
            }
            ?>
        </select>

        <label for="price_increase">Price Increase (%):</label>
        <input type="number" name="price_increase" id="price_increase" value="10" min="0" max="100">
        <input type="submit" value="Update Prices">
    </form>
    <?php
}


// Check for products and variations then loop through all and increase price by defined amount
function increase_product_price() {
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