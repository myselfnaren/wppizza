<?php
/*
Plugin Name: WPPizza Gateway - Razorpay Hosted Payment Pages
Description: Razorpay Hosted Payment Pages Gateway for WPPizza (adjust settings in WPPizza -> Gateways) - Requires WPPIZZA 3.0+
Author: narendra
Plugin URI: https://jalncer.in
Author URI: https://jlancer.in
Version: 1.0

Copyright (c) 2019, Narendra Arora
All rights reserved.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


*/
/********************************************
	 Exit if accessed directly
*********************************************/
if ( ! defined( 'ABSPATH' ) ) exit;

/********************************************
	on uninstall, remove options from table
*********************************************/
register_uninstall_hook( __FILE__, 'wppizza_gateway_razorpay_uninstall');

/*********************************************
	version number - in line with Version above
*********************************************/
define('WPPIZZA_GATEWAY_RAZORPAY_CURRENT_VERSION', '1.0' );

/*********************************************
	abspath file/dir constant to plugin -
	for convenience
*********************************************/
define('WPPIZZA_GATEWAY_RAZORPAY_PATH', __FILE__ );
define('WPPIZZA_GATEWAY_RAZORPAY_DIR', dirname(__FILE__) );
define('WPPIZZA_GATEWAY_RAZORPAY_URL', plugin_dir_url(__FILE__) );


/*******************************************************************
*
*	[load plugin for a specific major wppizza version]
*	
********************************************************************/
add_action( 'plugins_loaded', 'wppizza_gateway_razorpay_set_version', 8);

function wppizza_gateway_razorpay_set_version(){
	if (!class_exists( 'WPPIZZA' )) {return;}
	/* wppizza v.3.x */
	if( version_compare( WPPIZZA_VERSION, 	3,  '>='  )) {
		require_once('v3x.php');
	}		
}
?>