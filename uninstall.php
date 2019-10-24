<?php
if(!defined('WP_UNINSTALL_PLUGIN') ){
    exit();
}
/****************************************
*
*	[set as appropriate]
*
****************************************/

	$optionSuffix='dummypayhpp';//set to be the same as $this->gatewayIdent which is the last part of the class of WPPIZZA_GATEWAY_DUMMYPAYHPP (lowercase)

/****************************************
*
*	------ stop editing -------
*
****************************************/

/*delete options*/
if ( is_multisite() ) {
	global $wpdb;
	$blogs = $wpdb->get_results("SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A);
	if ($blogs) {
		foreach($blogs as $blog) {
			switch_to_blog($blog['blog_id']);
			delete_option('wppizza_gateway_'.$optionSuffix.'');
		}
		restore_current_blog();
	}
}else{
	delete_option('wppizza_gateway_'.$optionSuffix.'');
}
?>