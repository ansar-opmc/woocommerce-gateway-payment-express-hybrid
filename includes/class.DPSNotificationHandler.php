<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

spl_autoload_register( 'WC_Gateway_Payment_Express_Hybrid_Notification_Handler::autoload' );

/**
 * Handles notifications sent back from DPS Payment Express
 */

class WC_Gateway_Payment_Express_Hybrid_Notification_Handler {


	/**
	 * Constructor.
	 */

	public function __construct( $pxpay_userid, $pxpay_key, $pxpay_url ) {

		$this->access_userid = $pxpay_userid;
		$this->access_key = $pxpay_key;
		$this->access_url = $pxpay_url;
		
		add_action( 'woocommerce_api_wc_gateway_payment_express_hybrid', array( $this, 'pxhybrid_check_dps_callback' ) );
		
		add_action( 'pxhybrid_valid_dps_callback', array($this, 'successful_request') );

		/* initiation of logging instance */
		$this->log = new WC_Logger();
		
	}


	/**
	 * Check for valid server callback	 
	 */	
	function pxhybrid_check_dps_callback() {
$this->log->add( 'pxpay', '====== callback function has been accessed' );
		if ( isset($_REQUEST["userid"]) ) :
			$uri  = explode('result=', $_SERVER['REQUEST_URI']);
			$uri1 = $uri[1];
			$uri2  = explode('&', $uri1);
			$enc_hex = $uri2[0];

			do_action("pxhybrid_valid_dps_callback", $enc_hex);
		endif;
		
	}

	function successful_request ($enc_hex) {
		$this->log->add( 'pxpay', print_r( array( 'enc_hex' => $enc_hex ), true ) );
		$this->Payment_Express_success_result($enc_hex);
	}
	
	public function Payment_Express_success_result($enc_hex) {
		global $woocommerce;
		
		if ( isset( $enc_hex ) ) {
			
			$resultReq = new DpsPxPayResult( $this->access_userid, $this->access_key, $this->access_url );
			$resultReq->result = wp_unslash( $enc_hex );
$this->log->add( 'pxpay', print_r( array( 'resultReq->result' => $resultReq->result, 'enc_hex' => $enc_hex ), true ) );
			try {
$this->log->add( 'pxpay', '========= requesting transaction result' );
				$response = $resultReq->processResult();
				
				/* $response['option1'] => Order number : 392 */
				$orderId = explode( ':', $response->option1 );
				$result_orderId = trim( $orderId[1] );
				$order = new WC_Order( (int) $result_orderId );
$this->log->add( 'pxpay', print_r( array( 'response' => $response, 'orderId' => $orderId, 'order' => $order ), true ) );

				do_action( 'dpspxpay_process_return' );

				if ( $response->isValid ) {
					if ( $response->success ) {
						$order->payment_complete();
						
						
						$userId = explode( '-', $response->billingID );
						$userId = trim( $userId[1] );
						
$this->log->add( 'pxpay', print_r( array( 'current_user->ID' => $userId, 'BillingId' => $response->billingID ), true ) );
						
						/*new supplier details (dpsbillingid) being saved */
						update_user_meta( $userId, '_supDpsBillingID', $response->billingID );
						
						wp_redirect( WC_Payment_Gateway::get_return_url( $order ) );
						exit();
					} else {
						$this->log->add( 'pxpay', sprintf( 'failed; %s', $response->statusText ) );
						$order->update_status('failed', sprintf(__('Payment %s via Payment Express.', 'woothemes'), strtolower( $response->statusText ) ) );
						wc_add_notice( sprintf(__('Payment %s via Payment Express.', 'woothemes'), strtolower( $response->statusText ) ), $notice_type = 'error' );
						
						$urlFail = $woocommerce->cart->get_checkout_url();
						wp_redirect( $urlFail );
						/* wp_redirect( WC_Payment_Gateway::get_return_url( $order ) ); */
						exit();
						
					}

				}
			}
			catch (DpsPxPayException $e) {
				$this->log->add( 'pxpay', print_r( $e->getMessage(), true ) );
				exit;
			}
		}

	}

	/**
	* autoload classes as/when needed
	*
	* @param string $class_name name of class to attempt to load
	*/
	public static function autoload($class_name) {

		static $classMap = array (
			'DpsPxPayResult'	=> 'class.DpsPxPayResult.php',
		);

		if (isset($classMap[$class_name])) {
			require DPSPXPAY_PLUGIN_ROOT . $classMap[$class_name];
		}
	}
	
}