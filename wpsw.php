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

    function getSKUArr($arr) {
        return $arr['sku'];
    }

    function getSKUObj($obj) {
        return $obj->sku;
    }
    
    for ($retry = 1; $retry <= $max_retries; $retry++) {

		// setting timeout to 20 seconds for the request to be processed
        $response = wp_safe_remote_get($products_ext, array('timeout' => 20));

        if (is_wp_error($response)) {
			// log error
            error_log('Error retrieving JSON data: ' . $response->get_error_message());
            continue;
        }

        // convert JSON and proceed to processing it
        $prod_data = json_decode(wp_remote_retrieve_body($response), true);
		$valid_message = isset($prod_data['message']) && $prod_data['message'] === 'OK';

        if ($valid_message) {
            // Update WooCommerce products based on $prod_data

            // If successful, break out of the loop

            $prod_data_body = $prod_data['data'] ?? array();

            if ( is_array($prod_data_body) && count($prod_data_body) ) {

                if (count($prod_data_body) > 2000 ) {
                    error_log('Error retrieving JSON data: data overload');
                    exit;
                }

                $all_current = $woocommerce->get('products');

                $newSKU = array_diff(
                    array_map('getSKUArr', $prod_data_body),
                    array_map('getSKUObj', $all_current) 
                );

                $new_products = array_filter($prod_data_body, function($obj) use ($newSKU) {
                    return in_array($obj['sku'], $newSKU);
                });

                echo '<pre>';
                var_dump( $new_products );
                echo '</pre>';

                $total_new_p = count($new_products);

                // if ( $total_new_p ) {
                //     $batch_size = 5;

                //     for ($srt_index = 0; $srt_index < $total_new_p; $srt_index += $batch_size) {
                //         $batch_pr = array_slice($new_products, $srt_index, $batch_size);

                //         $new_pr_to_load = array();

                //         foreach ($batch_pr as $new_pr) {
                            
                //             $pr_data = [
                //                 'sku' => $new_pr['sku'],
                //                 'name' => $new_pr['name'],
                //                 'description' => $new_pr['description'],
                //                 'regular_price' => $new_pr['price'],
                //                 'images' => [       
                //                     [
                //                         'src' => upload_image_to_media($new_pr['picture']),
                //                     ],
                //                 ],
                //                 'stock_quantity' => $new_pr['in_stock']
                //             ];

                //             $new_pr_to_load["create"][] = $pr_data;
                //         }
                        

                //         if (count($new_pr_to_load)) {
                //             $pr_upload = $woocommerce->post('products/batch', $new_pr_to_load);
    
                //             if (is_wp_error($pr_upload)) {
                //                 error_log('WP Error: ' . $response->get_error_message());
                //                 break;
                //             } else {
                //                 var_dump( $pr_upload );
                //             }
                //         }

                //     }

                // }

                // echo '<pre>';
                // var_dump( $new_products );
                // echo '</pre>';
            

                // foreach ($prod_data_body as $prod_dataitem) {
                //     // Check if the product exists based on SKU
                //     $existing_product = $woocommerce->get('products', ['sku' => $prod_dataitem['sku']]);
                
                //     // Prepare product data
                //     $product = [
                //         'sku' => $prod_dataitem['sku'],
                //         'name' => $prod_dataitem['name'],
                //         'description' => $prod_dataitem['description'],
                //         'regular_price' => $prod_dataitem['price'],
                //         'images' => [       
                //             [
                //                 'src' => upload_image_to_media($prod_dataitem['picture']),
                //             ],
                //         ],
                //         'stock_quantity' => $prod_dataitem['in_stock']
                //     ];
                //     // echo '<pre>';
                //     // var_dump( upload_image_to_media($prod_dataitem['picture']) );
                //     // echo '</pre>';

                //     // Create or update the product based on SKU presence
                //     if (empty($existing_product)) {
                        
                //         // SKU not found, create a new product
                //         $woocommerce->post('products', $product);
                //         $action = 'created';

                //     } else {
                        
                //         // SKU found, update the existing product
                //         $product['id'] = $existing_product[0]->id;
                //         $woocommerce->put('products/' . $product['id'], $product);
                //         $action = 'updated';

                //     }
                
                //     echo 'Product ' . $action . ' with SKU: ' . $product['sku'] . '<br>';
                // }

            } else {
                // data body is absent
                error_log('Error retrieving JSON data: data is incorrect');
                exit;
            }

            break;
        }
    }
}
//add_action( 'wpsw_sync_event', 'wpsw_sync_worker' );

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function upload_image_to_media($src) {

    // get protected response obj with proper url of destination source
    $res = wp_safe_remote_get($src)['http_response'];

    // Create a reflection class to set response property accessible
    $reflFirst = new ReflectionClass($res);
    $reflRes = $reflFirst->getProperty('response');
    $reflRes->setAccessible(true);

    // Create another reflection to get 'url' property inside 'response' property
    $reflInner = new ReflectionClass($reflRes->getValue($res));
    
    $url_to_download = $reflRes->getValue($res)->url;
    $image_data = media_sideload_image( $url_to_download, 0, '', 'id' ); //get image content

    return $image_data;

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