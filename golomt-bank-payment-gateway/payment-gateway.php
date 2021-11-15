<?php
/*
Plugin Name: Golomt Bank WooCommerce Payment Gateway
Plugin URI: 
Description: Golomt Bank Payment Gateway allows you to accept payment through credit cards, Social Pay on your Woocommerce Powered Site.
Version: 1.0.0
Author: Khurelbaatar
Author URI: 
WC tested up to: 5.8.1
Text Domain: woocommerce-golomtbank-payment-gateway
Domain Path: /languages
License: free
License URI: free
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '\transaction.php';
add_action('plugins_loaded', 'woocommerce_golomtbank_init', 0);

function woocommerce_golomtbank_init()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Gateway class
     */
    class WC_Golomtbank_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {
            global $woocommerce;

            $this->id = 'golomtbank_gateway';
            $this->icon = apply_filters('golomtbank_icon', plugins_url('img/golomt-icon.png', __FILE__));
            $this->has_fields = true;
            $this->notify_url = WC()->api_request_url('WC_Golomtbank_Gateway');
            $this->method_description = __('golomtbank Payment Gateway allows you to accept payment through credit card and SocialPay On your Woocommerce Powered Site.', 'woo-payment-gateway-for-golomtbank');
            $this->redirect_page_id = $this->get_option('redirect_page_id');
            $this->method_title = __('Голомт банк', 'woo-payment-gateway-for-golomtbank');
            
            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            $this->title = sanitize_text_field($this->get_option('title'));
            $this->description = sanitize_text_field($this->get_option('description'));
            $this->gb_PayMerchantToken = sanitize_text_field($this->get_option('eb_PayMerchantToken'));
            $this->gb_PayMerchantKey = sanitize_text_field($this->get_option('eb_PayMerchantKey'));
            $this->gb_render_logo = sanitize_text_field($this->get_option('gb_render_logo'));

            //Actions
            add_action('woocommerce_receipt_golomtbank_gateway', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
            // Payment listener/API hook
            add_action('woocommerce_api_wc_golomtbank_gateway', array($this, 'check_golomtbank_response'));

            if($this->gb_render_logo == "yes") {
                $this->icon = apply_filters('golomtbank_icon', plugins_url('img/golomtbank.svg', __FILE__));
            }
            add_action('init','register_session');
        }

        /**
         * Initialise Gateway Settings Form Fields
         * */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Идэвхжүүлэх',
                    'label' => 'Тийм',
                    'type' => 'checkbox',
                    'description' => 'Enable or disable the gateway',
                    'desc_tip' => true,
                    'default' => 'yes'
                ) ,
                'title' => array(
                    'title' => 'Гарчиг',
                    'type' => 'text',
                    'description' => 'төлөлт хийх явцад хэрэглэгчид харагдана.',
                    'default' => 'Голомт банк',
                    'desc_tip' => false
                ) ,
                'description' => array(
                    'title' => 'Тайлбар',
                    'type' => 'textarea',
                    'description' => 'төлөлт хийх явцад хэрэглэгчид харагдана.',
                    'default' => 'Голомт банкны карт болон SocialPay апп ашиглан төлөлт хийх боломжтой.'
                ) ,
                'eb_PayMerchantKey' => array(
                    'title' => 'Мерчант KEY',
                    'type' => 'password',
                    'description' => 'мерчантын гэрээ хийсэний дараа банкнаас авна.', 
                    'default' => '', 
                    'desc_tip' => false
                ),
                'eb_PayMerchantToken' => array(
                    'title' => 'Мерчант TOKEN', 
                    'type' => 'text',
                    'description' => 'мерчантын гэрээ хийсэний дараа банкнаас авна.', 
                    'default' => '', 
                    'desc_tip' => false
                )
            );
        }

         /**
         * Admin Panel Options
         * */
        public function admin_options()
        {
            echo '<h3>' . __('Голомт банк', 'woo-payment-gateway-for-golomtbank') . '</h3>';
            echo '<p>' . __('Голомт банкны карт болон SocialPay апп ашиглан төлөлт хийх боломжтой.', 'woo-payment-gateway-for-golomtbank') . '</p>';

            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        function register_session(){
            if( !session_id() )
                session_start();
        }

        function payment_fields()
        {
            global $woocommerce;
            if ( $description = $this->get_description() ) {
                echo wpautop( wptexturize( esc_attr( $description ) ) );
            }

            echo '<style>
            .golomtbank_payment_golomtbank_payment_container {
              display: block;
              position: relative;
              padding-left: 25px;
              margin-bottom: 12px;
              cursor: pointer;
              font-size: 14px;
              -webkit-user-select: none;
              -moz-user-select: none;
              -ms-user-select: none;
              user-select: none;
              font-family: Arial, Helvetica, sans-serif;
            }
            .golomtbank_payment_golomtbank_payment_container input {
              position: absolute;
              opacity: 0;
              cursor: pointer;
              height: 0;
              width: 0;
            }
            
            .golomtbank_payment_checkmark {
              position: absolute;
              top: 0;
              left: 0;
              height: 22px;
              width: 22px;
              background-color: #0394fc;
              border-radius: .25em;
            }
            
            .golomtbank_payment_golomtbank_payment_container:hover input ~ .golomtbank_payment_checkmark {
              background-color: #ccc;
            }
            
            .golomtbank_payment_golomtbank_payment_container input:checked ~ .golomtbank_payment_checkmark {
              background-color: #0b03fc;
            }
            
            .golomtbank_payment_checkmark:after {
              content: "";
              position: absolute;
              display: none;
            }
            
            .golomtbank_payment_golomtbank_payment_container input:checked ~ .golomtbank_payment_checkmark:after {
              display: block;
            }
            
            .golomtbank_payment_golomtbank_payment_container .golomtbank_payment_checkmark:after {
              left: 7px;
              top: 4px;
              width: 7px;
              height: 12px;
              border: solid #d2fc03;
              border-width: 0 3px 3px 0;
              -webkit-transform: rotate(45deg);
              -ms-transform: rotate(45deg);
              transform: rotate(45deg);
            }
        
            .golomtbank_payment_gateway_select_tokens {
                -webkit-appearance:none;
                -moz-appearance:none;
                -ms-appearance:none;
                appearance:none;
                outline:;
                box-shadow:none;
                border:0!important;
                background-image: none;
                flex: 1;
                padding: 0 .5em;
                cursor:pointer;
                font-size: 16px;
                font-weight: 600;
             }
             .golomtbank_payment_gateway_select_tokens::-ms-expand {
                display: none;
             }
             .golomtbank_payment_gateway_select {
                display: flex;
                width: 10em;
                line-height: 2;
                background: #5c6664;
                overflow: hidden;
                border-radius: .25em;
                margin-left: 15px
             }
             .golomtbank_payment_gateway_select::after {
                content: "\25BC";
                font-size:15px;
                color: #fff;
                position: relative;
                top: 0;
                right: 0;
                padding: 0 5px;
                background: #0b03fc;
                cursor:pointer;
                pointer-events:none;
                transition:.25s all ease;
             }
             .golomtbank_payment_gateway_select:hover::after {
                color: #d2fc03;
             }
             #golomtbank_payment_choose_token{
                font-size: 15px;
                margin-left: 15px;
             }
            </style>';
            
            echo '<script>
		    jQuery(document).ready(function($){
                $("#golomtbank_payment_user_tokens").hide();
                $("#checkout_checkbox_gen_token").on("change" ,function(){
                    if(this.checked)
                    {
                        $("#checkout_checkbox_use_token").prop("checked", false);
                        $("#golomtbank_payment_user_tokens").hide();
                    }
                });
                $("#checkout_checkbox_use_token").on("change" ,function(){
                    if(this.checked)
                    {
                        $("#checkout_checkbox_gen_token").prop("checked", false);
                        $("#golomtbank_payment_user_tokens").show();
                    }
                    else
                    {
                        $("#golomtbank_payment_user_tokens").hide();
                    }
                });
            });
	        </script>';

            echo '<div style = margin-top:10px>
            <label class="golomtbank_payment_golomtbank_payment_container">Токен үүсгэх
            <input type="checkbox" id = "checkout_checkbox_gen_token" name = "checkout_checkbox_gen_token">
            <span class="golomtbank_payment_checkmark"></span>
            </label></div>
            <div>
            <label class="golomtbank_payment_golomtbank_payment_container">Токеноор гүйлгээ хийх
            <input type="checkbox" id = "checkout_checkbox_use_token" name = "checkout_checkbox_use_token">
            <span class="golomtbank_payment_checkmark"></span>
            </label></div>
            <div id="golomtbank_payment_user_tokens"><label id="golomtbank_payment_choose_token">Токен үүссэн данс сонгох:</label><div class="golomtbank_payment_gateway_select"><select class="golomtbank_payment_gateway_select_tokens" id="golomtbank_payment_user_token_select" name="golomtbank_payment_token_acc">';
           
            $user_tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'golomtbank');
            
            foreach($user_tokens as $token){
                $token_decode = json_decode($token, true);
                echo '<option value="'. esc_attr($token_decode['id']) .'">' . $token_decode['last4'] . '</option>';
            }
            echo '</select></div></div>';
        }
    
        /**
         * Generate the  golomtbank Payment button link
         * */
        function generate_golomtbank_form($order_id)
        {   
            wc_enqueue_js('
            $.blockUI({
            message: "' . esc_js(__('Баярлалаа. Таныг Голомт банкны төлбөр хийх хуудас руу холбож байна.', 'woo-payment-gateway-for-golomtbank')) . '",
            baseZ: 99999,
            overlayCSS:
            {
            background: "#fff",
            opacity: 0.6
            },
            css: {
            padding:        "20px",
            zindex:         "9999999",
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:		"24px",
            }
            });
            jQuery("#gb_payment_form").submit();
            ');

        $_SESSION['order_id'] = $order_id;
        WC()->session->set('gb_order_id', $order_id);

        $order = new WC_Order($order_id);

        $t=substr(time(), 4);
        $order -> set_transaction_id('GB' . $t);
        $order -> save();

        $gen_token = filter_var($_GET['gentoken'], FILTER_SANITIZE_STRING);

        if(isset($_GET['token']) && $gen_token == 'N'){

            $token = filter_var($_GET['token'], FILTER_SANITIZE_STRING);
            $token_id = WC_Payment_Tokens::get( $token);
            $token_decode = json_decode($token_id, true);
            
            $result = WC_Golomt_Bank_Payment_Gateway_Transaction::pay_by_token($order, $token_decode['token'], $this->gb_PayMerchantToken, $this->gb_PayMerchantKey);
            
            if ($result['errorCode'] != '000') {
            
                $order->update_status('failed', 'ErrorCode[' . $result['errorCode'] . '] ' . $result['errorDesc']);
                $order->save();
            } else {
                // Reduce stock levels
                $order->reduce_order_stock();
                // Empty cart
                WC()->cart->empty_cart();
                $order->update_status('completed', $result['errorDesc']);
                $order->save();
            }
            wp_redirect($order->get_checkout_order_received_url());
            exit;
        }else{
                $invoice = '/payment/en/';
                if (get_locale() == 'mn') {
                    $invoice = '/payment/mn/';
                }

                $invoice .= WC_Golomt_Bank_Payment_Gateway_Transaction::invoice($order, $gen_token, $this->gb_PayMerchantToken, $this->gb_PayMerchantKey);
                $html = '<form action="' . esc_url('https://ecommerce.golomtbank.com' . $invoice) . '" method="get" id="gb_payment_form"></form>';
                return $html;
            }
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {

            $order = new WC_Order($order_id);

            if ( ! (int) isset( $_POST['checkout_checkbox_gen_token'] ) ) {
                $token_require = 'N';
            }else{
                $token_require = 'Y';
            }  
            
            if ( ! (int) isset( $_POST['checkout_checkbox_use_token'] ) ) {
                $token_acc = null;
            }else
            {
                $token_acc = sanitize_text_field( $_POST['golomtbank_payment_token_acc'] );
            }

            $current_version = get_option( 'woocommerce_version', null );
            if (version_compare( $current_version, '2.2.0', '<' )) { //older version
                return array('result' => 'success', 'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, add_query_arg('gentoken', $token_require, add_query_arg('token', $token_acc, get_permalink(woocommerce_get_page_id('pay'))))))
                );
            }
            else if (version_compare( $current_version, '2.4.0', '<' )) { //older version
                return array
                    (
                        'result' => 'success',
                        'redirect'	=> add_query_arg('order-pay', $order->id, add_query_arg('key', $order->order_key, add_query_arg('gentoken', $token_require, add_query_arg('token', $token_acc, get_permalink(woocommerce_get_page_id('pay'))))))
                    );
            }else if (version_compare( $current_version, '3.0.0', '<' )) { //older version
                return array
                    (
                        'result' => 'success',
                        'redirect'	=> add_query_arg('order-pay', $order->id, add_query_arg('key', $order->order_key, add_query_arg('gentoken', $token_require, add_query_arg('token', $token_acc, wc_get_page_permalink('checkout')))))
                    );
            }else {
                return array('result' => 'success', 
                'redirect' => add_query_arg('order-pay', $order->get_id(), add_query_arg('key', $order->get_order_key(), add_query_arg('gentoken', $token_require, add_query_arg('token', $token_acc, wc_get_page_permalink('checkout'))))));
            }

            return array('result' => 'success', 
            'redirect' => add_query_arg('order-pay', $order->get_id(), add_query_arg('key', $order->get_order_key(), add_query_arg('gentoken', $token_require , add_query_arg('token', $token_acc, wc_get_page_permalink('checkout'))))));
        }

        /**
         * Output for the order received page.
         * */
        function receipt_page($order)
        {
            echo '<p>' . __('Баярлалаа. Таныг Голомт банкны төлбөр хийх хуудас руу холбож байна.', 'woo-payment-gateway-for-golomtbank') . '</p>';
            echo $this->generate_golomtbank_form($order);
        }

        /**
         * Verify a successful Payment!
         * */
         function check_golomtbank_response()
         {
            global $woocommerce;
            global $wpdb;

            $transaction_id = sanitize_text_field( filter_var( $_POST['invoice'], FILTER_SANITIZE_STRING) );
            $status_code = sanitize_text_field( filter_var( $_POST['status_code'], FILTER_SANITIZE_STRING) );
            $desc = sanitize_text_field( filter_var( $_POST['desc'], FILTER_SANITIZE_STRING ) );
            
            $order_id = $wpdb->get_var( "
            SELECT POST_ID 
            FROM    $wpdb->postmeta 
            WHERE meta_key = '_transaction_id'
            and meta_value = '$transaction_id'
            " );

            $order = new WC_Order($order_id);
            
            if (isset($_POST['invoice']) && isset($_POST['status_code']) && isset($_POST['desc'])) {

                
                $result = WC_Golomt_Bank_Payment_Gateway_Transaction::inquiry($transaction_id, $this->gb_PayMerchantToken, $this->gb_PayMerchantKey);
                
                if(isset($result['token']) && $result['token'] != '') {

                    $token = new WC_Payment_Token_CC();
                    $token->set_token($result['token']);
                    $token->set_user_id($order->get_user_id());
                    $token->set_gateway_id('golomtbank');

                    $token->set_card_type('credit card');
                    $token->set_last4($result['cardNumber']);
                    $token->set_expiry_year('****');
                    $token->set_expiry_month('**');

                    $token->save();
                    WC_Payment_Tokens::set_users_default( $order->get_user_id(), $token->get_id() );
                }

                if ($result['errorCode'] != '000') {

                    $order->update_status('failed', 'ErrorCode[' . $result['errorCode'] . '] ' . $result['errorDesc']);
                    $order->save();
                } else {
                    // Reduce stock levels
                    $order->reduce_order_stock();
                    // Empty cart
                    WC()->cart->empty_cart();
                    $order->update_status('completed', $result['errorDesc']);
                    $order->save();
                }
                wp_redirect($order->get_checkout_order_received_url());
                exit;
            }
            exit;
        }
    }

    /**
     * Add golomtbank Gateway to WC
     * */
    function woocommerce_add_golomtbank_gateway($methods)
    {
        $methods[] = 'WC_Golomtbank_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_golomtbank_gateway');

    /**
     * Add Settings link to the plugin entry in the plugins menu for WC below 2.1
     * */
    if (version_compare(WOOCOMMERCE_VERSION, "2.1") <= 0) {

        add_filter('plugin_action_links', 'golomtbank_plugin_action_links', 10, 2);

        function golomtbank_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Golomtbank_Gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }

    }
    /**
     * Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
     * */else {
        add_filter('plugin_action_links', 'golomtbank_plugin_action_links', 10, 2);

        function golomtbank_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WC_Golomtbank_Gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }

    }

}
