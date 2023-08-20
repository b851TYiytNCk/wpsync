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


function add_additonal_wc_mime_types($mime_types) {
    $mime_types['webp'] = 'image/webp';
    return $mime_types;
}
add_filter('woocommerce_rest_allowed_image_mime_types', 'add_additonal_wc_mime_types', 1, 1);

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
	
    // Woocommerce REST API client initialization
    require_once(__DIR__ . '/wpsw_woo_api.php'); 

    $products_ext = 'https://wp.webspark.dev/wp-api/products';
    $max_retries = 5; // count 20% failed requests   
    
    for ($retry = 1; $retry <= $max_retries; $retry++) {

		// setting timeout to 20 seconds for the request to be processed
        $response = wp_safe_remote_get($products_ext, array('timeout' => 20));

        if (is_wp_error($response)) {
        
			// log error
            error_log('Error retrieving JSON data: ' . $response->get_error_message());

            continue;
            // schedule the next retry
            // $next_retry = time() + (60 * $retry * $retry); // exponential backoff
            // wp_schedule_single_event($next_retry, 'wpsw_sync_event');
            // return;
        }

        // convert JSON and proceed to processing it
        $prod_data = json_decode(wp_remote_retrieve_body($response), true);
		$valid_message = isset($prod_data['message']) && $prod_data['message'] === 'OK';

        if ($valid_message) {
            // Update WooCommerce products based on $prod_data

            // If successful, break out of the loop

            $prod_data_body = $prod_data['data'] ?? array();

            if ( is_array($prod_data_body) && count($prod_data_body) ) {

                // $product_load = array(
                //     'create' => array(),
                //     'update' => array(),
                //     'delete' => array()
                // );

                foreach ($prod_data_body as $prod_dataitem) {
                    // Check if the product exists based on SKU
                    $existing_product = $woocommerce->get('products', ['sku' => $prod_dataitem['sku']]);
                
                    // Prepare product data
                    // $product = [
                    //     'sku' => $prod_dataitem['sku'],
                    //     'name' => $prod_dataitem['name'],
                    //     'description' => $prod_dataitem['description'],
                    //     'regular_price' => $prod_dataitem['price'],
                    //     'images' => [       
                    //         [
                    //             'src' => uploadImagetoMedia($prod_dataitem['picture']),
                    //         ],
                    //     ],
                    //     'stock_quantity' => $prod_dataitem['in_stock']
                    // ];
                    echo '<pre>';
                    var_dump( uploadImagetoMedia($prod_dataitem['picture']) );
                    echo '</pre>';
                    return;

                    // Create or update the product based on SKU presence
                    // if (empty($existing_product)) {
                        
                    //     // SKU not found, create a new product
                    //     $woocommerce->post('products', $product);
                    //     $action = 'created';

                    // } else {
                        
                    //     // SKU found, update the existing product
                    //     $product['id'] = $existing_product[0]->id;
                    //     $woocommerce->put('products/' . $product['id'], $product);
                    //     $action = 'updated';

                    // }
                
                    // echo 'Product ' . $action . ' with SKU: ' . $product['sku'] . '<br>';
                }

            } else {
                // data body is absent
                error_log('Error retrieving JSON data: data array is undefined');
                return;
            }

            break;
        }
    }
}
//add_action( 'wpsw_sync_event', 'wpsw_sync_worker' );

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function uploadImagetoMedia($src) {

    // $image_data = base64_encode(file_get_contents($src)); //get image content

    // $response = wp_remote_post(get_site_url() . '/wp-json/wp/v2/media', array(
    //     'headers'     => array(
    //         'Authorization' => 'Bearer mZSJxnrC2XIMX65esJ5VuYq7',
    //         'Content-Type' => 'application/json',
    //     ),
    //     'body'        => json_encode(array(
    //         'file' => $image_data
    //     )),
    // ));
    
    // if (is_wp_error($response)) {
    //     // if upload failed
    //     $error_message = $response->get_error_message();
    //     echo "Error uploading image: $error_message";
    // } else {
    //     $response_data = json_decode(wp_remote_retrieve_body($response), true);
    //     echo 'Image uploaded:', print_r($response_data, true);
    // }

    

        $res = wp_safe_remote_get($src)['http_response'];//media_sideload_image( $src, 0, '', 'id' );

        $reflFirst = new ReflectionClass($res);
        $reflRes = $reflFirst->getProperty('response');
        $reflRes->setAccessible(true);

        $reflInner = new ReflectionClass($reflRes->getValue($res));
        

    return $reflRes->getValue($res)->url;

}


/**
 * Deactivation 
 *  */

function wpsw_deactivate() {
    
}
register_deactivation_hook(
	__FILE__,
	'wpsw_deactivate'
);