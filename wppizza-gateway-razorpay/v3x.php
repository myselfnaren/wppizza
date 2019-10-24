<?php if ( ! defined( 'ABSPATH' ) ) exit; /* Exit if accessed directly */ ?>
<?php
include_once('razorpay-sdk/Razorpay.php');
use Razorpay\Api\Api;
/*********************************************
	register gateway
*********************************************/
function wppizza_register_gateway_razorpay( $gateways ) {
	$gateways[] = 'WPPIZZA_GATEWAY_RAZORPAY';/* should be equivalent to class name */
	return $gateways;
}
add_filter( 'wppizza_register_gateways', 'wppizza_register_gateway_razorpay' );

add_action('wppizza_gateways_process_transaction_razorpay',process_response,10,5);

function process_response($order_id, $order_amount, $order_currency, $order_data,  $request_vars){
	global $wpdb, $blog_id, $post;

			$request_vars = $gateway_response;
			/*************************************************
				run query, and get results
				even single order results are always arrays
				so simply use reset here
			*************************************************/
			$user_session = WPPIZZA()->session->get_userdata();
			$order_table = WPPIZZA()->db->order_table($blog_id);
			$get_order = $wpdb->get_row( "SELECT id,order_ini FROM ".$order_table." WHERE hash = '".$user_session[''.WPPIZZA_SLUG.'_hash']."' AND payment_status = 'INPROGRESS' ", ARRAY_A);
			$is_success = false;
			if(empty($gateway_response['error'])){
			$order_ini = maybe_unserialize($get_order['order_ini']);
			$order_ini['razorpay_order_id'] = $gateway_response['razorpay_order_id'];
			$data['order_ini'] = array(
				'data' =>maybe_serialize($order_ini),
				'type'=> '%s');
			$data['transaction_id'] = array('data'=>$gateway_response['razorpay_payment_id'],
			'type' => '%s');
			$data['transaction_details'] = array('data' => $gateway_response['razorpay_signature'],
			'type' => '%s');
			$data['payment_status'] = array('data' => 'COMPLETED',
			'type' => '%s');
			WPPIZZA() -> db -> update_order($blog_id,$get_order['id'],$user_session[''.WPPIZZA_SLUG.'_hash'],$data);
			$is_success = true;
			}else{
				$data['payment_status'] = array('data' => 'REJECTED',
			'type' => '%s');
			WPPIZZA() -> db -> update_order($blog_id,$get_order['id'],$user_session[''.WPPIZZA_SLUG.'_hash'],$data);
			$is_success = false;

			/*************************************************
				ORDER REJECTED / INVALID / DECLINED or similar
				according to what your gateway returns 
				['response_code'] etc is probably something different
			**************************************************/
			if ( !empty($gateway_response['error'])){
				$transaction_errors[] = array(
					'critical'=> false,// should be false
					'error_message' => $gateway_response['error']['description']
				);
			}

			/*************************************************
				ADDITIONAL CHECKS - EVEN IF verified
				Make sure amount and currency match
				and transaction has not already been processed
				by checking if transaction id was already set (if not empty)
				will add additional errors (if any)
				
				or simply use your own function returning 
				
			**************************************************/
			if ( empty($gateway_response['error']) ){
				$transaction_errors = apply_filters('wppizza_verify_amount_currency_transactionid', $transaction_errors, $order_amount, $order_currency, $request_vars['razorpay_payment_id']);
			}



			/* [response was CANCELLED via IPN] */
			if($gateway_response['response_code']=='CANCELLED'){
				$cancel = wppizza_order_cancel($order_id, $gateway_response, $order, $transaction_id);
				return;
			}	

			}
			return $is_success;
}

