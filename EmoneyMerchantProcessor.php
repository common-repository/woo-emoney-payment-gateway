<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
*
* ● From the merchant’s website user is redirected to special page
* ● After authorization and authentication he chooses the payment method
* or cancels (is redirected to cancelurl) the flow
* ● After the payment finishes - he is redirected to
* successurl/errorurl, based on the payment status
* ● eMoney sends additional callback to the merchant’s service and waits
* for the answer on payment status, to properly finish the flow
*
* 4 callback urls are needed to end the flow :
* success_url, error_url, cancel_url (for user redirection after the
* operation finishes)
* callback_url (for service-to-service operation status check/callback)
*/
class EmoneyMerchantProcessor
{

    /**
     * gateway endpoint
     * @var string
     */
    public $submit_url = 'https://www.emoney.ge/index.php/main/merchantstransfer';

    /**
     * merchant name, mandatory
     * @var string
     */
    public $merchant;

    /**
     * secret key for eMoney API, mandatory
     * @var string
     */
    public $secret_key;

    /**
     * 1 when test mode is enabled, 0 if disabled
     * @var numeric
     */
    public $test_mode;

    /**
     * an url where client is redirected after successful form submit on eMoney, only needed when test mode is enabled
     * @var string
     */
    public $success_url;

    /**
     * an url where client is redirected after failed form submit on eMoney, only needed when test mode is enabled
     * @var string
     */
    public $error_url;

    /**
     * an url where client is redirected after cancelled form submit on eMoney, only needed when test mode is enabled
     * @var string
     */
    public $cancel_url;

    /**
     * unique order number, if payment finishes successfully same order code can not be used again.
     * @var string
     */
    public $order_code;

    /**
     * transaction amount in fractional units, mandatory (up to 12 digits)
     * 100 = 1 unit of currency. e.g. 1 gel = 100.
     * @var numeric
     */
    public $amount;

    /**
     * transaction currency code (ISO 4217), mandatory, (3 letters)
     * http://en.wikipedia.org/wiki/ISO_4217
     * GEL/USD
     * @var numeric
     */
    public $currency;

    /**
     * transaction details, mandatory, allowed HTML tags <br> and <b>
     * @var string
     */
    public $description;

    /**
     * additional data, for customers this field is invisible, maximum length: 100 characters
     * @var string
     */
    public $custom_data;

    /**
     * interface language, KA/EN/RU​, mandatory
     * @var string
     */
    public $language;

    public function __construct()
    {

    }

    /**
     * creates redirect url to eMoney merchant gateway
     * @return string
     */

    public function get_redirect_url()
    {
        $data = array(
            'merchant'    => $this->merchant,
            'ordercode'   => $this->order_code,
            'amount'      => $this->amount,
            'currency'    => $this->currency,
            'description' => $this->description,
            // 'customdata'  => $this->custom_data,
            'lng'         => $this->language,
            'testmode'    => $this->test_mode,
            'check'       => md5($this->secret_key . $this->merchant . $this->order_code . $this->amount . $this->currency . $this->description . $this->custom_data . $this->language . $this->test_mode),
            'successurl'  => $this->success_url,
            'errorurl'    => $this->error_url,
            'cancelurl'   => $this->cancel_url,
            'callbackurl' => $this->callback_url,
        );

        return $this->submit_url . '/?' . http_build_query($data);
    }

    /**
     * making sure response is really sent from eMoney
     * @return boolean
     */
    public function verify_response()
    {
        // checking if all details are supplied, validating data
        $required_array_keys = ['status', 'amount', 'currency', 'ordercode', 'check'];
        foreach ( $required_array_keys as $key ) {
            if ( !array_key_exists( $key, $_GET ) ) {
                return false;
            }
        }
        
        if ( !( is_numeric( $_GET['amount'] ) && is_numeric( $_GET['ordercode'] ) && ctype_alpha( $_GET['status'] ) && ctype_alpha( $_GET['currency'] ) && ctype_alnum( $_GET['check'] ) ) ) {
            return false;
        }

        $check_string = $_GET['status'] . ( isset( $_GET['transactioncode'] ) ? filter_var( $_GET['transactioncode'], FILTER_VALIDATE_INT ) : '' ) . ( isset( $_GET['date'] ) ? filter_var( $_GET['date'], FILTER_VALIDATE_INT ) : '' ) . $_GET['amount'] . $_GET['currency'] . $_GET['ordercode'] 
        // . (isset($_GET['customdata']) ? $_GET['customdata'] : '')  no custom data transfer to the server yet
        . ( isset( $_GET['testmode'] ) ? filter_var( $_GET['testmode'], FILTER_VALIDATE_INT ) : '' ) . $this->secret_key;
        $check_code   = md5($check_string);

        // verifying response authenticity
        return $check_code === $_GET['check'];
    }

    /**
     * generates data to answer to eMoney on callback
     * @param numeric result_code
     * @param string result_description
     * @param array|null data
     * @return string
     */
    public function get_response_for_emoney($result_code, $result_description, $data)
    {
        $check_code = md5(md5($result_code . $result_description . $this->secret_key));

        $result_array = array(
            'resultcode' => $result_code,
            'resultdesc' => $result_description,
            'check'      => $check_code,
            'data'       => $data
        );
        
        
        // eMoney accepts only XML
        header("Content-Type: application/xml");

        $xml          = new SimpleXMLElement( '<result/>' );
        $result_array = array_flip( $result_array );
        array_walk( $result_array, array( $xml, 'addChild' ) );
        $xml_text     = $xml->asXML();
        // removing unwanted XML version tag
        $xml_text     = substr($xml_text, strpos($xml_text, '?'.'>') + 2);
        
        return $xml_text;
    }
}
