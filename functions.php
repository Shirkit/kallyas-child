<?php
// Replacing POS edit order page: it's done via MU-PLUGINS
?>
<?php

/* ======================================================== 
 * Resize author's avatar
 * ======================================================== */
add_filter("zn_author_bio_avatar_size", "change_author_avatar_size");
function change_author_avatar_size() {
	return 70;
}

/* ======================================================== 
 * Allow custom MIME types
 * ======================================================== */
add_filter( 'upload_mimes', 'my_myme_types', 1, 1 );
function my_myme_types( $mime_types ) {
  $mime_types['csv'] = 'text/csv';     // Adding .csv extension
  
  return $mime_types;
}

/* ======================================================== 
 * Reorder Checkout fields
 * ======================================================== */
add_filter("woocommerce_checkout_fields", "orquidario_override_checkout_fields", 1);
function orquidario_override_checkout_fields($fields) {
    $fields['billing']['billing_first_name']['priority'] = 10;
    $fields['billing']['billing_last_name']['priority'] = 20;
    $fields['billing']['billing_company']['priority'] = 30;
    $fields['billing']['billing_country']['priority'] = 40;
	foreach ($fields['billing']['billing_country']['class'] as &$str) {
    	$str = str_replace('form-row-wide', 'form-row-first', $str);
	}
	
    $fields['billing']['billing_state']['priority'] = 50;
	foreach ($fields['billing']['billing_state']['class'] as &$str) {
    	$str = str_replace('form-row-wide', 'form-row-last', $str);
	}
	
    $fields['billing']['billing_address_1']['priority'] = 60;
	$fields['billing']['billing_address_1']['placeholder'] = '';
	foreach ($fields['billing']['billing_address_1']['class'] as &$str) {
    	$str = str_replace('form-row-last', 'form-row-wide', $str);
		$str = str_replace('form-row-first', 'form-row-wide', $str);
	}
	
	$fields['billing']['billing_number']['priority'] = 61;
	$fields['billing']['billing_address_2']['priority'] = 62;
	$fields['billing']['billing_address_2']['placeholder'] = '';
	
	$fields['billing']['billing_city']['priority'] = 70;
	
	$fields['billing']['billing_neighborhood']['priority'] = 80;
	foreach ($fields['billing']['billing_neighborhood']['class'] as &$str) {
    	$str = str_replace('form-row-last', 'form-row-first', $str);
	}
	
    $fields['billing']['billing_postcode']['priority'] = 90;
    $fields['billing']['billing_email']['priority'] = 100;
    $fields['billing']['billing_phone']['priority'] = 110;
	
	
	// Shipping
	
	$fields['shipping']['shipping_first_name']['priority'] = 10;
    $fields['shipping']['shipping_last_name']['priority'] = 20;
    $fields['shipping']['shipping_company']['priority'] = 30;
    $fields['shipping']['shipping_country']['priority'] = 40;
	foreach ($fields['shipping']['shipping_country']['class'] as &$str) {
    	$str = str_replace('form-row-wide', 'form-row-first', $str);
	}
	
    $fields['shipping']['shipping_state']['priority'] = 50;
	foreach ($fields['shipping']['shipping_state']['class'] as &$str) {
    	$str = str_replace('form-row-wide', 'form-row-last', $str);
	}
	
    $fields['shipping']['shipping_address_1']['priority'] = 60;
	$fields['shipping']['shipping_address_1']['placeholder'] = '';
	foreach ($fields['shipping']['shipping_address_1']['class'] as &$str) {
    	$str = str_replace('form-row-last', 'form-row-wide', $str);
		$str = str_replace('form-row-first', 'form-row-wide', $str);
	}
	
	$fields['shipping']['shipping_number']['priority'] = 61;
	$fields['shipping']['shipping_address_2']['priority'] = 62;
	$fields['shipping']['shipping_address_2']['placeholder'] = '';
	
	$fields['shipping']['shipping_city']['priority'] = 70;
	
	$fields['shipping']['shipping_neighborhood']['priority'] = 80;
	foreach ($fields['shipping']['shipping_neighborhood']['class'] as &$str) {
    	$str = str_replace('form-row-last', 'form-row-first', $str);
	}
	
    $fields['shipping']['shipping_postcode']['priority'] = 90;
	
	if (is_admin()) {
		try {
			$screen = get_current_screen();
			if ($screen->id == 'pos_page') {
				$fields['billing']['billing_number']['priority'] = 65;
				$fields['billing']['billing_address_2']['priority'] = 61;
				
				foreach ($fields['billing']['billing_address_2']['class'] as &$str) {
					$str = str_replace('form-row-last', 'form-row-wide', $str);
					$str = str_replace('form-row-first', 'form-row-wide', $str);
				}
				
				foreach ($fields['billing']['billing_email']['class'] as &$str) {
					$str = str_replace('form-row-last', 'form-row-wide', $str);
					$str = str_replace('form-row-first', 'form-row-wide', $str);
				}
				
				foreach ($fields['billing']['billing_cellphone']['class'] as &$str) {
					$str = str_replace('form-row-last', 'form-row-first', $str);
				}
				
				foreach ($fields['billing']['billing_phone']['class'] as &$str) {
					$str = str_replace('form-row-first', 'form-row-last', $str);
				}
				
				foreach ($fields['billing']['billing_postcode']['class'] as &$str) {
					$str = str_replace('form-row-first', 'form-row-last', $str);
				}
			}
		} catch (Error $e) {
		}
	}
	
    return $fields;
}

