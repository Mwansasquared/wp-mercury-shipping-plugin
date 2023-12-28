<?php

class WC_MERCURY_SHIPPING extends WC_Shipping_Method {

    $email = $_ENV['EMAIL'];
    $private_key = $_ENV['PRIVATE_KEY'];
    $domestic_service = $_ENV['DOMESTIC_SERVICE'];
    $international_service = $_ENV['INTERNATIONAL_SERVICE'];
    $get_freight_api_url = $_ENV['GET_FREIGHT_URL'];

    public function __construct() {
        $this->id                 = 'mercury_logistics_shipping'; 
        $this->method_title       = __( 'Mercury Shipping for WooCommerce' );
        $this->method_description = __( 'This plugin sends information to calculate the shipping fee using the Mercury Logistics Courier API.' );

        $this->enabled            = "yes"; 
        $this->title              = "Mercury Shipping";

        $this->init();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

    }

    public function init() {
        // Load the settings API
        $this->init_form_fields(); 
        $this->init_settings(); 

        // Save settings in admin
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

        add_action('wp_ajax_get_mercury_shipping_fee', array($this, 'calculate_mercury_shipping_fee'));
        add_action('wp_ajax_nopriv_ajax_get_mercury_shipping_fee', array($this, 'ajax_get_mercury_shipping_fee'));

    }

    public function enqueue_scripts() {
    
        wp_enqueue_script('custom-location-scrip', plugin_dir_url(__FILE__) . 'custom-location-script.js', array('jquery'), null, true);

        
        wp_localize_script('custom-location-script', 'custom_location_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_location_data_nonce'),
            'countries' => $countries,
        ));
    }


    public function calculate_shipping( $package ) {


        $response = $this->setMercuryShippingFee();

        echo'Shipping response in calculate_shipping::'. $response;

        $rate = array(
            'id'       => $this->id,
            'label'    => $this->title,
            'cost'     =>  73.20,
            'calc_tax' => 'per_item',
        );

        // Register the rate
        $this->add_rate( $rate );
        
    }

    public function calculate_mercury_shipping_fee($country_id, $city_id) {


        global $woocommerce;

      
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
                'destination_country' => $country_id,
                'destination_city' => $city_id,
                'insurance' => 1,
                'length'  => $product->get_length(),
                'width'   => $product->get_width(),
                'height'  => $product->get_height(),
                'pieces' => $cart_item['quantity'],
                'gross_weight'  => $product->get_weight(),

            );
        }

        $shipmentData = json_encode($product_data);

        

        $api_url_string = $get_freight_api_url.'?email='.$email.'&private_key='.$private_key.'&domestic_service='.$domestic_service.'&international_service='.$international_service.'&shipment='.$shipmentData;

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


    public function ajax_get_mercury_shipping_fee() {

        check_ajax_referer('custom_location_data_nonce', 'nonce');

        $country_id = $_POST['country_id'];
        $city_id = $_POST['city_id'];

        $package = array();

        
        $shipping_fee = calculate_mercury_shipping_fee( $country_id, $city_id);

        wp_send_json_success($shipping_fee);
    
    }



}