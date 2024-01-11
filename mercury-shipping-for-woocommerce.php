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

register_activation_hook( __FILE__, 'activation_function' );

function activation_function() {
    if (get_option( 'countries_wp_table_version' ) === false) {
        // echo 'create tables call';
        create_database_table();
    } 
    
    save_country_state_city();
}

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


function create_database_table() {
    
    global $countries_wp_table_version;
    global $state_wp_table_version;
    global $cities_wp_table_version;
    global $wpdb;

    $countries_wp_table_version = '1.0.0';
    $states_wp_table_version = '1.0.0';
    $cities_wp_table_version = '1.0.0';


    $charset_collate = $wpdb->get_charset_collate();

    // Create the Countries table
    $countries_table_name = $wpdb->prefix . 'countries_wp_table_version';
    $countries_sql = "CREATE TABLE $countries_table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        country_id int(11) NOT NULL,
        country_name varchar(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($countries_sql);

    // Create the States table
    $states_table_name = $wpdb->prefix . 'states_wp_table_version';
    $states_sql = "CREATE TABLE $states_table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        state_id int(11) NOT NULL,
        state_name varchar(255) NOT NULL,
        country_id int(11) NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (country_id) REFERENCES $countries_table_name(id)
    ) $charset_collate;";

    dbDelta($states_sql);

    // Create the Cities table
    $cities_table_name = $wpdb->prefix . 'cities_wp_table_version';
    $cities_sql = "CREATE TABLE $cities_table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        city_id int(11) NOT NULL,
        city_name varchar(255) NOT NULL,
        state_id int(11) NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (state_id) REFERENCES $states_table_name(id)
    ) $charset_collate;";

    dbDelta($cities_sql);


    // Save API call as a Transient.
    add_option( 'countries_wp_table_version', $countries_wp_table_version );
    add_option( 'states_wp_table_version', $states_wp_table_version );
    add_option( 'cities_wp_table_version', $cities_wp_table_version );

}

function save_country_state_city() {
    global $wpdb;
    $country_tbl = $wpdb->prefix. 'countries_wp_table_version';
    $states_tbl = $wpdb->prefix . 'states_wp_table_version';
    $cities_tbl  = $wpdb->prefix . 'cities_wp_table_version';

    // Check if the table has any records
    $has_countries = $wpdb->get_var("SELECT EXISTS (SELECT 1 FROM $country_tbl LIMIT 1)");
    $has_states = $wpdb->get_var("SELECT EXISTS (SELECT 1 FROM $states_tbl LIMIT 1)");
    $has_cities = $wpdb->get_var("SELECT EXISTS (SELECT 1 FROM $cities_tbl LIMIT 1)");


    if ($has_countries && $has_cities && $has_states) {
        return 'Saved shipping addresses retrieved successfully!';
    }
    $url = 'http://116.202.29.37/quotation1/app/getcountrystatecity';
    $args = array(
        'header' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => array()
    );

    $response = wp_remote_get($url, $args);
    $body = wp_remote_retrieve_body($response);
    $body = json_decode($body, true);

    try {
        $response_message = $body['message'];
        if ($response_message === "Success") {
            function insert_country($country_name, $country_id)
            {
                global $wpdb;
                $table_name = $wpdb->prefix . 'countries_wp_table_version';
                $wpdb->insert($table_name, array(
                    'country_name' => $country_name,
                    'country_id' => $country_id
                ));

                return $wpdb->insert_id;
            }
            function insert_state($state_name, $state_id, $country_id)
            {
                global $wpdb;
                $table_name = $wpdb->prefix . 'states_wp_table_version';
                $wpdb->insert($table_name, array(
                    'state_name' => $state_name,
                    'state_id' => $state_id,
                    'country_id' => $country_id
                ));
                return $wpdb->insert_id;
            }

            function insert_city($city_name, $city_id, $state_id)
            {
                global $wpdb;
                $table_name = $wpdb->prefix . 'cities_wp_table_version';
                $wpdb->insert(
                    $table_name,
                    array(
                        'city_name' => $city_name,
                        'city_id'   => $city_id,
                        'state_id'  => $state_id,
                    )
                );
            }

            if ($body['status'] && isset($body['data']['country'])) {
                foreach ($body['data']['country'] as $country) {
                    $country_id = insert_country($country['country_name'], $country['id']);
                    if (isset($country['state'])) {
                        foreach ($country['state'] as $state) {
                            $state_id = insert_state($state['state_name'], $state['id'], $country_id);
                            if (isset($state['city'])) {
                                foreach ($state['city'] as $city) {
                                    insert_city($city['city_name'], $city['id'], $state_id);
                                }
                            }
                        }
                    }
                }
            }
            return 'New shipping addresses info saved successfully!';
        }
        if ($response_message !== "Success") {
            return "Error in pinging API";
        }
    } catch (\Throwable $th) {
        //throw $th;
        return "Error in pinging API - server error: ".$th->getMessage();
    }
}