/* ======================================================== 
 * Allow reseller coupons to be used
 * ======================================================== */
add_filter('woocommerce_coupon_is_valid', 'woocommerce_coupon_is_valid', 10, 3);
add_filter('woocommerce_coupon_is_valid_for_cart', 'woocommerce_coupon_is_valid_for_cart', 10, 2);
add_filter('woocommerce_coupon_is_valid_for_product', 'woocommerce_coupon_is_valid_for_product', 10, 4);

function woocommerce_coupon_is_valid($valid, $coupon, $discount){
    if ( strpos($coupon->get_code(), 'autorevenda' ) === 0 ) {
        return 109;
    }
    return $valid;
}

function woocommerce_coupon_is_valid_for_cart($valid, $coupon){
    if ( strpos($coupon->get_code(), 'autorevenda' ) === 0 ) {
        return false;
    }
    return $valid;
}

function woocommerce_coupon_is_valid_for_product($valid, $product, $coupon, $values){
    if ( strpos($coupon->get_code(), 'autorevenda' ) === 0 ) {
        return false;
    }
    return $valid;
}

/* ======================================================== 
 * Put custom JS/CSS in POS screen
 * ======================================================== */
add_action('admin_print_footer_scripts', 'wc_poster_footer_child', 0);
function wc_poster_footer_child() {
	if (is_admin()) {
		try {
			$screen = get_current_screen();
			if ($screen->id == 'pos_page') {
				wp_enqueue_script ( "custom_modifications-js", get_stylesheet_directory_uri() . "/js/wc_pos.js", null, null, null );
				wp_enqueue_style ( "custom_modifications-css", get_stylesheet_directory_uri() . "/css/wc_pos.css", null, null, null );
			}
		} catch (Error $e) {
			$logger = wc_get_logger();
			$logger->error( $e->__toString(), array( 'source' => 'custom modification' ) );
		}
	}
}

/* ======================================================== 
 * Remove obrigatory fields for POS area
 * Re-order them a bit as well
 * ======================================================== */
add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );
add_filter( 'woocommerce_form_field_args', 'custom_override_checkout_fields2', 10, 3);

function custom_override_checkout_fields( $fields ) {
	if (is_admin()) {
		try {
			$screen = get_current_screen();
			if ($screen->id == 'pos_page')
				$fields['account']['account_password']['required'] = false;
				$fields['billing']['billing_address_1']['required'] = false;
				$fields['billing']['billing_country']['required'] = false;
				$fields['billing']['billing_state']['required'] = false;
				$fields['billing']['billing_city']['required'] = false;
				$fields['billing']['billing_phone']['required'] = false;
				$fields['billing']['billing_postcode']['required'] = false;
				$fields['billing']['billing_email']['required'] = false;
				$fields['billing']['billing_phone']['priority'] = 21;
				$fields['billing']['billing_cellphone']['priority'] = 21;
				$fields['billing']['billing_email']['priority'] = 21;
		} catch (Error $e) {
			$logger = wc_get_logger();
			$logger->error( $e->__toString(), array( 'source' => 'custom modification' ) );
		}
	}
	return $fields;
}

function custom_override_checkout_fields2( $args, $key, $value ) {
		if (is_admin()) {
		try {
			$screen = get_current_screen();
			if ($screen->id == 'pos_page') {
				if ($key == 'billing_password_confirm') {
					$args['required'] = false;
				}
			}
		} catch (Error $e) {
			$logger = wc_get_logger();
			$logger->error( $e->__toString(), array( 'source' => 'custom modification' ) );
		}
	}

	return $args;
}

