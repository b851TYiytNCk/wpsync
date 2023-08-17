<?php

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;

$woocommerce = new Client(
    get_site_url(), // Your store URL
    'ck_58384434b7640f64e659110585ea83139573212a', // Your consumer key
    'cs_17d276d58c6612071050d4de5888335e9cd0ccca', // Your consumer secret
    [
        'wp_api' => true, // Enable the WP REST API integration
        'version' => 'wc/v3' // WooCommerce WP REST API version
    ]
);