<?php

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;

$woocommerce = new Client(
    get_site_url(), // Your store URL
    'ck_fca70f178d9782227dddc2c3080a569a53a616e3', // Your consumer key
    'cs_d53152c03331f52b410b9d9e79aa84129dd34ba7', // Your consumer secret
    [
        'wp_api' => true, // Enable the WP REST API integration
        'version' => 'wc/v3' // WooCommerce WP REST API version
    ]
);