<?php 
/*
 Plugin Name: Vimeo Videos - debug utility plugin
 Plugin URI: 
 Description: Debug for Vimeo Video Post plugin
 Author: CodeFlavors
 Version: 1.0
 Author URI: https://codeflavors.com
 */

// No direct access
if( !defined( 'ABSPATH' ) ){
	die();
}

class CF_VVP_Debug{
	
	/**
	 * Store custom post type class reference from main plugin 
	 * @var CVM_Video_Post_Type
	 */
	private $cpt;
	
	public function __construct(){
		
		add_action( 'init', array( $this, 'set_variables' ) );
		
		// add extra menu pages
		add_action( 'admin_menu', array( $this, 'menu_pages' ), 10 );
		
		// process errors sent by the plugin
		add_action( 'cvm_debug_request_error', array( $this, 'register_error' ), 10, 1 );
		add_action( 'cvm_debug_automatic_import', array( $this, 'register_error' ), 10, 1 );
		add_action( 'cvm_debug_bulk_import', array( $this, 'register_error' ), 10, 1 );
	}
	
	/**
	 * Action 'init' callback
	 */
	public function set_variables(){
		global $CVM_POST_TYPE;
		if( !$CVM_POST_TYPE ){
			return;
		}
		
		$this->cpt = $CVM_POST_TYPE;
		
	}
	
	/**
	 * Add debug page to main plugin menu
	 */
	public function menu_pages(){
		
		$debug = add_submenu_page(
			'edit.php?post_type=' . $this->cpt->get_post_type(),
			__('Debug', 'cvm_video'),
			__('Debug', 'cvm_video'),
			'manage_options',
			'cvm_debug',
			array( $this, 'debug_page' ) );
		
		add_action( 'load-' . $debug, array( $this, 'debug_onload' ) );
		
	}
	
	/**
	 * Debug plugin menu page onLoad callback
	 */
	public function debug_onload(){
		
		wp_enqueue_style(
			'cvm_debug_css',
			plugin_dir_url( __FILE__ ) . 'assets/css/style.css'				
		);
		
		// clear log
		if( isset( $_GET['cvm_nonce'] ) ){
			check_admin_referer( 'cvm_reset_error_log', 'cvm_nonce' );
			$file = plugin_dir_path( __FILE__ ) . 'error_log';
			$handle = fopen( $file, 'w' );
			fclose( $handle );
		}
		
	}
	
	/**
	 * Output debug page
	 */
	public function debug_page(){
		global $CVM_AUTOMATIC_IMPORT;
		
		$data = $this->gather_data();
		
?>
<div class="wrap">
	<h1><?php _e( 'Plugin information', 'cvm_video' );?></h1>
	<table class="cvm-debug widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'WordPress environment', 'cvm_video' );?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach( $data['wp'] as $d ):?>
			<tr>
				<td class="label"><?php echo $d['label'];?></td>
				<td><?php echo $d['value'];?></td>
			</tr>
			<?php endforeach;?>
		</tbody>
	</table>
	<br />
	<table class="cvm-debug widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Plugin', 'cvm_video' );?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach( $data['plugin'] as $d ):?>
			<tr>
				<td class="label"><?php echo $d['label']; ?>:</td>
				<td><?php echo $d['value']; ?></td>
			</tr>
			<?php endforeach;?>
		</tbody>
	</table>
	<br />
	<table class="cvm-debug widefat">
		<thead>
			<tr>
				<th><?php _e( 'Error log', 'cvm_video' );?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<?php 
						$file = plugin_dir_path( __FILE__ ) . 'error_log';
						if( !file_exists( $file ) || filesize( $file ) == 0 ){
							_e( 'No errors registered.', 'cvm_video' );
						}else{
							$handle = fopen( $file , 'r' );
							$content = fread( $handle, filesize( $file ) );
							fclose( $handle );
					?>
							<textarea id="cvm-debug-box" style="width:100%; height:300px;"><?php echo $content;?></textarea>
							<?php
								$url = menu_page_url( 'cvm_debug', false );
								$nonce = wp_create_nonce( 'cvm_reset_error_log' );
							?>							
							<a class="button" href="<?php echo $url . '&cvm_nonce=' . $nonce;?>"><?php _e( 'Clear log', 'cvm_video' );?></a>
					
					<?php }?>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<script language="javascript">
var textarea = document.getElementById('cvm-debug-box');
textarea.scrollTop = textarea.scrollHeight;
</script>
<?php
	}
	
