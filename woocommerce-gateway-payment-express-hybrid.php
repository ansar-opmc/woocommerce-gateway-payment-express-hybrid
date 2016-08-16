<?php
/**
 * Plugin Name: WooCommerce Payment Express
 * Plugin URI: http://woothemes.com/products/woocommerce-gateway-payment-express-hybrid/
 * Description: Allows your customers to pay via Payment Express PxPay (Supports PxPay1 & 2). The extension also uses PxPost for recurring billing support with the WooCommerce Subscriptions extension.
 * Version: 1.0
 * Author: OPMC
 * Author URI: http://opmc.com.au/
 * Developer: OPMC
 * Developer URI: http://opmc.com.au/
 * Requires at least: 3.9.1
 * Tested up to: 4.4.2
 *
 * Text Domain: woocommerce-gateway-payment-express-hybrid
 * Domain Path: /lang/
 */
if ( ! defined( 'ABSPATH' ) ) {exit;} /* Exit if accessed directly */

/**
/**
 *  Define all constant values
 */
define('DPSPXPAY_PLUGIN_ROOT', dirname(__FILE__) . '/');

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	if ( ! class_exists( 'Woocommerce_paymentexpress_init' ) ) {
		
		/**
		 * Localisation
		 **/
		load_plugin_textdomain( 'woocommerce-gateway-payment-express-hybrid', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		

		final class Woocommerce_paymentexpress_init {
			
			private static $instance = null;
			public static function initialize() {
				if ( is_null( self::$instance ) ){
					self::$instance = new self();
				}

				return self::$instance;
			}
			
			public function __construct() {
				
				// called after all plugins have loaded
				add_action( 'plugins_loaded', 				 array( $this, 'plugins_loaded' ) );
				/* This Method is called for fist payment and uses pxpay */
				add_action( 'wp_ajax_requestProcess', 		 array( $this, 'wc_pxhybrid_requestProcess_callback' ) );
				add_action( 'wp_ajax_nopriv_requestProcess', array( $this, 'wc_pxhybrid_requestProcess_callback' ) );
				/* This Method is called for fist payment and uses pxpay */
				add_action( 'wp_ajax_refundProcess', 		 array( $this, 'wc_pxhybrid_refundProcess_callback' ) );
				add_action( 'wp_ajax_nopriv_refundProcess',  array( $this, 'wc_pxhybrid_refundProcess_callback' ) );
				add_action( 'admin_enqueue_scripts', 		 array( $this, 'wc_pxhybrid_register_plugin_scripts_and_styles' ) );

			}
		
			/**
			 * Take care of anything that needs all plugins to be loaded
			 */
			public function plugins_loaded() {

				if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
					return;
				}

				/**
				 * Add the gateway to WooCommerce
				 */
				require_once( plugin_basename( 'class-woocommerce-gateway-payment-express-hybrid.php' ) );
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_payment_express_gateway') );
				/**
				 * Customizing the orders page in woocommerce admin panel
				 */
				add_filter( 'woocommerce_admin_order_actions', array( $this, 'wc_actions_column_refund_button_function' ), 20, 2 );

			}
			
			public function add_payment_express_gateway( $methods ) {

				$methods[] = 'WC_Gateway_Payment_Express_Hybrid'; 
				return $methods;
			}
			
			/**
			 *  Method to handle AJAX calls 
			 *  This Method is called for fist payment and uses pxpay
			 */
			public function wc_pxhybrid_requestProcess_callback() {
				$ress = '';	//all billingID for which transaction failed
				$amount = null;
				global $current_user;
				global $woocommerce;
				global $wpdb;
				wp_get_current_user();
				$userName = $current_user->user_login;
				$order_id = $_POST['orderNo'];
				$order                = wc_get_order( $order_id );
			//	$order = new WC_Order( $order_id );
				$allItems = $order->get_items();
				$billingDetailsSaved = $_POST['billingDetailsSaved'];
				$overwriteCreditDetails = $_POST['overwriteCreditDetails'];
				/* foreach ( $allItems as $eItem ) {
					$amount += $eItem['line_total'];
				} */
				$amount = $order->order_total;
				$supplier_items_details = array();
				$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
				$payment_method = $available_gateways[ 'Payment_Express' ];
				$URL = $payment_method->settings[ 'pxPay_url' ];
				$supplier_items_details[0]['total_amount'] =  $amount;
				$supplier_items_details[0]['pxuser'] = $payment_method->pp_user;
				$supplier_items_details[0]['pxpass'] = $payment_method->pp_password;
				$supplier_items_details = apply_filters( 'supplier_items_details_filter', $supplier_items_details, $order_id);
				/**
				 *  In general, $supplier_items_details will contain details for only one supplier, unless this array is changed using 
				 *  the filter 'supplier_items_details' applied above.
				 */
				foreach($supplier_items_details as $supplier_id => $supplier_det){
					$amount = number_format( $supplier_det['total_amount'], 2, '.', '' );
					$marchenUserName = $supplier_det['pxuser'];
					$marchentPassword = $supplier_det['pxpass'];
					$supports_subscriptions = in_array( 'subscriptions', $payment_method->supports );
					$userSuppliers = '';
					$name = $_POST['name'];
					$cmdDoTxnTransaction = "<Txn>";
					if ( $billingDetailsSaved == "YES" && $overwriteCreditDetails == "NO" ) {
						$userSuppliers = get_user_meta( $current_user->ID, '_supplierAndDpsBillingID', true );
						$cmdDoTxnTransaction .= "<BillingId>". $userSuppliers ."</BillingId>";
					} else {
						$ccnum = $_POST['ccnum'];
						$ccmm = $_POST['ccmm'];
						$ccyy = $_POST['ccyy'];
						$cvcnum = $_POST['cvcnum'];
						$cmdDoTxnTransaction .= "<CardNumber>$ccnum</CardNumber>";
						$cmdDoTxnTransaction .= "<DateExpiry>$ccmm$ccyy</DateExpiry>";
						$cmdDoTxnTransaction .= "<Cvc2>$cvcnum</Cvc2>";
						$cmdDoTxnTransaction .= "<Cvc2Presence>1</Cvc2Presence>";
					}
					$merchRef = $_POST['merchRef'];
					$currency = get_woocommerce_currency();
					
					$cmdDoTxnTransaction .= "<PostUsername>$marchenUserName</PostUsername>"; /*Insert your DPS Username here */
					$cmdDoTxnTransaction .= "<PostPassword>$marchentPassword</PostPassword>"; /*Insert your DPS Password here */
					$cmdDoTxnTransaction .= "<Amount>$amount</Amount>";
					$cmdDoTxnTransaction .= "<InputCurrency>$currency</InputCurrency>";
					$cmdDoTxnTransaction .= "<CardHolderName>$name</CardHolderName>";
					$cmdDoTxnTransaction .= "<TxnType>Purchase</TxnType>";
					$cmdDoTxnTransaction .= "<MerchantReference>$merchRef</MerchantReference>";
					if ( isset( $current_user->ID ) && ( $_POST['saveCreditDetails'] == "YES" || ( $supports_subscriptions && $billingDetailsSaved != "YES" ) ) ) {
						$cmdDoTxnTransaction .= "<EnableAddBillCard>1</EnableAddBillCard>";
						$cmdDoTxnTransaction .= "<BillingId>00". $userSuppliers . time().$current_user->ID ."</BillingId>";
					}
					if ( isset($current_user->ID) && $overwriteCreditDetails == "YES"){
						$cmdDoTxnTransaction .= "<EnableAddBillCard>1</EnableAddBillCard>";
						$cmdDoTxnTransaction .= "<BillingId>00". $userSuppliers . time().$current_user->ID ."</BillingId>";
					}
					$cmdDoTxnTransaction .= "</Txn>";
			/* self::wc_pxhybrid_log( array( 'px post url' => $URL ) ); */
			/**
			 *  Testing of wp_remote_post
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
						)
					);
self::wc_pxhybrid_log( array( "requestProcess[154]>>>" => $result ) );
					if ( is_wp_error( $result ) ) {
						$errorResponse = $result->get_error_message();
						self::wc_pxhybrid_log( array( 'pxpost request results' => $errorResponse, 'ErrorType' => 'HTTP' ) );
						return $errorResponse;
					} else {
						$result = $result['body'];
					}
/* self::wc_pxhybrid_log( array( 'pxpost request results' => $result ) ); */
					$transResult = $this->wc_pxpost_pxpost_parse_xml( $result );					
self::wc_pxhybrid_log( array( 'pxpost result returned by wc_pxpost_pxpost_parse_xml' => $transResult ) );
					$dpsBillingID = explode( '|', $transResult );
					$dpsCardHolderResponseText = $dpsBillingID[1];
					$dpsTxnRef = $dpsBillingID[4];
					$dpsBillingID = $dpsBillingID[5];
				
					/*new supplier details being saved */
					if ( isset( $current_user->ID ) && $_POST['saveCreditDetails'] == "YES" && $dpsBillingID != '0' ) {
						$userSuppliers = get_user_meta( $current_user->ID, '_supplierAndDpsBillingID', true );
						delete_user_meta( $current_user->ID, '_supplierAndDpsBillingID', $userSuppliers );
						add_user_meta( $current_user->ID, '_supplierAndDpsBillingID', $dpsBillingID, true );
					}
					/*previously saved supplier details being updated */
					if ( isset( $current_user->ID ) && $overwriteCreditDetails == "YES" && $dpsBillingID != '0'){
						$userSuppliers = get_user_meta( $current_user->ID, '_supplierAndDpsBillingID', true );
						$wpdb->update( $wpdb->prefix .'usermeta', 
												array( 'meta_value' => $dpsBillingID ), 
												array( 'user_id' => $current_user->ID, 'meta_key' => '_supplierAndDpsBillingID', 'meta_value' => $userSuppliers ) );
					}
				}
				
					/*if payment is not completed with success */
					if ( preg_match("/APPROVED/i", $dpsCardHolderResponseText ) ) {
						$order->payment_complete();
						add_post_meta( $order_id, 'dpsTxnRef', $dpsTxnRef, true );
						$ress = "APPROVED";
					} else {
					
						/* $ress .= '>>>'. $dpsBillingID; */
						$ress .= $dpsCardHolderResponseText;
					}
			self::wc_pxhybrid_log( array( 'pxpost dps CardHolder Response Text' => $dpsCardHolderResponseText ) );
				$ress = apply_filters( 'final_dps_response_result', $ress, $dpsCardHolderResponseText, $order_id);
				echo $ress;
				die;
			}
			
			/**
			 *  processing the payment REFUNDS
			 */
			public function wc_pxhybrid_refundProcess_callback(){
				global $woocommerce, $wpdb;
				$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
				$payment_method = $available_gateways[ 'Payment_Express' ];
				$URL = $payment_method->settings[ 'pxPost_url' ];
				$marchenUserName = $payment_method->pp_user;
				$marchentPassword = $payment_method->pp_password;
				$order_id = $_GET['order_id'];
				$merchRef = 'Refund Order#'. $order_id;	
				$dpsTxnRef = get_post_meta( $order_id, 'dpsTxnRef', true );
				$amount = get_post_meta( $order_id, '_order_total', true );
				$amount = number_format( $amount, 2, '.', '' );
				$cmdDoTxnTransaction = "<Txn>";
				$cmdDoTxnTransaction .= "<PostUsername>$marchenUserName</PostUsername>"; #Insert your DPS Username here
				$cmdDoTxnTransaction .= "<PostPassword>$marchentPassword</PostPassword>"; #Insert your DPS Password here
				$cmdDoTxnTransaction .= "<Amount>$amount</Amount>";
				$cmdDoTxnTransaction .= "<TxnType>Refund</TxnType>";
				$cmdDoTxnTransaction .= "<DpsTxnRef>$dpsTxnRef</DpsTxnRef>";
				$cmdDoTxnTransaction .= "<MerchantReference>$merchRef</MerchantReference>";
				$cmdDoTxnTransaction .= "</Txn>";
self::wc_pxhybrid_log( array( "Data sent to pxpost for refund >>>" => $cmdDoTxnTransaction ) );
			/**
			 *   wp_remote_post
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
			self::wc_pxhybrid_log( array( "Raw results from pxpost refund >>>" => $result ) );
				if ( is_wp_error( $result ) ) {
					$errorResponse = $result->get_error_message();
					self::wc_pxhybrid_log( array( 'pxhybrid request results' => $errorResponse, 'ErrorType' => 'HTTP' ) );
					wc_add_notice( __( 'Refund error:', 'woocommerce-gateway-payment-express-hybrid') . 'Refund failed', 'error' );
				} else {
					$result = $result['body'];
				}
			self::wc_pxhybrid_log( array( 'wp_remote_post result in refund' => $result ) );
				$transResult = $this->wc_pxpost_pxpost_parse_xml( $result );
				$dpsBillingID = explode( '|', $transResult );
				$dpsCardHolderResponseText = $dpsBillingID[1];
				if ( $dpsCardHolderResponseText == "APPROVED" ) {
					/*set order status as refunded */
					$order = wc_get_order( $order_id );
					$order->update_status( 'refunded' );
					wp_redirect( site_url( '/wp-admin/' ) . '/edit.php?post_type=shop_order' );
					die;
				}
				wp_redirect( site_url( '/wp-admin/' ) . '/edit.php?post_type=shop_order' );
				die;
			}
			
			/**
			 * Parse the data to xml format
			 *
			 * @param  string $data The text to display in the notice.
			 */
			public function wc_pxpost_pxpost_parse_xml( $data ) {
				$xml_parser = xml_parser_create();
				xml_parse_into_struct( $xml_parser, $data, $vals, $index );
				xml_parser_free( $xml_parser );
				$params = array();
				$level = array();
				foreach ( $vals as $xml_elem ) {
					if ( $xml_elem['type'] == 'open' ) {
						if ( array_key_exists( 'attributes', $xml_elem ) ) {
							list( $level[$xml_elem['level']], $extra ) = array_values( $xml_elem['attributes'] );
						} else {
							$level[$xml_elem['level']] = $xml_elem['tag'];
						}
					}
					if ( $xml_elem['type'] == 'complete' ) {
						$start_level = 1;
						$php_stmt = '$params';
						while ( $start_level < $xml_elem['level'] ) {
							$php_stmt .= '[$level['.$start_level.']]';
							$start_level++;
						}
						if ( array_key_exists( 'value', $xml_elem ) ) {
							$php_stmt .= '[$xml_elem[\'tag\']] = $xml_elem[\'value\'];';
							eval( $php_stmt );
						}
					}
				}
				$success = $params['TXN']['SUCCESS'];
				$MerchantReference = $params['TXN'][$success]['MERCHANTREFERENCE'];
				$CardHolderName = $params['TXN'][$success]['CARDHOLDERNAME'];
				$AuthCode = $params['TXN'][$success]['AUTHCODE'];
				$Amount = $params['TXN'][$success]['AMOUNT'];
				$CurrencyName = $params['TXN'][$success]['CURRENCYNAME'];
				$TxnType = $params['TXN'][$success]['TXNTYPE'];
				$CardNumber = $params['TXN'][$success]['CARDNUMBER'];
				$DateExpiry = $params['TXN'][$success]['DATEEXPIRY'];
				$CardHolderResponseText = $params['TXN'][$success]['CARDHOLDERRESPONSETEXT'];
				$CardHolderResponseDescription = $params['TXN'][$success]['CARDHOLDERRESPONSEDESCRIPTION'];
				$MerchantResponseText = $params['TXN'][$success]['MERCHANTRESPONSETEXT'];
				$DPSTxnRef = $params['TXN'][$success]['DPSTXNREF'];
				if( isset( $params['TXN'][$success]['BILLINGID'] ) ){
					$DPSBillingID = $params['TXN'][$success]['BILLINGID'];
				} else {
					$DPSBillingID = '0';
				}
				$html  = $AuthCode . "|";	/*AuthCode */
				$html .= $CardHolderResponseText . "|";
				$html .= $CardHolderResponseDescription . "|";
				$html .= $MerchantResponseText . "|";
				$html .= $DPSTxnRef . "|";
				$html .= $DPSBillingID . "|";
				return $html;    
			}
			
			/**
			 *  Adding new action button to the Actions column for refunding
			 */
			public function wc_actions_column_refund_button_function( $actions, $the_order ) {
				global $post, $woocommerce, $the_order;
				if ( ! ( in_array( $the_order->status, array( 'refunded' ) ) ) and $the_order->payment_method_title == 'Payment Express' ){
					$actions['refund'] = array(
						'url' 		=> wp_nonce_url( admin_url( 'admin-ajax.php?action=refundProcess&order_id='. $post->ID ), 'woocommerce-mark-order-complete' ),
						'name' 		=> __( 'Refund', 'woocommerce-gateway-payment-express-hybrid' ),
						'action' 	=> "refund post-". $post->ID,
					);
				}
				return $actions;
			}
			
			/**
			 *   Register style sheet.
			 */
			public function wc_pxhybrid_register_plugin_scripts_and_styles() {
				wp_register_style( 'woocommerce-gateway-payment-express-hybrid-css', plugins_url( 'css/style.css' , __FILE__ ) );
				wp_enqueue_style( 'woocommerce-gateway-payment-express-hybrid-css' );
				wp_register_script( 'woocommerce-gateway-payment-express-hybrid-js', plugins_url( 'js/ila-oow.js' , __FILE__ ) );
				wp_enqueue_script( 'woocommerce-gateway-payment-express-hybrid-js' );
			}
			
			public static function wc_pxhybrid_log( $message ) {
				$thislog = new WC_Logger();
				$thislog->add( 'pxhybrid', print_r( $message, true ) );
			}
		}

		$GLOBALS['woocommerce_paymentexpress_init'] = Woocommerce_paymentexpress_init::initialize();

	}
	
}