/**********************************************************************************
*
*
*	GATEWAY CLASS - MUST START WITH WPPIZZA_GATEWAY_
*
*
**********************************************************************************/
if (!class_exists( 'WPPIZZA_GATEWAY_RAZORPAY' )){
	class WPPIZZA_GATEWAY_RAZORPAY{


		/*****************************
			[required]
		*****************************/
		/*
			@type : string( must NOT be empty)
			@param : gateway version number
		*/
		public $gatewayVersion = WPPIZZA_GATEWAY_RAZORPAY_CURRENT_VERSION;

		/*
			@type : string (must NOT be empty)
			@param : gateway name
		*/
		public $gatewayName = 'RazorPay';

		/*
			@type : string (can be empty)
			@param : gateway descriptiosn
			additional description of gateway displayed in ADMIN area
		*/
		public $gatewayDescription = 'The easiest way to accept, process and disburse digital payments for businesses in India';

		/*
			@type : string (can be empty)
			@param : gateway additional info
			default printed under gateway options FRONTEND
			can be changed/localized/emptied in admin
		*/
		public $gatewayAdditionalInfo = '';

		/*
			@type : array
			@param : options of gateway
		*/
		public $gatewayOptions = array();


		/******************************
			[optional]
			but recommended
		******************************/
		/*
			@type : string
			@param : version number
			minimum wppizza version required.
			will be set set to currently installed wppizza version if omitted
		*/
		public $gatewayMinWppizzaVersion = '3.0';

		/*
			@type : string
			@param : 'prepay' or 'cod'.
			omit or set distinctly to "prepay" (i.e for cc's and other payment processors)
			set to 'cod' to simply have another payment option that works like the wppizza inbuilt cod payment
			(if you want that, simply set the parameters above, empty the construct and delete all methods)
		*/
		public $gatewayType = 'prepay';

		/*
			@type : bool
			@param : bool
			enable discounts on usage (rather than surcharges)
			simply omit or set to false for the typical default of enabling surcharges 
			if this gateway is being used for payment
		*/
		//public $gatewayDiscount = true;

		/******************************
			[optional]
		******************************/
		/*
			@type : url
			@param : url to small logo image next to gateway name frontend (if more than one gateway)
		*/
		public $gatewayLogo = '';

		/*
			@type : url
			@param : url to image, used as button if only this gateway enabled
		*/
		public $gatewayButton = '';
		
		
		/*
			@type : string
			@param : function name that gets executed when processing refunds at payment processor
		*/
		public $gatewayRefunds = '';
		
		const RAZORPAY_PAYMENT_ID            = 'razorpay_payment_id';
        const RAZORPAY_ORDER_ID              = 'razorpay_order_id';
        const RAZORPAY_SIGNATURE             = 'razorpay_signature';

        const INR                            = 'INR';
        const CAPTURE                        = 'capture';
        const AUTHORIZE                      = 'authorize';
		
		const DEFAULT_LABEL                  = 'Credit Card/Debit Card/NetBanking';
        
		
		
	/******************************************************************************************************************
	*
	*
	*	[construct]
	*
	*
	******************************************************************************************************************/
		public function __construct() {

			/************************
				additional description of gateway displayed in ADMIN area
			************************/
			$this -> gatewayDescription = '<span style="color:red">'.__('For additional setup information and requirements <a href="https://jlancer.in/wppizza_razorpay_gateway/" target="_blank">please see here</a>','wppizza-gateway-razorpay').'</span>';/*required*/

			/************************
				[set logo  - optional - must be in construct for concat to work for php <5.6 or so]
			************************/
			$this -> gatewayLogo = WPPIZZA_GATEWAY_RAZORPAY_URL.'images/logo.png' ;

			/************************
				[set button  - optional - must be in construct for concat to work for php <5.6 or so]
			************************/
			$this -> gatewayButton = WPPIZZA_GATEWAY_RAZORPAY_URL.'images/button.png' ;

			/************************
				[get gateway options]
			************************/
			$this -> gatewayOptions = get_option(__CLASS__, 0);

			/***************************
				enable refunds
				make sure to create the appropriate function
				dummy furtherdown
			***************************/
			//if(!empty($this -> gatewayOptions['GwRefunds'])){
			//	$this->gatewayRefunds = 'do_refund';
			//}

			/****************************
				init text domain
			*****************************/
			add_action('init', array($this, 'set_textdomain'));

		}


		function order_table($set_blog_id = false){
			global $wpdb, $blog_id;
			$order_table = $wpdb->prefix;
			if($set_blog_id && $set_blog_id != $blog_id && $set_blog_id>1){
				$order_table .= $set_blog_id.'_';
			}
			$order_table .= WPPIZZA_TABLE_ORDERS;
	
		return $order_table;
		}

	/******************************************************************************************************************
	*
	*
	*	[COMMON METHODS]
	*
	*
	******************************************************************************************************************/

		/*****************************************************
	    * load text domain on init.
	    ******************************************************/
	  	function set_textdomain(){
			   load_plugin_textdomain('wppizza-gateway-razorpay', false, dirname(plugin_basename( WPPIZZA_GATEWAY_RAZORPAY_PATH ) ) . '/lang' );
	    }

		
	/********************************************************************************************
	*
	*
	*	[METHODS REDIRECT]
	*
	*
	*********************************************************************************************/



		/******************************************************
		*
		*	[payment / redirect]
		*	method *must* be named "payment_redirect"
		*	returns appropriate redirect url depending on action set 
		*   or self posting form
		******************************************************/
		function payment_redirect($order_details){

			
			/***************************************
				adding user mapped formfields data
				only use if GwMapVars is added to
				the settings, else simply comment out/ delete
			***************************************/
			$order_details = apply_filters('wppizza_map_gateway_formfields', $order_details, $this->gatewayOptions['GwMapVars'] );
			
			/***********************************
			 * Create Order on Razorpay to use for the transaction
			 **********************************/

			$body = array();
			$body['amount'] = $order_details['ordervars']['total']['value'] * 100;
			$body['currency'] = "INR";
			$body['receipt'] = $order_details['ordervars']['order_id']['value'];
			$body['payment_capture'] = 1;
			$api = new Api($this->gatewayOptions['key_id'], $this->gatewayOptions['key_secret']);
			$order = $api->order->create($body);

			/* Set Razprpay order id */

			$order_details['ordervars']['razorpay_order_id']['value'] = $order->id;
			/*****************************************
				build redirect/post parameters
			*****************************************/
			$tx_request = $this -> map_parameters($order_details);



			//print_r($tx_request);die;
			return $tx_request;
		}

		/*****************************************************************************************************
	    * 	
	    * 		build redirect parameters 
	    * 		these are the parameters send to the gateway 
	    * 	
		*	--- ADJUST / EDIT AS APPROPRIATE ----			    
	    * 
	    ******************************************************************************************************/
	  	function map_parameters($order_details){

			/*
				[ini tx_redirect array]
			*/
			$tx_redirect = array();

			/*
				[GET or POST]
				GET will simply redirect to url set in $tx_redirect['url'], using any adding $tx_redirect['parameters'] via http_build_query, 
				POST will build a self submitting html form to the $tx_redirect['url'] set, using the same parameters
			*/
			$tx_redirect['action'] = 'POST'; /* GET will simply redirect to url set, using any adding $tx_redirect['parameters'] via http_build_query, POST will build a self submitting html form using the same parameters */

			/********************************************
				set parameters to submit to hosted payment page
				see your developers docs for your gateway as to which 
				ones are required/available
			********************************************/
			$MERCHANT_ID = $this -> gatewayOptions['key_id'] ;
			$ORDER_ID =	$order_details['ordervars']['order_id']['value'];
			$AMOUNT	=	$order_details['ordervars']['total']['value'];
			$CURRENCY = strtoupper($order_details['ordervars']['currency']['value']);
			$TEST_MODE = empty($this -> gatewayOptions['GwSandbox']) ? false : true;			
			
			/*
				[url - sandbox. or live]
			*/
			$tx_redirect['url'] = ($TEST_MODE) ? 'https://api.razorpay.com/v1/checkout/embedded' :  'https://api.razorpay.com/v1/checkout/embedded';

			/*
				check for unsupported currencies for example
				add any other checks the same way
				adding to $tx_redirect['error'] array
			*/
			if(!in_array($CURRENCY, array('ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'USD','INR'))) {
				$tx_redirect['error'][]['error_id'] = 0;
				$tx_redirect['error'][]['error_message'] = 'unsupported currency: '.$CURRENCY.'';
			}

			/*
				make up a full name if gateway allows for this
				using the parameters set in GwMapVars
			*/
			$full_name = array();
			if(!empty($order_details['customer']['razorpay_fname']['value'])){
				$full_name[]= $order_details['customer']['razorpay_fname']['value'];
			}
			if(!empty($order_details['customer']['razorpay_lname']['value'])){
				$full_name[]= $order_details['customer']['razorpay_lname']['value'];
			}
			if(!empty($order_details['customer']['razorpay_fullname']['value'])){
				$full_name[]= $order_details['customer']['razorpay_fullname']['value'];
			}


			
            $productinfo = "Order $orderId";
            $mod_version = get_plugin_data(plugin_dir_path(__FILE__) . 'wppizza-gateway-razorpay.php')['Version'];

			/********************************************
				set parameters
				see your gateway docs
				for parameters requiremnts
				below are just some dummy vars
				based on gateway_settings and order details
				
				-- adjust as required --
			********************************************/
            $tx_redirect['parameters'] = array(
            	'key_id' 		=>	$MERCHANT_ID,
            	'order_id' 			=>	$order_details['ordervars']['razorpay_order_id']['value'],
				'name'				=>  'Test',
                'description'		=>	$productinfo = "Order $orderId",
       		  	'amount'			=>	$AMOUNT,
				'currency'      	=>  $CURRENCY,
				'notes[wppizza_order_id]'        => $ORDER_ID,
				'prefill[name]'			=>  substr(implode(' ',$full_name),0,150),
				'prefill[email]'			=>  (!empty($order_details['customer']['cemail']['value']) ? substr($order_details['customer']['cemail']['value'],0,255) : ''),
				'prefill[contact]'			=>  (!empty($order_details['customer']['ctel']['value']) ? substr($order_details['customer']['ctel']['value'],0,50) : ''),
				'_[integration]'            => 'wppizza-razorpay',
                '_[integration_version]'           => $mod_version,
                '_[integration_parent_version]'    => WPPIZZA_VERSION,
                'callback_url'			=>	''.wppizza_transaction_url().'' ,
       		  	'cancel_url'			=>	''.wppizza_transaction_cancel_url().'' ,// use in case gateway simply redirects to a dedicated cancel url without using ipn notification
       		  	'test'				=>	(!empty($TEST_MODE) ? 1 : 0)
            );
			
		return $tx_redirect;
	    }



		
	/********************************************************************************************
	*
	*
	*	[METHODS PAYMENT]
	*
	*
	*********************************************************************************************/
		/**********************************************************************
		*
		*	[handle IPN responses received by gateway during/after transactions 
		*	are being executed at the hosted paymnt page]
		*	
		*	--- ADJUST / EDIT AS APPROPRIATE ----	
		*	
		**********************************************************************/
		function gateway_handle_response($gateway_response, $gateway_options){
			global $wpdb, $blog_id, $post;
			/*************************************************
				run query, and get results
				even single order results are always arrays
				so simply use reset here
			*************************************************/
			$user_session = WPPIZZA()->session->get_userdata();
			$order_table = $this->order_table($blog_id);
			$get_order = $wpdb->get_row( "SELECT id,order_ini FROM ".$order_table." WHERE hash = '".$user_session[''.WPPIZZA_SLUG.'_hash']."' AND payment_status = 'INPROGRESS' ", ARRAY_A);
			$is_success = false;
			if(empty($gateway_response['error'])){
			$order_ini = maybe_unserialize($get_order['order_ini']);
			$order_ini['razorpay_order_id'] = $gateway_response['razorpay_order_id'];
			$data['order_ini'] = array(
				'data' =>maybe_serialize($order_ini),
				'type'=> '%s');
			$data['transaction_id'] = array('data'=>$gateway_response['razorpay_payment_id'],
			'type' => '%s');
			$data['transaction_details'] = array('data' => $gateway_response['razorpay_signature'],
			'type' => '%s');
			$data['payment_status'] = array('data' => 'COMPLETED',
			'type' => '%s');
			WPPIZZA() -> db -> update_order($blog_id,$get_order['id'],$user_session[''.WPPIZZA_SLUG.'_hash'],$data);
			$is_success = true;
			
			

			}else{
				$data['payment_status'] = array('data' => 'REJECTED',
			'type' => '%s');
			WPPIZZA() -> db -> update_order($blog_id,$get_order['id'],$user_session[''.WPPIZZA_SLUG.'_hash'],$data);
			$is_success = false;

			/*************************************************
				ORDER REJECTED / INVALID / DECLINED or similar
				according to what your gateway returns 
				['response_code'] etc is probably something different
			**************************************************/
			if ( !empty($gateway_response['error'])){
				$transaction_errors[] = array(
					'critical'=> false,// should be false
					'error_message' => $gateway_response['error']['description']
				);
			}

			/*************************************************
				ADDITIONAL CHECKS - EVEN IF verified
				Make sure amount and currency match
				and transaction has not already been processed
				by checking if transaction id was already set (if not empty)
				will add additional errors (if any)
				
				or simply use your own function returning 
				
			**************************************************/
			if ( empty($gateway_response['error']) ){
				$transaction_errors = apply_filters('wppizza_verify_amount_currency_transactionid', $transaction_errors, $gateway_response['amoumt'], $gateway_response['currency'], $gateway_response['transaction_id']);
			}



			/* [response was CANCELLED via IPN] */
			if($gateway_response['response_code']=='CANCELLED'){
				$cancel = wppizza_order_cancel($gateway_response['orderId'], $gateway_response, $order, $transaction_id);
				return;
			}	

			}
			return $is_success;

		}
		
		
	/******************************************************************************************************************
	*
	*
	*	[REFUND METHOD ]
	*
	*
	******************************************************************************************************************/
//	  	function do_refund($order_id, $transaction_id, $total, $order_details){
//	       	
//	       	/*
//	       		get some gateway options. adjust as needed
//	       		use/see $order_details for all transaction values available
//	       	*/	       	
//			$MERCHANT_ID = $this -> gatewayOptions['MERCHANT_ID'] ;
//			$ACCOUNT_ID = $this -> gatewayOptions['ACCOUNT_ID'];
//			$API_KEY = $this -> gatewayOptions['API_KEY'];
//			$ORDER_ID =	$order_id;
//			$AMOUNT	=	$total;
//			$CURRENCY = strtoupper($order_details['ordervars']['currency']['value']);
//      	
//	       
//	       	/*
//	       		do the refund, return error message if unsuccessful
//	       		DUMMY EXAMPLE . this needs to be set/used as per your gateway
//	       		it will not work just using this as is
//	       		using Soap here is just a random example, refer to your gateway documentation
//	       	*/
//	       	$client = new SoapClient('some url perhaps');
//	 		$result = $client->credit(array('MERCHANT_ID' => ''.$MERCHANT_ID.'', 'TRANSACTION_ID' => ''.$transaction_id.'', 'AMOUNT' => $AMOUNT));
//	 		
//	 		
//			/*no errors, simply return true else retunrn $refund['error'] **/
//			if($result->creditResult == true){
//				$refund = true;		
//			}else{			
//				if($result->response != "-1"){
//					$errorDetails=$client->getError($MERCHANT_ID, $result->response);
//				}
//				else{
//					$errorDetails='An unknown error occured';
//				}
//				
//				$refund['error'] = $errorDetails;				
//			}
//	       	
//	      return $refund;
//	    }		
		
	/********************************************************************************************
	*
	*
	*	[METHODS INSTALL/ADMIN]
	*
	*
	*********************************************************************************************/
		/********************************************************************************
		*
		*	[settings, additional settings required by your gateway  ]
		*	see examples below for different types (checkbox radio etc etc)
		*	these will be added to the always available options of name , info etc
		*	options will be displayed by order set here	
		*
		*	@return array
		*	method name must be "gateway_settings"
		*
		*
		*			generally the following applies:
		*				
		*				- key => a unique option key that applies to this gateway
		*				
		*				
		*				- value => depends on type, see examples below. 
		*				
		*				
		*				- options => depends on type, see examples below
		*				
		*				
		*				- validateCallback => the fucntion call for validating the input on save
		*				you can choose from many already globally available  wppizza validation functions 
		*				(for a full list simply open/see wppizza/includes/global.validation.functions.inc.php )
		*				alternatively you can add your own function like so 
		*		
		*				'validateCallback'=>array(
		*					array(__CLASS__,'my_function_name'), 
		*					array($parameter_1, $parameter_2, $parameter_3)
		*				)
		*		
		*				and then create/add the following to your gateway class
		*				
		*				function my_function_name($parameters){
		*					== do your validation and return what needs returning ==
		*				}
		*			
		*				
		*				- label => label before option
		*				
		*				- descr => description after option				
		*		
		*				- placeholder => false or some text for input fields	
		*
		*				- wpml => does the option need to be translatable via wpml				
		*
		*		
		********************************************************************************/
		function gateway_settings($gateway_ident, $gateway_options, $gateway_options_name){
			/*
				initialize array
			*/

			$gatewaySettings[]=array(
				'key'=>'key_id',
				'value'=>empty($this->gatewayOptions['key_id']) ? '' : $this->gatewayOptions['key_id'],
				'type'=>'text_size_40',
				'options'=>false,
				'validateCallback'=>'wppizza_validate_string',
				'label'=>__('Key ID','wppizza-gateway-razorpay'),
				'descr'=>__('Your Key ID','wppizza-gateway-razorpay') . ' <span style="color:red">['.__('required','wppizza-gateway-razorpay').']</span>',
				'placeholder'=>__('Key ID','wppizza-gateway-razorpay'),
				'wpml'=>false
			);


			$gatewaySettings[]=array(
				'key'=>'key_secret',
				'value'=>empty($this->gatewayOptions['key_secret']) ? '' : $this->gatewayOptions['key_secret'],
				'type'=>'text_size_40',
				'options'=>false,
				'validateCallback'=>'wppizza_validate_string',
				'label'=>__('Api Secret','wppizza-gateway-razorpay'),
				'descr'=>__('Your Api Secret','wppizza-gateway-razorpay') . ' <span style="color:red">['.__('required','wppizza-gateway-razorpay').']</span>',
				'placeholder'=>__('Api Secret','wppizza-gateway-razorpay'),
				'wpml'=>false
			);

			/* boolean - checkbox allow for test transactions */
			$gatewaySettings[]=array(/**test or live**/
				'key'=>'GwSandbox',
				'value'=>true,
				'type'=>'checkbox',
				'options'=>false,
				'validateCallback'=>'wppizza_validate_boolean',
				'label'=>__('Test Mode ? (Y/N)','wppizza-gateway-razorpay'),
				'descr'=>__('check to use test mode','wppizza-gateway-razorpay'),
				'placeholder'=>false,
				'selected'=>checked(!empty($this->gatewayOptions['GwSandbox']),true,false),
				'wpml'=>false
			);


			/* multiple dropdowns to associate wppizza order formfields with gateway formfields if required */
			$gatewaySettings[]=array(
				'key'=>'payment_action',
				'value'=>empty($this->gatewayOptions['payment_action']) ? '' : $this->gatewayOptions['payment_action'],
				'type'=>'select',
				'options' => array(
					'authorize' => 'Authorize',
					'capture'   => 'Authorize and Capture'
				),
				'validateCallback'=>'wppizza_validate_string',
				'label'=>__('Payment Action','wppizza-gateway-razorpay'),
				'descr'=>__('Payment action on order compelete','wppizza-gateway-razorpay'),
				'placeholder'=>false,
				'wpml'=>false
			);
			
			
/*==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==*
			
			
	################# some other examples of various output options ################
			
			
*==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==**==*==*/

//			/* 
//				simple textbox 
//			*/
//			$gatewaySettings[]=array(
//				'key'=>'TestSecretKey',
//				'value'=>empty($this->gatewayOptions['TestSecretKey']) ? '' : $this->gatewayOptions['TestSecretKey'],
//				'type'=>'text',
//				'options'=>false,
//				'validateCallback'=>'wppizza_validate_string',
//				'label'=>__('Test Secret Key [required]','wppizza-gateway-dummypayhpp'),
//				'descr'=>'',
//				'placeholder'=>__('Test Secret Key: sk_test_... ','wppizza-gateway-dummypayhpp'),
//				'wpml'=>false
//			);
//
//
//			/* 
//				simple textbox setting width of text to 60
//			*/
//			$gatewaySettings[]=array(
//				'key'=>'TestNotsoSecretKey',
//				'value'=>empty($this->gatewayOptions['TestNotsoSecretKey']) ? '' : $this->gatewayOptions['TestNotsoSecretKey'],
//				'type'=>'text_size_60',
//				'options'=>false,
//				'validateCallback'=>'wppizza_validate_string',
//				'label'=>__('Test almost Secret Key [required]','wppizza-gateway-dummypayhpp'),
//				'descr'=>'',
//				'placeholder'=>__('Test almost Secret Key: sk_test_... ','wppizza-gateway-dummypayhpp'),
//				'wpml'=>false
//			);
//
//
//
//			
//			/* 
//				dropdown 
//			*/
//				$options = array();
//				$options['1'] = __('Overlay','wppizza-gateway-dummypayhpp');
//				$options['3'] = __('Full Screen','wppizza-gateway-dummypayhpp');
//				
//				$gatewaySettings[]=array(
//					'key'=>'GwWindowType',
//					'value'=>empty($this->gatewayOptions['GwWindowType']) ? 1 : $this->gatewayOptions['GwWindowType'],
//					'type'=>'select',
//					'options'=>$options,
//					'validateCallback'=>'wppizza_validate_int_only',
//					'label'=>__('Integration','wppizza-gateway-dummypayhpp'),
//					'descr'=>__('Select how you want to integrate the payment page','wppizza-gateway-dummypayhpp'),
//					'placeholder'=>false,
//					'wpml'=>false
//				);
//
//			/* 
//				multiselect 
//			*/
//				$ptype = array();
//				$ptype[1]='Dankort/VISA Dankort [Payment cards]';
//				$ptype[2]='eDankort [Payment cards]';
//				$ptype[3]='VISA / VISA Electron [Payment cards]';
//				$ptype[4]='Mastercard [Payment cards]';
//
//				$gatewaySettings[]=array(
//					'key'=>'GwPaymentType',
//					'value'=>empty($this->gatewayOptions['GwPaymentType']) ? array(1,3,4) : $this->gatewayOptions['GwPaymentType'],
//					'type'=>'selectmulti',
//					'options'=>$ptype,
//					'validateCallback'=>'',
//					'label'=>__('Allowed Payment Types','wppizza-gateway-dummypayhpp'),
//					'descr'=>__('Ctr+click to select multiple','wppizza-gateway-dummypayhpp'),
//					'placeholder'=>false
//				);			
//
//			/* 
//				multiselect - alternative setting array directly
//			*/
//			$gatewaySettings[]=array(
//				'key'=>'GwPaymentType',
//				'value'=>empty($this->gatewayOptions['GwPaymentType']) ? array('cards') : $this->gatewayOptions['GwPaymentType'],
//				'type'=>'selectmulti',
//				'options'=> array('cards' => 'Credit Cards', 'paypal' => 'Paypal', 'sofort' => 'Sofort', 'giropay' => 'Giropay', 'elv' => 'ELV', 'ideal' => 'Ideal'),
//				'validateCallback'=>'',
//				'label'=>__('Allowed Payment Types','wppizza-gateway-realex'),
//				'descr'=>''.__('Set payment types you want to offer your customers.','wppizza-gateway-dummypayhpp').'<br /><b>'.__('Payment types selected must be enabled in your account.','wppizza-gateway-dummypayhpp').'</b><br />'.__('Ctr+click to select multiple','wppizza-gateway-realex').'',
//				'placeholder'=>false,
//				'wpml'=>false
//			);
//
//			/* 
//				checkbox 
//			*/
//			$gatewaySettings[]=array(
//				'key'=>'GwCheckbox',
//				'value'=>true,
//				'type'=>'checkbox',
//				'options'=>false,
//				'validateCallback'=>'wppizza_validate_boolean',
//				'label'=>__('some label','wppizza-gateway-dummypayhpp'),
//				'descr'=>__('check to use checkbox mode','wppizza-gateway-dummypayhpp'),
//				'placeholder'=>false,
//				'selected'=>checked(!empty($this->gatewayOptions['GwCheckbox']),true,false),
//				'wpml'=>false
//			);
//
//
//			/* 
//				multiple checkboxes using "gateway_payment_types" helper function for convenience
//			*/
//			$gatewaySettings[]=array(
//				'key'=>'PaymentsAccepted',
//				'value'=>empty($this->gatewayOptions['PaymentsAccepted']) ? $this->gateway_payment_types(true) : $this->gatewayOptions['PaymentsAccepted'],
//				'type'=>'checkboxmulti',
//				'options'=>$this->gateway_payment_types(),
//				'validateCallback'=>'wppizza_validate_array',
//				'label'=>__('Accepted Payments','wppizza-gateway-dummypayhpp'),
//				'descr'=>'<span style="color:red">'.__('some text in red','wppizza-gateway-dummypayhpp').'</span>',
//				'placeholder'=>false,
//				'wpml'=>false
//			);
//
//

		return $gatewaySettings;
		}
		
		/*************************************************************
		*
		*	[helper available payment types for above multiple checkboxes example]
		*
		*************************************************************/
		function gateway_payment_types($default=false){
			$gatewayPaymentTypes['VISA']='VISA';
			$gatewayPaymentTypes['MASTERCARD']='MASTERCARD';
			$gatewayPaymentTypes['MAESTRO']='MAESTRO';
			if(!$default){
				$gatewayPaymentTypes['IDEAL']='IDEAL';
				$gatewayPaymentTypes['MINITIX']='MINITIX';
			}
			return $gatewayPaymentTypes;
		}		
		
		
	}
}
?>