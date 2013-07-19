<?php

/*
 *	Name: PostType superclass - for extension only
 *	Description: Class base for establishing new post types in WP.
 *	Version:  0.3a
 */
class PostType {
	
	/* In your extension, replace the plural/singular names with your name. A prefix is highly encouraged, but not necessary. */
	var $plural = 'Tests';
	var $singular = 'Test';
	var $prefix = null;
	var $system_name = null;
	var $domain = 'textdomain';
	var $icon = '/library/images/custom-post-icon.png';
	var $supports = array('title','editor','excerpt'); //Default supports are pretty basic
	var $taxonomies = array('category', 'post_tag');
	var $metafields = array();
	var $columns = array();
	var $nonce = null;
	
	function __construct() {
		$this->domain = $this->pr($this->domain);
		$this->system_name = substr($this->pr($this->singular),0,20); //because that's the maximum. Thanks, WordPress, you've done it again.
		if( !isset($this->nonce) )
		{
			$this->nonce = '_'.$this->system_name;
		}
		
		/* Add Meta Boxes */
		add_action( 'add_meta_boxes', array(&$this, 'add_metaboxes') );
		/* Save Post Logic */
		add_action( 'save_post', array(&$this,'save_postdata') );
		/* Custom Columns */
		add_action( 'manage_'.$this->system_name.'_posts_custom_column', array(&$this, 'parse_column') );
		add_filter( 'manage_'.$this->system_name.'_posts_columns', array(&$this, 'extra_columns') );
		
		/* Make It So */
		$this->create();
	}
	
	function create() {
		register_post_type($this->system_name, array(
			'labels' => array(
				'name' => _x("$this->plural", 'post type general name'),
			    'singular_name' => _x("$this->singular", 'post type singular name'),
			    'add_new' => _x('Add New', "$this->singular"),
			    'add_new_item' => __("Add New $this->singular"),
			    'edit_item' => __("Edit $this->singular"),
			    'new_item' => __("New $this->singular"),
			    'view_item' => __("View $this->singular"),
			    'search_items' => __("Search $this->plural"),
			    'not_found' =>  __("No $this->plural found"),
			    'not_found_in_trash' => __('No $this->plural found in Trash'),
			    'parent_item' => __("Parent $this->singular"),
			    'parent_item_colon' => __("Parent $this->singular:")
			),
			'menu_icon' => get_stylesheet_directory_uri() . $this->icon, /* the icon for the custom post type menu */
			'taxonomies' => $this->taxonomies,
			'rewrite' => array('slug' => strtolower($this->plural)),
			'hierarchical' => false,
			'supports' => $this->supports,
			'public' => true
		));
	}
	
	function extra_columns( $columns ) {
		$extra = $this->columns;
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'title' => 'Title'
		);
		if( !empty($extra) )
		{
			foreach( $extra as $k => &$c )
			{
				$columns[$k] = __($c, $this->domain);
			}
		}
		$columns['date'] = __('Date');
		return $columns;
	}
	
	/*
	 *	Extension 
	 */
	function parse_column( $column ) {
		global $post;
		switch( $column )
		{
			case 'ID':
				echo $post->ID;
				break;
			case isset($post->{"post_".$column}): //title, content, excerpt, date...
				echo $post->{"post_".$column};
				break;
			case 'thumbnail':
				echo get_the_post_thumbnail( $post->ID );
				break;
			default:
				echo get_post_meta( $post->ID, $this->pr($column), true );
				break;
		}
	}
	
	function add_metaboxes() {
		if( !empty($this->metafields) )
		{
			add_meta_box(
				$this->system_name.'-metabox',
				__( $this->singular.' Details', $this->domain ),
				array( &$this, 'custom_fields' ),
				$this->system_name,
				'normal',
				'high',
				null
			);
		}
	}
	
	function get_posts( $args = array() ) {
		$args['post_type'] = $this->system_name;
		$posts = get_posts( $args );
		if( !empty($posts) && !empty($this->metafields) )
		{
			foreach( $posts as &$post )
			{
				$post->post_meta = $this->get_meta($post->ID,array_keys($this->metafields));
			}
			foreach( $this->taxonomies as $taxonomy )
			{
				$post->post_taxonomies[$taxonomy] = wp_get_post_terms($post->ID, $taxonomy);
			}
		}
		return $posts;
	}
	
	/* Helper functions to get/save large amounts of metadata */
	function update_meta( $post_id, $metadata = array() ) {
		if( !empty($metadata) )
		{
			foreach( $metadata as $meta => &$data )
			{
				update_post_meta($post_id, $this->pr($meta), $data);
			}
		}
	}
	function get_meta( &$post_id, $field ) {
		if( is_array($field) )
		{
			$fields = array_combine($field, $field);
			foreach( $fields as &$field )
			{
				$field = $this->get_meta($post_id,$field);
			}
			return $fields;
		}
		else
		{
			return get_post_meta($post_id,$this->pr($field),true);
		}
	}
	
	function custom_fields( $post ) {
		if( !empty($this->nonce) )
		{
			wp_nonce_field( $this->system_name, $this->nonce );
		}
		if( !empty($this->metafields) )
		{
			$inputs = array();
			$data = $this->get_meta($post->ID,array_keys($this->metafields));
			foreach( $this->metafields as $meta => &$title )
			{
				$sys = $this->pr($meta);
				$inputs[] = '<label style="font-weight:bold;" for="'.$sys.'">'.__($title,$this->domain).'</label><br /><input id="'.$sys.'" name="'.$sys.'" value="'.((isset($data[$meta])&&!empty($data[$meta]))?$data[$meta]:'').'" type="text" style="width:100%;" />';
			}
			echo '<ul><li>'.implode('</li><li>', $inputs).'</li></ul>';
		}
	}
	
	function save_postdata( $post_id ) {
		if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		{
			return;
		}
		else if( !empty($this->nonce) && !wp_verify_nonce($_POST[$this->nonce], $this->system_name) )
		{
			return;
		}
		if( !empty($this->metafields) )
		{
			$updates = $this->metafields;
			foreach( $updates as $meta => &$field )
			{
				$field = $_POST[$this->pr($meta)];
			}
			$this->update_meta($post_id, $updates);
		}
	}
	
	function set_labels( $labels = array() ) {
		$this->labels = array_merge($this->labels,$labels);
	}
	
	function pr( $string = '' ) {
		if( isset($this->prefix) )
		{
			$prefix = $this->prefix;
		}
		return str_replace('-','_',sanitize_title(implode('_', compact('prefix','string'))));
	}

}
?>