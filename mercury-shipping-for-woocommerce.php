<?php

/**
 * Plugin Name: Mercury Shipping for WooCommerce
 * Plugin URI: https://github.com/Mwansasquared/wp-mercury-shipping-plugin
 * Author: Mwansa Mwansa
 * Author URI: https://github.com/Mwansasquared/
 * Description: Plugin integrates the Mercury logistics API into WooCommerce
 * Version: 0.0.1
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * text-domain: wordpress-mercury-shipping-plugin
*/

defined( 'ABSPATH' ) or die;


function mercury_shipping_init() {
    if ( ! class_exists( 'WC_MERCURY_SHIPPING') ) {

        require_once plugin_dir_path(__FILE__) . 'classes/mercury.class.php';
    }
}

add_action( 'woocommerce_shipping_init', 'mercury_shipping_init' );

function add_mercury_logistics_method( $methods ) {
    $methods['mercury_logistics_shipping'] = 'WC_MERCURY_SHIPPING';

    return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_mercury_logistics_method');
