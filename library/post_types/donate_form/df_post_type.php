<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-content/themes/cww/library/utilities/CwwPostTypeEngine.class.php');
require_once('df_meta_boxes.php');
require_once('df_options.php');

$cww_df_post_type = array(
	'handle'	=> 'cww_donate_form',
	'args'		=>array(
		'labels' => array(
			'name' => __( 'Donate Forms' ),
			'singular_name' => __( 'Donate Form' ),
			'all items' => __( 'All Donate Forms' ),
			'add_new_item' => __( 'Add New Donate Form' ),
			'edit_item' => __( 'Edit Donate Form' ),
			'new_item' => __( 'New Donate Form' ),
			'view_item' => __( 'View Donate Form' ),
			'search_item' => __( 'Search Donate Forms' ),
			'not_found' => __( 'No Donate Forms found' ),
			'not_found_in_trash' => __( 'No Donate Forms found in trash' )
		),
		'description' => __( 'Use this post type to create new donate forms.' ),
		'rewrite' => array('slug' => 'donate','with_front' => false),
		'public' => true,
		'has_archive' => false,
		'show_in_nav_menus' => false,
		'menu_position' => 20,
		'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes', 'post-formats')
	),
	'meta_box_groups' => array(
		'cww_df_settings' => array(
			'handle' => 'cww_df_settings',
			'title' => __('Donate Form Settings'),
			'priority' => 'high',
			'context' => 'normal'
		),
	)
);
$cww_df_meta_boxes = cww_df_meta_boxes(cww_df_mailchimp_is_enabled(), cww_df_highrise_is_enabled());
$cww_df_post_type_engine = new CwwPostTypeEngine($cww_df_post_type, $cww_df_meta_boxes);
add_action('init', array(&$cww_df_post_type_engine, 'create_post_type'));
add_action('admin_init', array(&$cww_df_post_type_engine, 'add_meta_boxes'));

add_action( 'save_post', 'cww_df_save_post' );
function cww_df_save_post( $post_id ) {
	$post = get_post($post_id);
	// Make sure this is our post type.
	if ( $post->post_type != 'cww_df' )
		return;
	// verify if this is an auto save routine. 
	// If it is our form has not been submitted, so we dont want to do anything
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
		return;
	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	if ( !wp_verify_nonce( ( isset( $_POST['cww_donate_form_nonce'] ) ? $_POST['cww_donate_form_nonce'] : '' ), 'cww_nonce_field_cww_donate_form' ) )
		return;
	
	// Get the post type object.
    $post_type = get_post_type_object( $post->post_type );
    
    // Check if the current user has permission to edit the post.
    if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
        return;
	
	// OK, we're authenticated: we need to find and save the data
	$_POST['cww_df_update_hr'] = isset( $_POST['cww_df_update_hr'] ) && $_POST['cww_df_update_hr'] ? 1 : 0;
	
	foreach ( $_POST as $key => $value ) {
		if ( preg_match( '/^cww_df_.*/', $key ) ) {
			$value = trim( $_POST[$key] );
			update_post_meta( $post_id, $key, $value );
		}
	}
}

function cww_df_mailchimp_is_enabled() {
	$options = cww_df_options_page_fields();
	return cww_df_option_is_set($options['cww_df_mailchimp_setting_api_token']);
}

function cww_df_highrise_is_enabled() {
	$options = cww_df_options_page_fields();
	$required = array(
		'cww_df_highrise_setting_account',
		'cww_df_highrise_setting_api_token',
		'cww_df_highrise_setting_admin_user_id',
		'cww_df_highrise_setting_admin_group_id',
		'cww_df_highrise_setting_deals_admin_user_id',
		'cww_df_highrise_setting_task_delay',
		'cww_df_highrise_setting_onetime_category_id',
		'cww_df_highrise_setting_monthly_category_id',
		'cww_df_highrise_setting_annual_category_id',
		'cww_df_highrise_setting_business_category_id'
	);
	foreach ( $required as $id ) {
		if ( !cww_df_option_is_set( $options[$id] ) )
			return false;
	}
	return true;
}

add_action( 'admin_notices','cww_df_admin_notice' );
function cww_df_admin_notice(){
	if ( !current_user_can('manage_options') )
		return;
	
	if ( cww_df_required_options_not_set() ) {
	?>
		<div class="error">
		  <p>
		  	<strong><?php _e('Courage Worldwide Notice'); ?></strong><br />
		  </p>
		  <p>
		    <?php _e( "Donate forms are not enabled.  You must first supply your sitewide information in 'Settings >> Donate forms'." ); ?>
		  </p>
		</div>
	<?php
	}
}
 
add_filter( 'custom_menu_order', 'cww_df_required_options_not_set' );
function cww_df_required_options_not_set() {
	return !cww_df_required_options_are_set();
}

function cww_df_required_options_are_set() {
 	$options = cww_df_options_page_fields();
	foreach ( $options as $option ) {
		if ( isset( $option['req'] ) && $option['req'] && !cww_df_option_is_set($option) )
			return false;
	}
    return true;
 }

function cww_df_option_is_set( $option ) {
	static $settings = false;
	$settings = $settings ? $settings : get_option( 'cww_df_options' );
	$setting = isset( $settings[$option['id']] ) ? $settings[$option['id']] : false;
	return ( $setting && $option['std'] != $setting );
}

add_filter( 'menu_order', 'cww_df_hide_post_type' );
function cww_df_hide_post_type($menu_order) {		
	global $menu;
	foreach ( $menu as $key => $array ) {
		if ( in_array( 'edit.php?post_type=cww_donate_form', $array ) ) 
			$unset_key = $key;
	}
	unset($menu[$unset_key]);
	return $menu_order;
}