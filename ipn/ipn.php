<?php
/*********************************************************************************
 *  set headers to NOT cache requests to this page. might help on some servers
*********************************************************************************/
  header("Cache-Control: no-cache, must-revalidate"); //HTTP 1.1
  header("Pragma: no-cache"); //HTTP 1.0
  header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
/*********************************************************************************
*  debug only - if we ever need to check if ANYTHING was actually ever sent to the ipn
*  and/or checking for SERVER vars (if p24 IPs change for example)
*********************************************************************************/
//file_put_contents('../../wppizza/logs/ipn-access.log', '['.date('Y-m-d H:i:s').']: '.print_r($_POST, true) . PHP_EOL . PHP_EOL, FILE_APPEND);

/*********************************************************************************
*  stop right here when no data was received
*********************************************************************************/
	if(empty($_POST)){ exit();}

/*********************************************************************************
	skip themes stuff
*********************************************************************************/
	define('WP_USE_THEMES', false);
	
/*********************************************************************************
*	 find path to wp-load to load
*	 WordPress Environment and Template
*********************************************************************************/
	function get_wp_config_path($file){
	    $base = dirname(__FILE__);
	    $base = str_replace("\\", "/", $base);/*in case we are on windows**/
	    $chunkPath=explode("/",$base);
	    /*let's go UP the tree *****/
	    for($i=count($chunkPath)-1;$i>=0;$i--){
    		$loadPath=implode('/',$chunkPath);
    		if(file_exists($loadPath."/".$file)){
	    		return $loadPath."/".$file;
    		}
    		unset($chunkPath[$i]);
    	}
	}
	$wpLoad=get_wp_config_path('wp-load.php');
	require($wpLoad);

/************************************************************************
	instanciate gateway class and handle response
*************************************************************************/
$gw = new WPPIZZA_GATEWAY_RAZORPAY();
$gw -> gateway_handle_response($_POST, $gw->gatewayOptions);

exit();
?>