	/**
	 * Store errors in error log
	 * @param WP_Error $error
	 */
	public function register_error( WP_Error $error ){
		
		if( !is_wp_error( $error ) ){
			return;
		}
		
		$codes = $error->get_error_codes();
		
		$handle = fopen( plugin_dir_path( __FILE__ ) . 'error_log' , "a" );
		
		foreach( $codes as $code ){
			$message = $error->get_error_message( $code );
			$data = $error->get_error_data( $code );
			
			$log_entry = sprintf(
				__( '[%s] %s (Error code: %s)', 'cvm_video' ),
				date( 'M/d/Y H:i:s' ),
				$message,
				$code//,
				//"\n" . print_r( $data, true )
			);
			
			fwrite( $handle, $log_entry ."\n" );
		}
		
		fclose( $handle );		
	}
	
	/**
	 * Gather data about the plugin and WP to output it into the debug page
	 */
	private function gather_data(){
		
		global $CVM_AUTOMATIC_IMPORT;
		$last_import = $CVM_AUTOMATIC_IMPORT->get_update();
		
		$transient_time = $CVM_AUTOMATIC_IMPORT->get_transient_time();
		
		$data = array();
		$data['wp'] = array(
			'version' => array(
				'label' => __( 'WordPress version', 'cvm_video' ),
				'value' => get_bloginfo( 'version' )
			),
			'multisite' => array(
				'label' => __( 'WP Multisite', 'cvm_video' ),
				'value' => ( is_multisite() ? __( 'Yes', 'cvm_video' ) : __( 'No', 'cvm_video' ) )
			),
			'debug' => array(
				'label' => __( 'WordPress debug', 'cvm_video' ),
				'value' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'On', 'cvm_video' ) : __( 'Off', 'cvm_video' ) )
			),
			'cron' => array(
				'label' => __( 'WP Cron', 'cvm_video' ),
				'value' => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Not allowed', 'cvm_video' ) : __( 'Allowed', 'cvm_video' ) )
			)			
		);
		
		$data['plugin'] = array(
			'version' => array(
				'label' => __( 'Version', 'cvm_video' ),
				'value' => ( defined( 'CVM_VERSION' ) ? CVM_VERSION : __( 'unknown', 'cvm_video' ) )
			),
			'client_code' => array(
				'label' => __( 'Client code', 'cvm_video' ),
				'value' => ( defined( 'CFVIM_CLIENT_CODE' ) ? CFVIM_CLIENT_CODE : __( 'unknown', 'cvm_video' ) )
			),
			'last_import' => array(
				'label' => __( 'Last automatic import', 'cvm_video' ),
				'value' => sprintf( 
						'Post id: %s <br />Server time: %s <br> Import time: %s <br> Currently running: %s', 
						$last_import['post_id'],
						date( 'M/d/Y H:i:s' ),
						date( 'M/d/Y H:i:s', $last_import['time'] ),
						$last_import['running_update'] ? 'Yes' : 'No' 
				)
			),
			'transient' => array(
				'label' => __( 'Transient time', 'cvm_video' ),
				'value' => sprintf( 'Transient time: %1$s <br> Next import at: %3$s<br>Delay between imports: %2$s',
						( $transient_time ? date( 'M/d/Y H:i:s', $transient_time ) : 'not set' ),
						$CVM_AUTOMATIC_IMPORT->get_delay() . ' sec. ( ' . cvm_human_time( $CVM_AUTOMATIC_IMPORT->get_delay() ) . ' )',
						date( 'M/d/Y H:i:s', $transient_time + $CVM_AUTOMATIC_IMPORT->get_delay() )
					)
			)
			
		);
		
		return $data;
	}
	
}
new CF_VVP_Debug();