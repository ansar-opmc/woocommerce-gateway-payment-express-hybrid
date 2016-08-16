<?php
/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) exit;

spl_autoload_register( 'WC_Gateway_Payment_Express_Hybrid::autoload' );

class DpsPxPayException extends Exception {}
class DpsPxPayCurlException extends Exception {}

/**
 * @class 		WC_Gateway_Payment_Express_Hybrid
 * @extends		WC_Payment_Gateway
 * @version		1.0
 */
class WC_Gateway_Payment_Express_Hybrid extends WC_Payment_Gateway {

	protected $paymentURL = false;		/* where to redirect browser for payment */
	protected $errorMessage = false;	/* last transaction error message */
	
	public function __construct() {
		
		global $woocommerce;

		$this->id   = 'payment_express_hybrid';
		$this->icon   = esc_url( plugin_dir_url( __FILE__ ) . 'images/cc_icons.png' );
		$this->has_fields  = true;
		/*adding support for subscription to the payment gateway*/
		$this->supports = array(
		   'products',
		   'refunds',
		   'subscriptions',
		   'subscription_cancellation', 
		   'subscription_suspension', 
		   'subscription_reactivation',
		   'subscription_date_changes',
		);
		$this->method_title = __('Payment Express', 'woothemes');

		/* Load the form fields. */
		$this->init_form_fields();

		/* Load the settings. */
		$this->init_settings();

		/* Define user set variables */
		$this->title       		= $this->get_option( 'title' );	
		$this->access_url       = $this->get_option( 'access_url' );
		$this->access_userid 	= $this->get_option( 'access_userid' );
		$this->access_key       = $this->get_option( 'access_key' );
		$this->site_name       	= $this->get_option( 'site_name' );

		$this->description  	= $this->get_option( 'description' );
		$this->URL 			= $this->get_option( 'pxPost_url' );	/* used for pxpost only. */
		$this->pp_user     	= $this->get_option( 'pxPost_username' );
		$this->pp_password 	= $this->get_option( 'pxPost_password' );



		add_action( 'woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		
		/* Hook IPN callback logic*/
		new WC_Gateway_Payment_Express_Hybrid_Notification_Handler( $this->access_userid, $this->access_key, $this->access_url );
				
		add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'process_payment_for_scheduled_subscription' ), 0, 3 );
		/* initiation of logging instance */
		$this->log = new WC_Logger();
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	function init_form_fields() {

		$default_site_name = home_url() ;

		$this->form_fields = array(

			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Payment Express', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'default' => 'yes',
			),

			'title' => array(
				'title' => __( 'Title', 'woocommerce-gateway-payment-express-pxhybrid' ),

				'type'  => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'default'     => __( 'Payment Express', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'css'   => 'width: 400px;',
			),

			'description' => array(
				'title' => __( 'Description', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'type'  => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'default'     => __( "Pay with your Credit Card via Payment Express.", 'woocommerce-gateway-payment-express-pxhybrid' ),

			),
			'group_title_pxpay_settings' => array(
                'title'         => __( '<h3>PX PAY Settings</h3>' , 'woocommerce-gateway-payment-express-pxhybrid' ) ,
                'type'          => 'title' ,
                'description'   => ''
                )   ,

			'site_name' => array(

				'title' => 'Merchant Reference',
				'description' => 'A name (or URL) to identify this site in the "Merchant Reference" field (shown when viewing transactions in the site\'s Digital Payment Express back-end). This name <b>plus</b> the longest Order/Invoice Number used by the site must be <b>no longer than 53 characters</b>.',
				'type' => 'text',
				'default' => $default_site_name,
				'css' => 'width: 400px;',
				'custom_attributes' => array( 'maxlength' => '53' )
                ),

			'access_url' => array(
				'title' => __( 'Px-Pay Access URL', 'woocommerce-gateway-payment-express-pxhybrid' ),

				'description' => __( 'For PxPay V1 use: https://sec.paymentexpress.com/pxpay/pxaccess.aspx<br/>For PxPay V2 use: https://sec.paymentexpress.com/pxaccess/pxpay.aspx', 'woothemes'),




				'type'  => 'text',

				'default' => esc_url( 'https://sec.paymentexpress.com/pxaccess/pxpay.aspx' ),
				'css'   => 'width: 400px;',
			),
			'access_userid' => array(
				'title' => __( 'Px-Pay User', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'type'  => 'text',
				'default' => __( '', 'woocommerce-gateway-payment-express-pxhybrid' ),

				'css'   => 'width: 200px;',
			),

			'access_key' => array(
				'title' => __( 'Px-Pay Password', 'woocommerce-gateway-payment-express-pxhybrid' ),


				'type'  => 'password',
				'default' => __( '', 'woocommerce-gateway-payment-express-pxhybrid' ),

				'css'   => 'width: 200px;',
			),
			
			'group_title_pxpost_settings' => array(
                'title'         => __( '<h3>PX POST Settings</h3>' , 'woocommerce-gateway-payment-express-pxhybrid' ) ,
                'type'          => 'title' ,
                'description'   => ''
                ) ,
			
			'pxPost_url' => array(
				'title' => __( 'Px-Post Access URL', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'type'  => 'text',
				'default' => esc_url( 'https://sec.paymentexpress.com/pxpost.aspx' ),
				'css'   => 'width: 400px;',
			),
			'pxPost_username' => array(
				'title' => __( 'Px-Post User', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'type'  => 'text',
				'default' => __( '', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'css'   => 'width: 200px;',
			),
			'pxPost_password' => array(
				'title' => __( 'Px-Post Password', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'type'  => 'password',
				'default' => __( '', 'woocommerce-gateway-payment-express-pxhybrid' ),
				'css'   => 'width: 200px;',
			)
			
		);
		
		/**
		 *  adding extra fields to the form by using hook
		 */
		$extra_fields = apply_filters( 'filter_pxpost_checkout_currency_selector_form_fields', array() );
		
		$this->form_fields = array_merge( $this->form_fields, $extra_fields );
		

	} /* End init_form_fields() */

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
?>
		<h3><?php _e( 'Payment Express', 'woocommerce-gateway-payment-express-pxhybrid' ); ?></h3>
		<p><?php _e( 'Allows your customers to pay via Payment Express PxPay (Supports PxPay1 & 2). The extension also uses PxPost for recurring billing support with the WooCommerce Subscriptions extension.', 'woocommerce-gateway-payment-express-pxhybrid' ); ?></p>

		<table class="form-table">
<?php
			/* Generate the HTML For the settings form. */
			$this->generate_settings_html();
?>
		</table><!--/.form-table-->
<?php
	} /* End admin_options() */
	
	/**
	 * fields to show on payment page - here it is only displaying description. To show form or other components include them below.
	 **/
	public function payment_fields(){
			if ( $this -> description ) { echo wpautop( wptexturize( $this -> description ) ); }
	}
	
	/**

	/**
	 * receipt_page
	 **/
	function receipt_page( $order_id ) {

		global $woocommerce;
		global $current_user;
		
		$order         = wc_get_order( $order_id );
		$order_number  = $order->get_order_number();
		$billing_name  = $order->billing_first_name." ".$order->billing_last_name;
		$shipping_name = explode(' ', $order->shipping_method);

		$http_host   = getenv("HTTP_HOST");
		$request_uri = getenv("SCRIPT_NAME");
		$server_url  = "http://$http_host";

		if ( method_exists( $woocommerce, 'api_request_url' ) ) {
			$script_url = $woocommerce->api_request_url( 'WC_Gateway_Payment_Express_Hybrid' );
			/*$script_url = esc_url_raw( add_query_arg( 'wc-api', get_class( $this ), trailingslashit( home_url( ) ) ) );*/
		} else {
			$script_url = $this->get_return_url();
		}
		$this->log->add( 'pxpay', print_r( array( 'return url' => $script_url ), true ) );

		$urlFail = $woocommerce->cart->get_checkout_url();
		
		if( function_exists ( "get_woocommerce_currency" ) ){
			$currency = get_woocommerce_currency(); 
		} else {
			$currency = get_option('woocommerce_currency');
		}
		$currency = apply_filters( 'filter_pxpay_checkout_currency', $currency, $order_id, $this->settings );
		
		//$MerchantRef = home_url();
		//$MerchantRef.= " # ".$order->order_key;
		$MerchantRef = $this->site_name . ' - Order # ' . $order_number ;
		if ( strlen( $MerchantRef ) > 64 ) {
			$MerchantRef = substr( $this->site_name , 0 , max( 50 - strlen( $order_number ) , 0 ) ) . '... - Order # ' . $order_number ;
			if ( strlen( $MerchantRef ) > 64 ) {
				$MerchantRef = 'Order # ' . substr( $order_numberd , 0 , 53 ) . '...' ;
			}
		}
		$txndata1 =  apply_filters( 'filter_custom_order_number', "Order number : ". $order_number );

		//Generate a unique identifier for the transaction
		$TxnId = uniqid("ID") ;
		$TxnId = $TxnId .'-'. $order_id;
		
		$TxnId =  apply_filters( 'filter_txn_id', $TxnId, $order );
		$MerchantRef =  apply_filters( 'filter_merchant_reference', $MerchantRef, $order );
		$success_url = apply_filters( 'filter_custom_success_url', $script_url, $order );
		$fail_url = apply_filters( 'filter_custom_fail_url', $script_url, $order );
		
		$billingID = "00" . time() ."-". $current_user->ID;

		$paymentReq = new DpsPxPayPayment( $this->access_userid, $this->access_key, $this->access_url );
		$paymentReq->txnType			= 'Purchase';
		$paymentReq->amount				= $order->order_total;
		$paymentReq->currency			= $currency;
		$paymentReq->transactionNumber	= $TxnId;
		$paymentReq->invoiceReference	= $MerchantRef;
		$paymentReq->option1			= $txndata1;
		$paymentReq->option2			= $billing_name;
		$paymentReq->option3			= $order->billing_email;
		$paymentReq->invoiceDescription	= $MerchantRef;
		$paymentReq->emailAddress		= $order->billing_email;
		$paymentReq->urlSuccess			= $success_url;
		$paymentReq->urlFail			= $fail_url;
		$paymentReq->billingID			= $billingID;
		$paymentReq->enableRecurring	= "1";
		
		// allow plugins/themes to modify invoice description and reference, and set option fields
		$paymentReq->invoiceDescription	= apply_filters('dpspxpay_invoice_desc', $paymentReq->invoiceDescription, $form);
		$paymentReq->invoiceReference	= apply_filters('dpspxpay_invoice_ref', $paymentReq->invoiceReference, $form);
		$paymentReq->option1			= apply_filters('dpspxpay_invoice_txndata1', $paymentReq->option1, $form);
		$paymentReq->option2			= apply_filters('dpspxpay_invoice_txndata2', $paymentReq->option2, $form);
		$paymentReq->option3			= apply_filters('dpspxpay_invoice_txndata3', $paymentReq->option3, $form);
		
		$this->log->add( 'pxpay', '========= initiating transaction request' );
		$this->log->add( 'pxpay', sprintf( '%s account, invoice ref: %s, transaction: %s, amount: %s, billingID: %s', 
			'test or live', $paymentReq->invoiceReference, $paymentReq->transactionNumber, $paymentReq->amount, $billingID ) );

		$this->log->add( 'pxpay', sprintf( 'success URL: %s', $paymentReq->urlSuccess ) );
		$this->log->add( 'pxpay', sprintf( 'failure URL: %s', $paymentReq->urlFail ) );
		
$this->log->add( 'pxpay', print_r( array( 'access_userid' => $this->access_userid, 'access_key' => $this->access_key, 'access_url' => $this->access_url ), true ) );
		
		$this->errorMessage = '';
		try {
			$response = $paymentReq->processPayment();

			if ($response->isValid) {
				$this->paymentURL = $response->paymentURL;
			}
			else {
				$this->errorMessage = 'Payment Express request invalid.';
				$this->log->add( 'pxpay', $this->errorMessage );
			}
		}
		catch (DpsPxPayException $e) {
			$this->errorMessage = $e->getMessage();
			$this->log->add( 'pxpay', $this->errorMessage );
		}
		$this->log->add( 'pxpay', $this->paymentURL );
		$dps_adr =  $this->paymentURL;

		$img_loader = apply_filters( 'filter_custom_loader_image', plugins_url( 'images/ajax-loader.gif', __FILE__ ) );

$this->log->add( 'pxpay', print_r( array( 'dps url' => esc_url( $dps_adr ), 'ajax img_loader' => $img_loader ), true ) );
 
		echo '<form action="'.esc_url( $dps_adr ).'" method="post" id="dps_payment_form">
			<input type="submit" class="button-alt button alt" id="submit_Payment_Express_payment_form" value="'.__('Pay via Payment_Express', 'woothemes').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woothemes').'</a>
			<script type="text/javascript">
				jQuery(function(){
					jQuery("body").block(
						{
							message: "<img src=\"'. $img_loader .'\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Express to make payment.', 'woothemes').'",
							overlayCSS:
							{
								background: "#fff",
								opacity: 0.6
							},
							css: {
								padding:        20,
								textAlign:      "center",
								color:          "#555",
								border:         "3px solid #aaa",
								backgroundColor:"#fff",
								cursor:         "wait",
								lineHeight:		"32px"
							}
						});
					jQuery("#submit_Payment_Express_payment_form").click();
				});
			</script>
		</form>';

	}

	/**
	 * Process the payment and return the result
	 **/
	function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		return array(
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url( true )
		); 

	}
	
	/**
	 * Process scheduled payment for a subscription.
	 */
	public function process_payment_for_scheduled_subscription( $amount, $order, $product_id ) {
		$ress = '';	/* all billingID for which transaction failed */
		global $woocommerce;
		global $wpdb;
		$amount = number_format( $amount, 2, '.', '' );
		$marchenUserName = $this->pp_user; 
		$marchentPassword = $this->pp_password; 
		$userSuppliers = get_user_meta( $order->user_id, '_supDpsBillingID', true ); 	/* This is actually a BillingId */
		$merchRef = 'Scheduled auto payment for order '.$order->id;

		$currency = get_woocommerce_currency(); 

		$currency = apply_filters( 'filter_pxpost_reccurring_currency', $currency, $order_id, $this->settings );
		
		$name = '';
$this->log->add( 'pxpost', print_r( array( 'userID and BillingId' => array( 'userid' => $order->user_id, 'BillingId' => $userSuppliers ) ), true ) );
		$cmdDoTxnTransaction = "<Txn>";
		$cmdDoTxnTransaction .= "<BillingId>". $userSuppliers ."</BillingId>";
		$cmdDoTxnTransaction .= "<PostUsername>$marchenUserName</PostUsername>"; /*Insert your DPS Username here */
		$cmdDoTxnTransaction .= "<PostPassword>$marchentPassword</PostPassword>"; /*Insert your DPS Password here */
		$cmdDoTxnTransaction .= "<Amount>$amount</Amount>";
		$cmdDoTxnTransaction .= "<InputCurrency>$currency</InputCurrency>";
		$cmdDoTxnTransaction .= "<CardHolderName>$name</CardHolderName>";
		$cmdDoTxnTransaction .= "<TxnType>Purchase</TxnType>";
		$cmdDoTxnTransaction .= "<MerchantReference>$merchRef</MerchantReference>";
		$cmdDoTxnTransaction .= "</Txn>";
	
$this->log->add( 'pxpost', print_r( array( 'XML request' => $cmdDoTxnTransaction ), true ) );

	/**
	 * 
	 *  Testing of wp_remote_post 
	 */
	$result = wp_remote_post( $this->URL, array(
		'method' => 'POST',
		'timeout' => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking' => true,
		'headers' => array(),
		'body' => $cmdDoTxnTransaction,
		'cookies' => array(),
		)
	);
	$this->log->add( 'pxpost', print_r( array( "requestProcess[851]>>>" => $result ), true ) );
	if ( is_wp_error( $result ) ) {
		$errorResponse = $result->get_error_message();
		$this->log->add( 'pxpost', print_r( array( 'pxpost request results' => $errorResponse, 'ErrorType' => 'HTTP' ), true ) );
		WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
	} else {
		$result = $result['body'];
	}
$this->log->add( 'pxpost', print_r( array( 'pxpost server url' => $this->URL ), true ) );
$this->log->add( 'pxpost', print_r( array( 'txn request' => $cmdDoTxnTransaction ), true ) );
$this->log->add( 'pxpost', print_r( array( 'Subscription renewal results' => var_export( $result, true ) ), true ) );
		$transResult = $GLOBALS['woocommerce_paymentexpress_init']::wc_pxpost_pxpost_parse_xml( $result );
		$dpsBillingID = explode( '|', $transResult );
		$dpsCardHolderResponseText = $dpsBillingID[1];
		$dpsTxnRef = $dpsBillingID[4];
		$dpsBillingID = $dpsBillingID[5];
		//if payment completed with successself::wc_pxpost_log( array( 'pxpost dps CardHolder Response Text' => $dpsCardHolderResponseText ) );
		if ( preg_match("/APPROVED/i", $dpsCardHolderResponseText ) ) {
			$order->payment_complete();
			add_post_meta( $order->id, 'dpsTxnRef', $dpsTxnRef, true );
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
			$ress = "APPROVED";
		} else {
				$ress .= '>>>'. $dpsBillingID;
		}
	}
	
	/**
	 *  Refund process function recommended by Woo
	 */
	 public function process_refund( $order_id, $amount = null, $reason = '' ) {
		global $woocommerce;
		$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
		$payment_method = $available_gateways[ 'Payment_Express' ];
		$URL = $payment_method->settings[ 'pxPost_url' ];
		$marchenUserName = $payment_method->pp_user;
		$marchentPassword = $payment_method->pp_password;	
$this->log->add( 'pxpost', print_r( array( 'pxpost user' => $marchenUserName ), true ) );
$this->log->add( 'pxpost', print_r( array( 'pxpost password' => $marchentPassword ), true ) );
		$merchRef = 'Refund Order#'. $order_id;	
		$dpsTxnRef = get_post_meta( $order_id, 'dpsTxnRef', true );
		$amount = number_format( $amount, 2, '.', '' );
$this->log->add( 'pxpost', print_r( array( 'amount refunded' => $amount ), true ) );
		$cmdDoTxnTransaction = "<Txn>";
		$cmdDoTxnTransaction .= "<PostUsername>$marchenUserName</PostUsername>"; #Insert your DPS Username here
		$cmdDoTxnTransaction .= "<PostPassword>$marchentPassword</PostPassword>"; #Insert your DPS Password here
		$cmdDoTxnTransaction .= "<Amount>$amount</Amount>";
		$cmdDoTxnTransaction .= "<TxnType>Refund</TxnType>";
		$cmdDoTxnTransaction .= "<DpsTxnRef>$dpsTxnRef</DpsTxnRef>";
		$cmdDoTxnTransaction .= "<MerchantReference>$merchRef</MerchantReference>";
		$cmdDoTxnTransaction .= "</Txn>";
	
	/**
	 * wp_remote_post testing
	 */
	$result = wp_remote_post( $URL, array(
		'method' => 'POST',
		'timeout' => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking' => true,
		'headers' => array(),
		'body' => $cmdDoTxnTransaction,
		'cookies' => array(),
		) );
		
$this->log->add( 'pxpost', print_r( array( "requestProcess[1152]>>>" => $result ), true ) );
		if ( is_wp_error( $result ) ) {
			$errorResponse = $result->get_error_message();
$this->log->add( 'pxpost', print_r( array( 'pxpost request results' => $errorResponse, 'ErrorType' => 'HTTP' ), true ) );
			wc_add_notice( __( 'Refund error:', 'woocommerce-gateway-payment-express-pxhybrid') . 'Refund failed', 'error' );
		} else {
			$result = $result['body'];
		}
$this->log->add( 'pxpost', print_r( array( 'wp_remote_post result in refund' => $result ), true ) );
		$transResult = $GLOBALS['woocommerce_paymentexpress_init']::wc_pxpost_pxpost_parse_xml( $result );
		$dpsBillingID = explode( '|', $transResult );
		$dpsCardHolderResponseText = $dpsBillingID[1];
		if ( $dpsCardHolderResponseText == "APPROVED" ) {
			$this->log->add( 'pxpost', print_r( "order #". $order_id ." is refunded.", true ) );
			return true;
		}
		$this->log->add( 'pxpost', print_r( "order #". $order_id ." refund failed.", true ) );
		return false;
	}
	

	/**
	* generalise an XML post request
	* @param string $url
	* @param string $request
	* @param bool $sslVerifyPeer whether to validate the SSL certificate
	* @return string
	* @throws DpsPxPayCurlException
	*/
	public static function xmlPostRequest($url, $request, $sslVerifyPeer = true) {
		// execute the request, and retrieve the response
		$response = wp_remote_post($url, array(
			'user-agent'	=> 'DPS PxPay 1.94',
			'sslverify'		=> $sslVerifyPeer,
			'timeout'		=> 60,
			'headers'		=> array(
									'Content-Type'		=> 'text/xml; charset=utf-8',
							   ),
			'body'			=> $request,
		));

		if (is_wp_error($response)) {
			throw new DpsPxPayCurlException($response->get_error_message());
		}

		return $response['body'];
	}
	
	/**
	* autoload classes as/when needed
	*
	* @param string $class_name name of class to attempt to load
	*/
	public static function autoload($class_name) {

		static $classMap = array (
			'DpsPxPayPayment'	=> 'includes/class.DpsPxPayPayment.php',
			'DpsPxPayResult'	=> 'includes/class.DpsPxPayResult.php',
			'WC_Gateway_Payment_Express_Hybrid_Notification_Handler' => 'includes/class.DPSNotificationHandler.php', 
		);

		if (isset($classMap[$class_name])) {
			require DPSPXPAY_PLUGIN_ROOT . $classMap[$class_name];
		}
	}
}