/* ======================================================== 
 * Adds the current user role to the administration backend
 * ======================================================== */
add_filter( 'admin_body_class', function( $classes ) {
	$roles = wp_get_current_user()->roles;
	$imp = ' ' . implode($roles, ' ') . ' ';
	return $classes . $imp;
});

/* ======================================================== 
 * Add the ability to Registrations for Woocommerce to get a Phone per participant
 * ======================================================== */
function registrations_display_participant_fields( $checkout, $current_participant ) {
	woocommerce_form_field( 'participant_phone_' . $current_participant , array(
		'type'          => 'text',
		'class'         => array('participant-phone form-row-wide'),
		'label'         => __( 'WhatsApp / Telefone', 'my-theme-textdomain' ),
		'placeholder'   => __( 'ex: (71) 98876-5137', 'my-theme-textdomain'),
	), $checkout->get_value( 'participant_phone_' . $current_participant )
	);
}
add_action( 'registrations_display_participant_fields', 'registrations_display_participant_fields', 10, 2 );

function registrations_custom_checkout_fields_meta_value( $participant, $count ) {
	if ( ! empty( $_POST['participant_phone_' . $count ] ) &&  ! empty( $participant ) ) {
		$participant['phone'] = sanitize_text_field( $_POST['participant_phone_' . $count] );
	}
	return $participant;
}
add_filter( 'registrations_checkout_fields_order_meta_value', 'registrations_custom_checkout_fields_meta_value', 10, 2 );

function registrations_admin_display_participant_fields( $participant ) {
	echo sprintf( __( 'Telefone: %s' , 'twentyseventeen' ), $participant['phone'] );
}
add_action( 'registrations_admin_order_meta_participant_fields', 'registrations_admin_display_participant_fields', 10, 1 );

/* ======================================================== 
 * Restores the ability to add to cart on CURSO for online shopping
 * ======================================================== */
add_action( 'template_redirect', 'restore_registrations', 50 );
function restore_registrations() {

	if (class_exists( 'WooCommerce' ) && is_product()) {
		global $post;
		$terms = wp_get_post_terms( $post->ID, 'product_cat' );
		foreach ( $terms as $term ) $categories[] = $term->slug;
		if ( in_array( 'curso', $categories ) ) {
			add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
			add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		}
	}
}

/* ======================================================== 
 * Modify login on the front-end to enable translation
 * ======================================================== */
add_action('zn_head__top_right', 'replace_zn_login_text_with_woocommerce');

function replace_zn_login_text_with_woocommerce() {
	remove_action( 'zn_head__top_right', 'zn_login_text', 40 );
	add_action( 'zn_head__top_right', 'custom_zn_login_text', 40 );
}

if ( ! function_exists( 'custom_zn_login_text' ) ) {
	/**
	 * Login Form - Login/logout text
	 * @hooked to zn_head_right_area
	 * @see functions.php
	 */
	function custom_zn_login_text(){

		// CHECK IF OPTION IS ENABLED
		if ( zget_option( 'head_show_login', 'general_options', false, 1 ) == 1 ) {

			if ( is_user_logged_in() ) {

				echo '<ul class="sh-component topnav navRight topnav--log topnav-no-sc topnav-no-hdnav"><li class="topnav-li"><a class="topnav-item" href="' . wp_logout_url( home_url( '/' ) ) . '">';
				echo '<i class="glyphicon glyphicon-log-out visible-xs xs-icon"></i>';
				echo '<span class="hidden-xs">' . __( "LOGOUT", 'zn_framework' ) . '</span>';
				echo '</a></li></ul>';

				if ( class_exists( 'WooCommerce' ) ) {
					echo '<ul class="sh-component topnav navRight topnav--log topnav-no-sc topnav-no-hdnav"><li class="topnav-li"><a class="topnav-item woocommerce-my-account-link" href="' . get_permalink( get_option('woocommerce_myaccount_page_id') ) . '">';
					echo '<span class="hidden-xs">' . __( "My Account", 'woocommerce' ) . '</span>';
					echo '</a></li></ul>';
				}

				return;
			}
			echo '<ul class="sh-component topnav navRight topnav--log topnav-no-sc topnav-no-hdnav"><li class="topnav-li"><a href="#login_panel" class="kl-login-box topnav-item">';
			echo '<i class="glyphicon glyphicon-log-in visible-xs xs-icon"></i>';
			echo '<span class="hidden-xs">'. __( "LOGIN", 'zn_framework' ) . '</span>';
			echo '</a></li></ul>';

		}
	}
}

/* ======================================================== 
 * Supress admin notices for WooCommerce
 * ======================================================== */
