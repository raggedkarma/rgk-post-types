<?php
/**
 * @package RGK Post Types
 */
/*
Plugin Name: Post Types
Plugin URI: http://www.bv02.com
Description: Build custom post types using object-oriented principles
Version: 0.5.dev
Author: bv02
Author URI: http://www.bv02.com
*/

/* Load Custom Post Types */
function rgk_post_types_init() {
	$post_type_dir =  dirname(__FILE__) . DIRECTORY_SEPARATOR . 'post-types' . DIRECTORY_SEPARATOR;
	if( is_dir($post_type_dir) && ($types = opendir($post_type_dir)) )
	{
		require_once( dirname(__FILE__) . DIRECTORY_SEPARATOR . 'post-type-superclass.php' );
		while( $file = readdir($types) )
		{
			list( $class, $ext ) = explode('.', $file);
			if( ($ext == 'php') && is_file($post_type_dir.$file) )
			{
				require_once( $post_type_dir . $file );
				$className = ucfirst($class.'PostType');
				$GLOBALS['rgk-post-type-'.strtolower($class)] =& new $className();
			}
		}
		closedir($types);
	}
}
add_action('init', 'rgk_post_types_init');

function rgk_post_types_admin_class( $classes ) {
	global $post_type;
	$classes .= $post_type;
	return $classes;
}
add_filter('admin_body_class', 'rgk_post_types_admin_class');

?>