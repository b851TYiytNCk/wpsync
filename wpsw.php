<?php
/**
 * Plugin Name: wpsync-webspark
 * Plugin URI: http://highpassion.dev/wp-plugins/woo-prod-sync/
 * Description: Advanced WooCommerce Add-on for product synchronization.
 * Version: 1.0.0
 * Author: Vladyslav Nahornyi
 * Author URI: https://highpassion.dev/
 * Requires PHP: 7.3
 *
 * @package WPSync-Webspark
 */

defined( 'ABSPATH' ) || exit;


/**
 * Setting up a WP Cron 
 *  */

// Schedule syncronization event if someone visits the site
function wpsw_sync_setup() {
	if ( ! wp_next_scheduled( 'wpsw_sync_event' ) ) {
		// default 'hourly' set to repeat event every hour
		wp_schedule_event( time(), 'hourly', 'wpsw_sync_event' );
	}
}
add_action( 'wp', 'wpsw_sync_worker' );

function wpsw_sync_worker() {
	
    $products_ext = 'https://wp.webspark.dev/wp-api/products';
    $max_retries = 5; // count 20% failed requests

	// Woocommerce REST API client initialization
	require_once(__DIR__ . '/wpsw_woo_api.php');    
    
    for ($retry = 1; $retry <= $max_retries; $retry++) {

		// setting timeout to 20 seconds for the request to be processed
        $response = wp_safe_remote_get($products_ext);//, array('timeout' => 20));

        if (is_wp_error($response)) {
        
			// log error
            error_log('Error retrieving JSON data: ' . $response->get_error_message());

            // schedule the next retry
            $next_retry = time() + (60 * $retry * $retry); // exponential backoff
            wp_schedule_single_event($next_retry, 'wpsw_sync_event');

            return;
        }

        // convert JSON and proceed to processing it
        $prod_data = json_decode(wp_remote_retrieve_body($response), true);
		$valid_message = isset($data_json['message']) && $data_json['message'] === 'OK';

        if ($valid_message) {
            // Update WooCommerce products based on $prod_data

            // If successful, break out of the loop

            $prod_data_body = $prod_data['data'] ?? false;

            if ($prod_data_body) {

                foreach ($prod_data_body as $prod_dataitem) {
                    // Check if the product exists based on SKU
                    $existing_product = $woocommerce->get('products', ['sku' => $prod_dataitem['sku']]);
                
                    // Prepare product data
                    $product = [
                        'sku' => $prod_dataitem['sku'],
                        'name' => $prod_dataitem['name'],
                        'description' => $prod_dataitem['description'],
                        'regular_price' => $prod_dataitem['price'],
                        'images' => [
                            [
                                'src' => $prod_dataitem['picture'],
                            ],
                        ],
                        'stock_quantity' => $prod_dataitem['in_stock']
                    ];
                
                    // Create or update the product based on SKU presence
                    if (empty($existing_product)) {
                        
                        // SKU not found, create a new product
                        $woocommerce->post('products', $product);
                        $action = 'created';

                    } else {
                        
                        // SKU found, update the existing product
                        $product['id'] = $existing_product[0]->id;
                        $woocommerce->put('products/' . $product['id'], $product);
                        $action = 'updated';

                    }
                
                    echo 'Product ' . $action . ' with SKU: ' . $product['sku'] . '<br>';
                }

                // echo '</pre>';
            } else {
                // data body is absent
                error_log('Error retrieving JSON data: data array is undefined');
                return;
            }

			

            break;
        }
    }
}
add_action( 'wpsw_sync_event', 'wpsw_sync_worker' );


/**
 * Deactivation 
 *  */

function wpsw_deactivate() {
    
}
register_deactivation_hook(
	__FILE__,
	'wpsw_deactivate'
);