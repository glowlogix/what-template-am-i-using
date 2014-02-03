<?php
/*
Plugin Name: What Template Am I Using
Description: This plugin is intended for theme developers to use. It shows the current template being used to render the page, current post type, and much more.
Author: Eric King
Version: 0.1.6
Author URI: http://webdeveric.com/
Plugin Group: Utilities

----------------------------------------------------------------------------------------------------

If you want to add your own information to the sidebar panel, you just need to create a class that
extends WTAIU_Panel.

Take a look at core-panels.php for examples.

----------------------------------------------------------------------------------------------------

Here is how you can filter the handle text.

add_filter('wtaiu_handle_text', function( $text ){
	return 'Your Custom Text Here';
} );

----------------------------------------------------------------------------------------------------

@todo Add a little arrow in the drag handle so that the user can collapse the panel.

*/

include __DIR__ . '/PriorityQueueInsertionOrder.php';
include __DIR__ . '/wtaiu-panel.php';
include __DIR__ . '/core-panels.php';



class What_Template_Am_I_Using {

	const VERSION = '0.1.5';

	private static $panels;

	public static function init(){

		self::$panels = new PriorityQueueInsertionOrder();

		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		add_action( 'init', array( __CLASS__, 'setup' ) );
		add_action( 'wp_ajax_wtaiu_save_sort_order', array( __CLASS__, 'wtaiu_save_sort_order') );
	}

	public static function setup(){
		if( ! is_admin() && current_user_can( 'edit_theme_options' ) ){
			self::enqueue_assets();
			add_action( 'wp_footer', array( __CLASS__, 'output' ), PHP_INT_MAX );
		}
	}

	public static function deactivate(){
		delete_metadata( 'user', 0, 'wtaiu-sort-order', '', true );
	}

	public static function wtaiu_save_sort_order() {

		$order = filter_has_var( INPUT_POST, 'order') && is_array( $_POST['order'] ) ? $_POST['order'] : array();

		$user_id = get_current_user_id();
		
		if( $user_id > 0 ){
			update_user_meta( $user_id, 'wtaiu-sort-order', $order );
		}

		wp_send_json( array('updated' => true ) ) ;

		die();
	}

	public static function getPanels(){
		return self::$panels;
	}

	public static function addPanel( WTAIU_Panel $panel, $priority = 1 ){
		self::$panels->insert( $panel, $priority );
	}

	public static function removePanel( WTAIU_Panel $panel ){
		self::$panels->remove( $panel );
	}

	public static function enqueue_assets(){
		wp_enqueue_style('wtaiu', plugins_url( '/css/what-template-am-i-using.css', __FILE__ ), array('dashicons'), self::VERSION );
		wp_enqueue_script('wtaiu-modernizr', plugins_url( '/js/modernizr.custom.49005.js', __FILE__ ), array(), self::VERSION );
		wp_enqueue_script('wtaiu', plugins_url( '/js/what-template-am-i-using.js', __FILE__ ), array('jquery', 'jquery-ui-sortable' ), self::VERSION );
		wp_localize_script('wtaiu', 'wtaiu_ajaxurl', admin_url( 'admin-ajax.php' ) );
	}

	public static function output(){
		
		self::$panels->setExtractFlags( SplPriorityQueue ::EXTR_DATA );

		$user_id = get_current_user_id();

		$order = array();
		$items = array();
		$sorted_items = array();

		if( $user_id > 0 ){
			$order = get_user_meta( $user_id, 'wtaiu-sort-order', true );
			if( isset( $order ) && ! is_array( $order ) )
				$order = array( $order );
			$order = array_filter( $order );
		}

		foreach( self::$panels as $panel ){
			$label = $panel->get_label();
			$content = $panel->get_content();
			$id	= $panel->get_id();
			$items[ $id ] = sprintf('<li class="panel" id="%3$s"><div class="label">%1$s</div><div class="content">%2$s</div></li>', $label, $content, $id );
		}

		foreach( $order as $index => $id ){
			if( isset( $items[ $id ] ) ){
				$sorted_items[ $id ] = $items[ $id ];
				unset( $items[ $id ] );
			}
		}

		?>
		<div id="wtaiu">
			<a id="wtaiu-handle" title="Click to toggle"><span><?php echo apply_filters('wtaiu_handle_text', 'What Template Am I Using?' ); ?></span></a>
			<a id="wtaiu-close" title="Click to remove from page">&times;</a>
			<ul id="wtaiu-data">
				<?php
					// Print out the sorted items.
					echo implode('', $sorted_items );
					// Print out any remaining items that may have been added to the sidebar after the user had saved their sort preference.
					echo implode('', $items );
				?>
			</ul>
		</div>
		<?php
	}

}

What_Template_Am_I_Using::init();
What_Template_Am_I_Using::addPanel( new WTAIU_Template_Panel(), 100 );
What_Template_Am_I_Using::addPanel( new WTAIU_General_Info_Panel(), 100 );
What_Template_Am_I_Using::addPanel( new WTAIU_Additional_Files_Panel(), 100 );
What_Template_Am_I_Using::addPanel( new WTAIU_Scripts_Panel(), 100 );
What_Template_Am_I_Using::addPanel( new WTAIU_Styles_Panel(), 100 );
What_Template_Am_I_Using::addPanel( new WTAIU_IP_Addresses_Panel(), 100 );

/*
if( WP_DEBUG )
	What_Template_Am_I_Using::addPanel( new WTAIU_Server_Info_Panel(), 100 );
*/