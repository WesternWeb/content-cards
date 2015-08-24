<?php
/*
Plugin Name: OpenGraph oEmbed
Description: Pull OpenGraph data from other websites and show them as "Cards"
Version: 0.1.0
Author: Arūnas Liuiza
Author URI: http://arunas.co
License: GPL2
*/

add_action( 'plugins_loaded', array( 'OpenGraph_oEmbed', 'init' ) );
class OpenGraph_oEmbed {
	public static $options = array(
		'patterns' => "#http://wptavern\.com/?(.*?)/#i",
		'theme' => 'default',
	);
	private static $stylesheet = '';
	public static function init() {
		$options = get_option( 'opengraph-oembed_options' );
		self::$options = wp_parse_args( $options, self::$options );
		self::$stylesheet = self::get_stylesheet();
		add_action( 'wp_enqueue_scripts', 	array( 'OpenGraph_oEmbed', 'styles' ) );
		add_action( 'admin_init', 			array( 'OpenGraph_oEmbed', 'admin_init' ) );
		add_action( 'admin_menu', 			array( 'OpenGraph_oEmbed', 'admin_menu' ) );
		add_shortcode( 'opengraph', 		array( 'OpenGraph_oEmbed', 'shortcode' ) );
		$patterns = self::$options['patterns'];
		$patterns = explode( "\r\n", $patterns );
		foreach ( $patterns as $id => $regex) {
			wp_embed_register_handler( $id, $regex, array( 'OpenGraph_oEmbed', 'oembed' ) );
		}
	}
	private static function _get_file( $url, $extension='php', $type = 'website', $theme ="default", $method='dir' ) {
		$plugin_dir = plugin_dir_path( __FILE__ );
		$plugin_uri = 'dir' === $method ? $plugin_dir : plugins_url( '/', __FILE__ );
		$theme_dir = get_template_directory() . '/';
		$theme_uri = 'dir' === $method ? $theme_dir : ( get_template_directory_uri() . '/' );
		$child_theme_dir = get_stylesheet_directory() . '/';
		$child_theme_uri = 'dir' === $method ? $child_theme_dir : ( get_stylesheet_directory_uri() . '/' );
		$template =  "{$plugin_uri}templates/{$theme}/opengraph-oembed.{$extension}";
		if ( file_exists( "{$theme_dir}opengraph-oembed.{$extension}" ) ) {
			$template = "{$theme_uri}opengraph-oembed.{$extension}";
		}
		if ( file_exists( "{$child_theme_dir}opengraph-oembed.{$extension}" ) ) {
			$template = "{$child_theme_uri}opengraph-oembed.{$extension}";
		}
		if ( file_exists( "{$plugin_dir}templates/{$theme}/opengraph-oembed-{$type}.{$extension}" ) ) {
			$template = "{$plugin_uri}templates/{$theme}/opengraph-oembed-{$type}.{$extension}";
		}
		if ( file_exists( "{$theme_dir}opengraph-oembed-{$type}.{$extension}" ) ) {
			$template = "{$theme_uri}opengraph-oembed-{$type}.{$extension}";
		}
		if ( file_exists( "{$child_theme_dir}opengraph-oembed-{$type}.{$extension}" ) ) {
			$template = "{$child_theme_uri}opengraph-oembed-{$type}.{$extension}";
		}
		$template = apply_filters( 'opengraph_oembed_file', $template, $url, $extension );
		return $template;
	}
	private static function get_template( $url, $type = 'website' ) {
		$template = self::_get_file( $url, 'php', $type, self::$options['theme'] );
		$template = apply_filters( 'opengraph_oembed_template', $template, $url );
		$template = file_get_contents( $template );
		return $template;
	}
	private static function get_stylesheet( ) {
		$template = self::_get_file( false, 'css', '', self::$options['theme'], 'uri'  );
		$template = apply_filters( 'opengraph_oembed_stylesheet', $template );
		return $template;
	}
	public static function admin_init() {
		if ( self::$stylesheet ) {
			add_editor_style( self::$stylesheet );		
		}
	}
	public static function admin_menu() {
		require_once ( 'includes/options.php' );
		$fields =   array(
			"general" => array(
				'title' => '',
				'callback' => '',
				'options' => array(
					'patterns' => array(
						'title'=>__('oEmbed Whitelist','opengraph-oembed'),
						'args' => array (
							'rows' 		  => 10,
							'cols'		  => 60,
							'description' => __( 'A list of <code>regex</code> link patterns, one per line.', 'opengraph-oembed' ),
						),
						'callback' => 'textarea',
					),
					'theme' => array(
						'title'=>__('Snippet theme','opengraph-oembed'),
						'args' => array (
							'values' => array('default','fancy'),
							'description' => __( 'Can be overwritten by theme.', 'opengraph-oembed' ),
						),
						'callback' => 'select',
					),
				),
			),
		);
		$tabs = array();
		OpenGraph_oEmbed_Options::init(
			'opengraph-oembed',
			__( 'OpenGraph oEmbed',          'opengraph-oembed' ),
			__( 'OpenGraph oEmbed Settings', 'opengraph-oembed' ),
			$fields,
			$tabs,
			'OpenGraph_oEmbed',
			'opengraph-oembed-settings'
		);		
	}
	public static function styles() {
		if ( self::$stylesheet ) {
			wp_register_style( 'opengraph-oembed', self::$stylesheet );
			wp_enqueue_style( 'opengraph-oembed' );
		}
	}
	public static function oembed( $matches, $attr, $url, $rawattr ) {
		$embed = self::build( $url );
		return apply_filters( 'embed_opengraph_oembed', $embed, $matches, $attr, $url, $rawattr );
	}
	public static function shortcode( $args, $content = '') {
		$result = '';
		if ( !isset( $args['url'] ) ) {
			return $result;
		}
		$result = self::build( $args['url'] );
		return $result;
	}
	public static function build( $url ) {
		$data = self::get_data( $url );
		$result = "";
		if ( !$data ) {
			return $result;
		}
		$data['description'] = wpautop($data['description']);
		$data['url'] = $url;
		if ( isset($data['image']) ) {
			$data['image'] = "<img src=\"{$data['image']}\" alt=\"\"/>";
		} else {
			$data['image'] = "";
		}
		$type = isset( $data['type'] ) ? $data['type'] : 'website';
		foreach( $data as $key => $value ) {
			unset( $data[$key] );
			$data["%%{$key}%%"] = $value;
		}
		$template = self::get_template( $url, $type );;

		$result = str_replace( 
			array_keys($data), 
			array_values($data), 
			$template 
		);
		return $result;
	}
	private static function get_data( $url ) {
		$result = get_transient( 'og_oembed_'.md5( $url ) );
		if ( !$result ) {
			require_once( 'includes/opengraph.php' );
			$data = wp_remote_retrieve_body( wp_remote_get( $url ) );
			if ( $data ) {
				$graph = OpenGraph::parse( $data );
				$result = array();
				if ( sizeof( $graph ) > 0 ) {
					foreach ($graph as $key => $value) {
					    $result[$key] = $value;
					}				
				}
				if ( $result ) {
					set_transient( 'og_oembed_'.md5( $url ), $result, DAY_IN_SECONDS );
				}				
			}
		}
		return $result;
	}
} 