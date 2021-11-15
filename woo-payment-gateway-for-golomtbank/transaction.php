<?php
class WC_Golomt_Bank_Payment_Gateway_Transaction
{
    static function invoice($order, $gen_token, $token, $key){

        global $woocommerce;
        global $wpdb;

        //echo $this->pb_authorize;

        if(version_compare(WOOCOMMERCE_VERSION, '2.0', '<')){
            $callback = get_site_url() . "/?wc-api=WC_Golomtbank_Gateway";
        }else{
            $callback = get_site_url() . "/wc-api/WC_Golomtbank_Gateway";
        }
        
        $amount = $order->get_total();
        $genToken = $gen_token;
        $returnType = 'POST';
        $transactionId = $order->get_transaction_id();
        $checksum = hash_hmac('sha256', $transactionId . $amount . $returnType . $callback, $key);
        
        $body = array(
            'amount' => $amount,
            'callback' => $callback,
            'checksum' => $checksum,
            'genToken' => $genToken,
            'returnType' => $returnType,
            'transactionId' => $transactionId
        );
        $body = wp_json_encode($body);
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        );
        $args = array(
            'body'   => $body,
            'blocking'    => true,
            'headers'     => $headers
        );

        $response = wp_remote_post('https://ecommerce.golomtbank.com/api/invoice', $args);
        $invoice = '';

        if (!is_wp_error($response)){

                $body = json_decode($response['body'], true);
                if(isset($body['invoice'])){
                    
                    $invoice = $body['invoice'];
                    //$wpdb->insert($wpdb->prefix . 'golomtbank_transactions', array('order_id' => $order_id, 'invoice_id' => $invoice, 'timestamp' => current_time('mysql', 1)));
                }
                else{
                    $order->update_status('failed');
                }
            }
            else{
                $order->update_status('failed');
            }
        return $invoice;
    }

    static function pay_by_token($order, $payment_token, $bearer_token, $key){

        global $woocommerce;
        global $wpdb;

        $amount = $order->get_total();
        $transactionId = $order->get_transaction_id();
        $checksum = hash_hmac('sha256', $amount . $transactionId . $payment_token , $key); 

        $body = array(
            'amount' => $amount,
            'checksum' => $checksum,
            'transactionId' => $transactionId,
            'lang' => "MN",
            'token' => $payment_token
        );
        $body = wp_json_encode($body);

        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $bearer_token
        );
        $args = array(
            'body'   => $body,
            'blocking'    => true,
            'headers'     => $headers
        );
        $response = wp_remote_post('https://ecommerce.golomtbank.com/api/pay', $args);

        $result = array(
            'errorCode' => '',
            'errorDesc' => '',
            'cardNumber' => null
        );

            if (!is_wp_error($response)){
                $body = json_decode($response['body'], true);
                if(isset($body['errorCode']))
                {
                    $result['errorCode'] = $body['errorCode'];
                    $result['errorDesc'] = $body['errorDesc'];
                    if(isset($body['cardNumber']))
                    {
                        $result['cardNumber'] = $body['cardNumber'];
                    }
                }
                else{
                    $result['errorDesc'] = 'Гүйлгээ амжилтгүй. /' . $body['error'] . '/' . $body['message'] ;
                }
            }
            else{
                $result['errorDesc'] = 'Гүйлгээ хийхэд холболтын алдаа гарсан';
            }
        return $result;
    }

    static function inquiry($transaction_id, $token, $key){

        $checksum = hash_hmac('sha256', $transaction_id . $transaction_id , $key); 
        
        $body = array(
            'checksum' => $checksum,
            'transactionId' => $transaction_id
        );
        $body = wp_json_encode($body);

        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        );
        $args = array(
            'body'   => $body,
            'blocking'    => true,
            'headers'     => $headers
        );
        $response = wp_remote_post('https://ecommerce.golomtbank.com/api/inquiry', $args);

        $result = array(
            'errorCode' => '',
            'errorDesc' => '',
            'token' => null,
            'cardNumber' => null
        );

            if (!is_wp_error($response)){
                $body = json_decode($response['body'], true);
                if(isset($body['errorCode']))
                {
                    $result['errorCode'] = $body['errorCode'];
                    $result['errorDesc'] = $body['errorDesc'];
                    if(isset($body['token']))
                    {
                        $result['cardNumber'] = $body['cardNumber'];
                    }
                    if(isset($body['token'])){
                        $result['token'] = $body['token'];
                    }
                }
                else{
                    $result['errorDesc'] = $body['message'] . '/' . $transactionId;
                }
            }
            else{
                $result['errorDesc'] = 'Гүйлгээг шалгахад холболтын алдаа гарсан';
            }
        return $result;
    }
}
?>