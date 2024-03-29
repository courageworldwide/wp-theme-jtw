<?php
/************************************************************************************ 
/* Definition and functions for Associate custom post type.
/*
/* By Jesse Rosato, 2012 - jesse.rosato@gmail.com
/************************************************************************************/
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-content/themes/cww/library/utilities/CwwPostTypeEngine.class.php');
require_once('associate_meta_boxes.php');

$cww_associate_post_type = array(
	'handle'	=> 'cww_associate',
	'args'		=>array(
		'labels' => array(
			'name' => __( 'Associates' ),
			'singular_name' => __( 'Associate' ),
			'all items' => __( 'All Associates' ),
			'add_new_item' => __( 'Add New Associate' ),
			'edit_item' => __( 'Edit Associate' ),
			'new_item' => __( 'New Associate' ),
			'view_item' => __( 'View Associate' ),
			'search_item' => __( 'Search Associates' ),
			'not_found' => __( 'No Associates found' ),
			'not_found_in_trash' => __( 'No Associates found in trash' )
		),
		'singular_label' => __('associate', 'cww'),
		'description' => __( 'Create an associate, with first name, last name, etc.' ),
		'rewrite' => array('slug' => 'people','with_front' => false),
		'public' => true,
		'publicly_queryable' => true,
		'has_archive' => true,
		'show_in_nav_menus' => false,
		'menu_position' => 20,
		'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes', 'post-formats')
	),
	'meta_box_groups' => array(
		'cww_associate_details' => array(
			'handle' => 'cww_associate_details',
			'title' => __('Associate Details'),
			'desc' => '',
			'priority' => 'default',
			'context' => 'side'
		),
	)
);
$cww_associate_meta_boxes = cww_associate_meta_boxes();
$cww_associate_post_type_engine = new CwwPostTypeEngine($cww_associate_post_type, $cww_associate_meta_boxes);
add_action('init', array(&$cww_associate_post_type_engine, 'create_post_type'));
add_action('admin_init', array(&$cww_associate_post_type_engine, 'add_meta_boxes'));

// Add relationship taxonomy
add_action('init', 'cww_associate_register_taxonomy');
function cww_associate_register_taxonomy( ) {
	register_taxonomy('cww_associate_relationships','cww_associate',array(
		'hierarchical' => true,
		'labels' => array(
			'name' => _x( 'Associate Relationships', 'taxonomy general name', 'cww' ),
			'singular_name' => _x( 'Associate Relationship', 'taxonomy singular name', 'cww' ),
			'search_items' =>  __( 'Search Associate Relationships', 'cww' ),
			'popular_items' => __( 'Popular Associate Relationships', 'cww' ),
			'all_items' => __( 'All Associate Relationships', 'cww' ),
			'parent_item' => null,
			'parent_item_colon' => null,
			'edit_item' => __( 'Edit Associate Relationship', 'cww' ), 
			'update_item' => __( 'Update Associate Relationship', 'cww' ),
			'add_new_item' => __( 'Add New Associate Relationship', 'cww' ),
			'new_item_name' => __( 'New Associate Relationship Name', 'cww' ),
			'separate_items_with_commas' => __( 'Separate Associate Relationships with commas', 'cww' ),
			'add_or_remove_items' => __( 'Add or remove Associate Relationship', 'cww' ),
			'choose_from_most_used' => __( 'Choose from the most used Associate Relationships', 'cww' ),
			'menu_name' => __( 'Relationships', 'cww' ),
		),
		'public' => false,
		'show_in_nav_menus' => false,
		'show_ui' => true,
		'show_tagcloud' => false,
		'query_var' => false,
		'rewrite' => false,
		
	));
}

function cww_associate_content( $associate_id = false, $type = 'single' ) {
	echo cww_associate_get_content($associate_id, $type);
}

function cww_associate_get_content( $associate_id = false, $type = 'single' ) {
	$post = $associate_id ? get_post( $associate_id ) : $GLOBALS['post'];
	if ( $type == 'single' || $type == 'multi-full' ) {
		$content = apply_filters('the_content', $post->post_content);
	} else {
		$content = apply_filters('the_excerpt', $post->post_excerpt);
		if ( empty( $content ) )
			$content = apply_filters('the_content', $post->post_content);
	}
		
	$first		= get_post_meta($post->ID, 'cww_associate_first_name', true);
	$last		= get_post_meta($post->ID, 'cww_associate_last_name', true);
	$org		= get_post_meta($post->ID, 'cww_associate_organization', true);
	$pos		= get_post_meta($post->ID, 'cww_associate_position', true);
	$terms		= wp_get_post_terms($post->ID, 'cww_associate_relationships');
	$rels		= array();
	foreach ( $terms as $term )
		$rels[] = $term->name;
	$rel = implode(', ', $rels);
	if ( has_post_thumbnail( $post->ID ) ) {
		$images = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' );
		$image = empty($images[0]) ? false : $images[0];
	} else {
		$image = false;
	}
	$result = '<div class="cww-associate">';
	$result .= '<div class="cww-associate-title">';
	$result .= '<h3><a href="' . get_permalink($post->ID) . $first . ' ' . $last . '</a></h3>';
	$result .= '</div>';
	$result .= '<div class="cww-associate-details">';
	if ( $image )
		$result .= '<img class="cww-associate-thumbnail thumbnail" src="' . $image . '" />';
	$result .= '<p><strong>Name</strong>: ' . $first . ' ' . $last . '</p>';
	if ( $org )
		$result .= '<p><strong>Organization</strong>: ' . $org . '</p>';
	if ( $pos ) :
		$result .= '<p><strong>Position</strong>: ' . $pos . '</p>';
	$result .= '</div>';
	$result .= '<div class="cww-associate-description">';
	$result .= $content;
	$result .= '<p><strong>Relationships</strong>: ' . $rel . '</p>';
	$result .= '</div></div>';
	
	return $result;
}

/************************************************************************************ 
/* Validate and save post meta data.
/*
/* @param int $post_id
/************************************************************************************/
add_action( 'save_post', 'cww_associate_save_post');
function cww_associate_save_post( $post_id ) {
	$post = get_post($post_id);
	// Make sure this is our post type.
	if ( $post->post_type != 'cww_associate' )
		return;
	// verify if this is an auto save routine. 
	// If it is our form has not been submitted, so we dont want to do anything
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
		return;
	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	if ( !wp_verify_nonce( isset( $_POST['cww_associate_nonce'] ) ? $_POST['cww_associate_nonce'] : false, 'cww_nonce_field_cww_associate' ) )
		return;
	
	// Get the post type object.
    $post_type = get_post_type_object( $post->post_type );
    // Check if the current user has permission to edit this post-type.
    if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
        return;
	foreach ( $_POST as $key => $value ) {
		if ( preg_match( '/^cww_associate_.*/', $key ) )
			update_post_meta( $post_id, $key, trim( $value ) );
	}
}

/************************************************************************************ 
/* Return an associate's content.
/*
/* @param array $atts
/* @param string $content
/*
/* @return string
/************************************************************************************/
add_shortcode( 'associate', 'cww_associate_single_shortcode_callback' );
function cww_associate_single_shortcode_callback( $atts, $content = null ) {
	$associate_id = empty($atts['associateid']) ? false : $atts['associateid'];
	$type = empty($atts['excerpt']) ? false : 'multi';
	return cww_associate_get_content($associate_id, $type);
}