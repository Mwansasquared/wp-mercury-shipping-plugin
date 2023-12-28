<?php

class WCMercuryCron extends WC_MERCURY_SHIPPING {

    $get_countries_api_url = $_ENV['GET_COUNTRIES_URL'];

    if (get_option( 'wp_mercury_api_info' ) === false) {
        
        $info_mercury_api = get_country_state_city();
        
        // Save API call as a Transient.
        add_option( 'wp_mercury_api_info', $info_mercury_api );

        return;
    }
     // Custom Tables
    if (get_option( 'countries_wp_table_version' )) {
        
        create_database_table();
    }

    // create_database_table();

    save_database_table_info();

    function get_country_state_city() {
        
        $args = array (
            'header' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => array()
        );
    
        $response = wp_remote_get($get_countries_api_url, $args);
        $body = json_decode($response['body'], true);
    
        $response_message = $body['message'];
    
        $expectedMessage = "Success";
    
        if ( $response_message === "Success" ) {
            return $body;
        }
    
        if ( $response_message !== "Success" ){
        
            return "Error in pinging API";
        }
    
    
    }

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
    
    
    function save_database_table_info() {
    
        global $wpdb;
        
        $table_name = $wpdb->prefix;
        
        $body =  get_option( 'wp_mercury_api_info' );
    
        function insert_country($country_name, $country_id) {
            global $wpdb;
        
            $table_name = $wpdb->prefix . 'countries_wp_table_version';
        
            $wpdb->insert($table_name, array(
                'country_name'=> $country_name,
                'country_id' => $country_id));
    
            return $wpdb->insert_id;
        }
        
        function insert_state($state_name, $state_id, $country_id) {
            global $wpdb;
        
            $table_name = $wpdb->prefix . 'states_wp_table_version';
        
            $wpdb->insert($table_name, array(
                'state_name' => $state_name,
                'state_id' => $state_id,
                'country_id' => $country_id));
        
            return $wpdb->insert_id;
        }
        
        function insert_city($city_name, $city_id, $state_id) {
            global $wpdb;
        
            $table_name = $wpdb->prefix . 'cities_wp_table_version';
        
            $wpdb->insert($table_name, array(
                'city_name' => $city_name,
                'city_id' => $city_id,
                'state_id' => $state_id));
        }
    
        // Process the data and insert it into the tables
        if (isset($body['data']['country']) && is_array($body['data']['country'])) {
            foreach ($body['data']['country'] as $country_data) {
                $country_name = $country_data['country_name'];
                $country_id = $country_data['id'];
                insert_country($country_name, $country_id);
                
                if (isset($country_data['state']) && is_array($country_data['state'])) {
                    foreach ($country_data['state'] as $state_data) {
                        $state_name = $state_data['state_name'];
                        $state_id = $state_data['id'];
                        $id = insert_state($state_name, $state_id, $country_id);
                        
                        if (isset($state_data['city']) && is_array($state_data['city'])) {
                            foreach ($state_data['city'] as $city_data) {
                                $city_name = $city_data['city_name'];
                                $city_id = $city_data['id'];
                                insert_city($city_name, $city_id, $state_id);
                            }
                        }
                    }
                }
            }
        }
    
    }

}