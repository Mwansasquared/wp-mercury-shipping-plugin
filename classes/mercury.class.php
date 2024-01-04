<?php

class WC_MERCURY_SHIPPING extends WC_Shipping_Method {

    $api_url = $_ENV['GET_FREIGHT_API_URL'];
    $email = $_ENV['EMAIL'];
    $private_key = $_ENV['PRIVATE_KEY'];
    $domestic_service = $_ENV['DOMESTIC_SERVICE'];
    $international_service = $_ENV['INTERNATIONAL_SERVICE'];

    public function __construct() {
        $this->id                 = 'mercury_logistics_shipping'; 
        $this->method_title       = __( 'Mercury Shipping for WooCommerce' );
        $this->method_description = __( 'This plugin sends information to calculate the shipping fee using the Mercury Logistics Courier API.' );

        $this->enabled            = "yes"; 
        $this->title              = "Mercury Shipping";
        $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
        $this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Mercury Shipping', 'mercury_logistics_shipping' );
        

        $this->init();

    }

    function init() {
        // Load the settings API
        $this->init_form_fields(); 
        $this->init_settings(); 

        // Save settings in admin
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

        

    }

    function init_form_fields() {

        $this->form_fields = array(
         'enabled' => array(
              'title' => __( 'Enable', 'mercury_logistics_shipping' ),
              'type' => 'checkbox',
              'description' => __( 'Enable this shipping.', 'mercury_logistics_shipping' ),
              'default' => 'yes'
              ),
         'title' => array(
            'title' => __( 'Title', 'mercury_logistics_shipping' ),
              'type' => 'text',
              'description' => __( 'Title to be display on site', 'mercury_logistics_shipping' ),
              'default' => __( 'Mercury Shipping', 'mercury_logistics_shipping' )
              ),
         );
    }

    public function calculate_shipping( $package ) {
            
        $selected_country_code = $_POST['country']; 
        $selected_city_name = $_POST['city'];

        $wc_countries_class = new WC_Countries(); 
        $wc_countries = $wc_countries_class->get_countries();
        
        
        $mercury_countries = $this->get_countries_from_database();      
        $mercury_cities = $this->get_cities_from_database();
        

        if (!empty($selected_country_code)) {
           
            $selected_country_name = $wc_countries[$selected_country_code];

            $matchingCountry = array_filter($mercury_countries, function ($mercury_country) use ($selected_country_name) {
                return $mercury_country->country_name === $selected_country_name;
            });

            if (!empty($matchingCountry)) {
        
                foreach ($matchingCountry as $item) {
                    $mercury_selected_country_id = $item->country_id;
                    
                    break;
                }
            }

            $matchingCity = array_filter($mercury_cities, function ($mercury_city) use ($selected_city_name) {
                return $mercury_city->city_name === $selected_city_name;
            });


            if (!empty($matchingCity)) {
        
                foreach ($matchingCity as $item) {
                    $mercury_selected_city_id = $item->city_id;
                    
                    break;
                }
            }
        
        } else {
            
            echo "Country not found!";
        }

        $shipping_fee = $this->calculate_mercury_shipping_fee($mercury_selected_country_id, $mercury_selected_city_id);


        $rate = array(
            'label'    => $this->title,
            'cost'     => $shipping_fee,
            'calc_tax' => 'per_item',
        );

        // Register the rate
        $this->add_rate( $rate );
        
    }

    public function calculate_mercury_shipping_fee($mercury_selected_country_id, $mercury_selected_city_id) {

        global $woocommerce;

        // Retrieve product details from the cart
        $cart_items = $woocommerce->cart->get_cart();
        $product_data = array();

        $source_country = 3; //Zambia
        $source_city = 1; //Lusaka
        $vendor_id = 0;
        $insurance = 1;


        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $product = wc_get_product($product_id);

            $product_data[] = array(

                'vendor_id' => 0,
                'source_country' => $source_country,
                'source_city' => $source_city,
                'destination_country' => $mercury_selected_country_id,
                'destination_city' => $mercury_selected_city_id,
                'insurance' => 1,
                'length'  => $product->get_length(),
                'width'   => $product->get_width(),
                'height'  => $product->get_height(),
                'pieces' => $cart_item['quantity'],
                'gross_weight'  => $product->get_weight(),

            );
        }

        $shipmentData = json_encode($product_data);


        $api_url_string = $api_url.'?email='.$email.'&private_key='.$private_key.'&domestic_service='.$domestic_service.'&international_service='.$international_service.'&shipment='.$shipmentData;

        $response = wp_remote_get($api_url_string);


        if (!is_wp_error($response) && $response['response']['code'] === 200) {
            $api_result = json_decode($response['body'], true);

            if ($api_result['rate'] === null ) {
                return 0;
            }

            $mercury_shipping_fee = $api_result['rate'];

            // wp_send_json_success($mercury_shipping_fee);
            

            return $mercury_shipping_fee;
        }

        
        return 0;

    }


    public function get_countries_from_database() {
        global $wpdb;
    
        $countries_table = $wpdb->prefix . 'countries_wp_table_version';
    
        $countries = $wpdb->get_results("SELECT * FROM $countries_table ORDER BY country_name");
    
        return $countries;
    }


   
    public function get_cities_from_database() {

        global $wpdb;


        $cities_table = $wpdb->prefix . 'cities_wp_table_version';

        $cities = $wpdb->get_results("SELECT * FROM $cities_table");

        return $cities;
    }


}


