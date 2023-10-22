<?php
/*
Plugin Name: Wishlist Button
Description: Add a wishlist button next to the add to cart button.
Version: 1.0
Author: kasra sabet
*/
add_action('woocommerce_after_add_to_cart_form', 'add_wishlist_button');


function add_wishlist_button() {
    if (!is_user_logged_in()) {
        return;
    }
    $product_id = get_the_ID();
    $wishlist_state = get_wishlist_state();
    $button_text = in_array($product_id, $wishlist_state) ? 'In Wishlist' : 'Add to Wishlist';
    $current_user_id = get_current_user_id();

    // Generate a nonce
    $nonce = wp_create_nonce('wishlist_nonce');

    echo '<form method="post" action="" class="wishlist-form" id="wishlist-form">
              <input type="hidden" name="add_to_wishlist" value="true" />
              <input type="hidden" name="product_id" value="' . esc_attr($product_id) . '" />
              <input type="hidden" name="user_id" value="' . esc_attr($current_user_id) . '" />
              <input type="hidden" name="security" value="' . esc_attr($nonce) . '" />
              <button type="button" class="wishlist-button" id="wishlist-button">' . esc_html($button_text) . '</button>
          </form>';

}
function enqueue_styles() {
    wp_enqueue_style('wishlist-button-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
}

add_action('wp_enqueue_scripts', 'enqueue_styles');

add_action('init', 'process_wishlist_button');


add_action('wp_ajax_update_wishlist', 'update_wishlist_callback');
add_action('wp_ajax_nopriv_update_wishlist', 'update_wishlist_callback');

function update_wishlist_callback() {
    $nonce = $_POST['security'];
    if (!wp_verify_nonce($nonce, 'wishlist_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce.'));
    }

    // Check if the current user is allowed to perform the action
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'User not logged in.'));
    }

    $current_user_id = get_current_user_id();
    $requested_user_id = intval($_POST['user_id']);

    if ($current_user_id !== $requested_user_id) {
        wp_send_json_error(array('message' => 'Unauthorized user.'));
    }
    if (isset($_POST['product_id'])) {
        $product_id = intval($_POST['product_id']);
        $user_id = get_current_user_id();

        // Get current wishlist items
        $wishlist_items = get_user_meta($user_id, 'wishlist_items', true);
        $wishlist_items = explode(',', $wishlist_items);


        // Check if the product is already in the wishlist
        $index = array_search($product_id, $wishlist_items);

        if ($index !== false) {
            // Product is already in the wishlist, remove it
            unset($wishlist_items[$index]);
            echo 'Removed from Wishlist';
        } else {
            // Product is not in the wishlist, add it
            $wishlist_items[] = $product_id;
            echo 'Added to Wishlist';
        }

        // Update user metadata with the new wishlist items
        update_user_meta($user_id, 'wishlist_items', implode(',', $wishlist_items));

        wp_die();
    }
}



function process_wishlist_button() {
    if (isset($_POST['add_to_wishlist'])) {
        // Get the product ID and user ID
        $product_id = get_the_ID();
        $user_id = get_current_user_id();

        // Check if the product is already in the wishlist
        global $wpdb;
        $table_name = $wpdb->prefix . 'wishlist';

        $wishlist_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND product_id = %d", $user_id, $product_id));

        if (!$wishlist_entry) {
            // Add the product to the wishlist table
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'product_id' => $product_id,
                    'in_wishlist' => 1,
                ),
                array('%d', '%d', '%d')
            );

            // Output JavaScript to update the button
            echo '<script type="text/javascript">
                      updateWishlistButton(document.getElementById("wishlist-button"));
                  </script>';
        }

        // Redirect back to the product page
        wp_redirect(get_permalink($product_id));
        exit;
    }
}

function enqueue_scripts() {
    wp_enqueue_script('wishlist-button-script', plugin_dir_url(__FILE__) . '/assets/js/script.js', array(), null, true);
    wp_localize_script('wishlist-button-script', 'wishlist_ajax_object',
    array('ajaxurl' => admin_url('admin-ajax.php'))
);
}

add_action('wp_enqueue_scripts', 'enqueue_scripts');

function get_wishlist_state() {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return array();
    }

    // Get wishlist items from user meta
    $wishlist_state = get_user_meta($user_id, 'wishlist_items', true);
    $wishlist_items_array = explode(',', $wishlist_state);

    return is_array($wishlist_items_array) ? $wishlist_items_array : array();
}


function add_custom_account_endpoint($menu_links) {
    $menu_links['wishlist'] = 'whishlist';
    return $menu_links;
}

add_filter('woocommerce_account_menu_items', 'add_custom_account_endpoint');
function custom_list_content() {
    // Get current user ID
    $user_id = get_current_user_id();

    // Get wishlist items from user meta
    $wishlist_items = get_user_meta($user_id, 'wishlist_items', true);
    $wishlist_items_array = explode(',', $wishlist_items);
    echo '<h2>My Wishlist</h2>';

    if (!empty($wishlist_items)) {
        echo '<ul class="wishlist_menu">';
        foreach ($wishlist_items_array as $product_id) {
            $product = wc_get_product($product_id);

            if ($product) {
                echo '<li class="wishlist_items_menu">';
                echo '<a href="' . esc_url($product->get_permalink()) . '" class="wishlist_items">';
                
                // Get the product image ID
                $image_id = $product->get_image_id();
                
                // Display the product image
                echo wp_get_attachment_image($image_id, 'thumbnail', false, array('alt' => esc_attr($product->get_name())));
                
                echo '</a>';
                echo '<a href="' . esc_url($product->get_permalink()) . '" class="wishlist_items">'; 
                echo esc_html($product->get_name());
                echo '</a>';
                echo '</li>';
            }
        }
        echo '</ul>';
    } else {
        echo '<p>Your wishlist is empty.</p>';
    }
}



add_action('woocommerce_account_wishlist_endpoint', 'custom_list_content');
function add_custom_endpoint() {
    add_rewrite_endpoint('wishlist', EP_ROOT | EP_PAGES);
}
add_action('init', 'add_custom_endpoint');

// Step 4: Flush permalinks
function flush_rewrite_rules_on_activation() {
    add_custom_endpoint();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'flush_rewrite_rules_on_activation');


// commented creating the table as ostad said
// global $wpdb;
// $table_name = $wpdb->prefix . 'wishlist';

// $charset_collate = $wpdb->get_charset_collate();

// $sql = "CREATE TABLE $table_name (
//     id mediumint(9) NOT NULL AUTO_INCREMENT,
//     user_id mediumint(9) NOT NULL,
//     product_id mediumint(9) NOT NULL,
//     in_wishlist tinyint(1) NOT NULL,
//     PRIMARY KEY (id),
//     UNIQUE KEY user_product_unique (user_id, product_id)
// ) $charset_collate;";

// require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
// dbDelta($sql);
?>