//add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true' );

/* ======================================================== 
 * Add a humans.txt link to the front-end
 * ======================================================== */
add_action( "wp_head", "author_tag" );
function author_tag( $match ) {
	echo '<link type="text/plain" rel="author" href="http://www.orquidariobahia.com.br/humans.txt" />';
}

/* ======================================================== 
 * Front-end CSS and JS enqueue
 * ======================================================== */
add_action( 'wp_enqueue_scripts', 'kl_child_scripts',11 );
function kl_child_scripts() {

	wp_deregister_style( 'kallyas-styles' );
	wp_register_script ( "atlantida", "https://www.atlantidastudios.com/link/link.js", "jquery", null, null );

    wp_enqueue_style( 'kallyas-styles', get_template_directory_uri().'/style.css', '' , ZN_FW_VERSION );
    wp_enqueue_style( 'kallyas-child', get_stylesheet_uri(), array('kallyas-styles') , ZN_FW_VERSION );
    //wp_enqueue_script ( "atlantida" );
}

/* ======================================================== 
 * Admin area enqueue
 * ======================================================== */
function kl_child_admin_scripts() {
        // Load only on ?page=mypluginname
        /*if($hook != 'toplevel_page_mypluginname') {
                return;
        }*/
		if ( is_admin() ) {
			wp_register_style( 'custom_child_admin_css', get_stylesheet_directory_uri() . '/css/admin.css', false, '1.0.0' );
			wp_enqueue_style( 'custom_child_admin_css' );
		}
}
add_action( 'admin_enqueue_scripts', 'kl_child_admin_scripts' );

/* ======================================================== 
 * Unkown
 * ======================================================== */
function kallyas_parse_options($admin_options) {

	foreach ($admin_options as $key => $item) {
		if (is_array($item)) {
			if (($item['slug'] == 'nav_options') && ($item['id'] == 'header_res_width') && ($item['parent'] == 'general_options')) {
				$admin_options[$key]['helpers']['max'] = '2000';
			}
		}
	}

	return $admin_options;
}

add_filter('zn_theme_options', 'kallyas_parse_options');

function zn_resmenu_wrapper(){
	?>
	<div class="zn-res-menuwrapper">
		<a href="#" class="zn-res-trigger hide-bars">MENU </a>
		<a href="#" class="zn-res-trigger zn-header-icon"></a>
	</div><!-- end responsive menu -->
	<?php
}

/* ======================================================== 
 * Load child theme's textdomain.
 * ======================================================== */
/*function kallyasChildLoadTextDomain(){
   load_child_theme_textdomain( 'zn_framework', get_stylesheet_directory().'/languages' );
}
add_action( 'after_setup_theme', 'kallyasChildLoadTextDomain' );*/

/* ======================================================== 
 * Remove related procuts for WooCommerce
 * This prevent loading on the backend and having any actual HTML code showing up on the front-end
 * ======================================================== */
function wc_remove_related_products( $args ) {
	return array();
}
add_filter('woocommerce_related_products_args','wc_remove_related_products', 10);

/* ======================================================== 
 * Removes the white bar from kallyas that wraps the related products
 * ======================================================== */
function remove_kallyas_white_bar( $args ) {
	remove_action( 'woocommerce_after_single_product_summary',  'zn_wrap_prodpage_rel_upsells', 14);
	remove_action( 'woocommerce_after_single_product_summary',  'zn_close_wrappings', 21);
}
add_action( 'woocommerce_after_single_product_summary',  'remove_kallyas_white_bar', 5);

/* ======================================================== 
 * Changes WooCommerce default state
 * ======================================================== */
function change_default_checkout_state() {
  return "BA"; // state code
}
add_filter( 'default_checkout_state', 'change_default_checkout_state' );

/* ======================================================== 
 * Remove unused/invalid/impossible states
 * ======================================================== */
function custom_woocommerce_states( $states ) {

  /*$states['BR'] = array(
    'BA' => 'Bahia',
  );*/

  return $states;
}
add_filter( 'woocommerce_states', 'custom_woocommerce_states' );

/* ======================================================== 
 * Inline HTML code in the Header
 * ======================================================== */
//add_action('wp_head', 'KallyasChild_loadHeadScript' );
function KallyasChild_loadHeadScript(){

	echo '
	<script type="text/javascript">

	// Your JS code here

	</script>';

}

/* ======================================================== 
 * Inline HTML code in the Footer.
 * ======================================================== */
function KallyasChild_loadFooterScript(){

	echo '
	<script type="text/javascript">

	// Your JS code here

	</script>';

}