<?php

/*
 * Plugin Name: Mijireh Checkout for WP Deals
 * Plugin URI: http://www.patsatech.com
 * Description: Mijireh Checkout Plugin for accepting payments on your WP Deals Store.
 * Author: PatSaTECH
 * Version: 1.0.0
 * Author URI: http://www.patsatech.com
 * Contributors: patsatech
 * Text Domain: patsatech-wpdeals-mijireh
 * Domain Path: /lang
*/


// Register activation hook to install page slurp page
register_activation_hook(__FILE__, 'install_slurp_page');
register_uninstall_hook(__FILE__, 'remove_slurp_page');

function install_slurp_page() {
  if(!get_page_by_path('mijireh-secure-checkout')) {
    $page = array(
      'post_title' => 'Mijireh Secure Checkout',
      'post_name' => 'mijireh-secure-checkout',
      'post_parent' => 0,
      'post_status' => 'private',
      'post_type' => 'page',
      'comment_status' => 'closed',
      'ping_status' => 'closed',
      'post_content' => "<h1>Checkout</h1>\n\n{{mj-checkout-form}}",
    );
    wp_insert_post($page);
  }
}

function remove_slurp_page() {
  $force_delete = true;
  $post = get_page_by_path('mijireh-secure-checkout');
  wp_delete_post($post->ID, $force_delete);
}

add_action('plugins_loaded', 'init_wpdeals_mijireh', 0);
 
function init_wpdeals_mijireh() {
 
    if ( ! class_exists( 'wpdeals_payment_gateway' ) ) { return; }
	
	class wpdeals_mijireh extends wpdeals_payment_gateway {
	
	    /**
	     * Constructor for the gateway.
	     *
	     * @access public
	     * @return void
	     */
		public function __construct() {
			global $wpdeals;
	
			$this->id 			= 'mijireh';
			$this->method_title = __( 'Mijireh Checkout', 'patsatech-wpdeals-mijireh' );
			$this->icon 		= WP_PLUGIN_URL . '/'. plugin_basename( dirname(__FILE__)) .'/assets/images/credit_cards.png';
			$this->has_fields = false;
	
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
	
			// Define user set variables
			$this->access_key 	= $this->settings['access_key'];
			$this->title 		= $this->settings['title'];
			$this->description 	= $this->settings['description'];
		
			// Actions
			add_action( 'init', array( $this, 'mijireh_notification' ) );
			add_action('wpdeals_update_options_payment_gateways', array( $this, 'process_admin_options'));
	  		add_action('add_meta_boxes', array( $this, 'add_page_slurp_meta'));
	  		add_action('wp_ajax_page_slurp', array( $this, 'page_slurp'));
	
		}
		
		/**
		 * mijireh_notification function.
		 *
		 * @access public
		 * @return void
		 */
		public function mijireh_notification() {
		    if( isset( $_GET['order_number'] ) ) {
		  		global $wpdeals;
		
		  		$this->init_mijireh();
		
		  		try {
		  		      $mj_order 	= new Mijireh_Order( esc_attr( $_GET['order_number'] ) );
					  
		  		      $order_id 	= $mj_order->get_meta_value( 'wpd_order_id' );
					  
		  		      $order 	= new wpdeals_order( absint( $order_id ) );
		
		  		      // Mark order complete
		  		      $order->payment_complete();
		
		  		      // Empty cart and clear session
		  		      $wpdeals->cart->empty_cart();
		
		  		      wp_redirect( $this->get_return_url( $order ) );
		  		      exit;
		
		  		} catch (Mijireh_Exception $e) {
		
		  			$wpdeals->add_error( __( 'Mijireh Error: ', 'patsatech-wpdeals-mijireh' ) . $e->getMessage() );
					
					$get_checkout_url = apply_filters( 'wpdeals_get_checkout_url', $wpdeals->cart->get_checkout_url() );
		
					wp_redirect( $get_checkout_url );
		  		    exit;
		
		  		}
		   
		    }elseif( isset( $_POST['page_id'] ) ) {
		    	
				if( isset( $_POST['access_key'] ) && $_POST['access_key'] == $this->access_key ) {
				
		        	wp_update_post( array( 'ID' => $_POST['page_id'], 'post_status' => 'private' ) );
					
		      	}
			}
		}
		
	
	
	    /**
	     * Initialise Gateway Settings Form Fields
	     *
	     * @access public
	     * @return void
	     */
	    public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'patsatech-wpdeals-mijireh' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Mijireh Checkout', 'patsatech-wpdeals-mijireh' ),
					'default' => 'no'
					),
				'access_key' => array(
					'title' => __( 'Access Key', 'patsatech-wpdeals-mijireh' ),
					'type' => 'text',
					'description' => __( 'The Mijireh access key for your store.', 'patsatech-wpdeals-mijireh' ),
					'default' => '',
					'desc_tip'      => true,
					),
				'title' => array(
					'title' => __( 'Title', 'patsatech-wpdeals-mijireh' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'patsatech-wpdeals-mijireh' ),
					'default' => __( 'Credit Card', 'patsatech-wpdeals-mijireh' ),
					'desc_tip'      => true,
					),
				'description' => array(
					'title' => __( 'Description', 'patsatech-wpdeals-mijireh' ),
					'type' => 'textarea',
					'default' => __( 'Pay securely with your credit card.', 'patsatech-wpdeals-mijireh' ),
					'description' => __( 'This controls the description which the user sees during checkout.', 'patsatech-wpdeals-mijireh' ),
					),
			);
	    }
	
	
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @access public
		 * @return void
		 */
	  	public function admin_options() {
			?>
			<h3><?php _e( 'Mijireh Checkout', 'patsatech-wpdeals-mijireh' );?></h3>

			<p><a href="http://www.mijireh.com">Mijireh Checkout</a> <?php _e( 'provides a fully PCI Compliant, secure way to collect and transmit credit card data to your payment gateway while keeping you in control of the design of your site.', 'patsatech-wpdeals-mijireh' ); ?></p>
	
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table><!--/.form-table-->
			<?php
	  	}
	
	    /**
		 * There are no payment fields for payza, but we want to show the description if set.
		 **/
	    function payment_fields() {
	    	if ($this->description) echo wpautop(wptexturize($this->description));
	    }
	
	    /**
	     * Process the payment and return the result
	     *
	     * @access public
	     * @param int $order_id
	     * @return array
	     */
	    public function process_payment( $order_id ) {
			global $wpdeals;
	
			$this->init_mijireh();
	
			$mj_order = new Mijireh_Order();
			
			$order = new wpdeals_order( $order_id );
			
			// Cart Contents
            $item_loop = 0;
            if (sizeof($order->items)>0){
			
				foreach ($order->items as $item){
            		
					if ($item['qty']) {

                    	$item_loop++;

                        $item_name = $item['name'];

                        $item_meta = &new order_item_meta( $item['item_meta'] );
											
                        if ($meta = $item_meta->display( true, true )){
                        	$item_name .= ' ('.$meta.')';
						}
						
						$mj_order->add_item( $item_name, number_format($item['cost'], 2, '.', ''), $item['qty'], '' );
						
					}
					
				}
				
			}
	
			// set order name
			$mj_order->email		= $order->user_email;
	
			// set order totals
			$mj_order->total		= $order->order_total;
	
			// add meta data to identify wpdeals_mijireh order
			$mj_order->add_meta_data( 'wpd_order_id', $order_id );
	
			// Set URL for mijireh payment notification - use WC API
			$mj_order->return_url	= str_replace( 'https:', 'http:', add_query_arg( 'mijireh', 'ipn', home_url( '/' ) ) );
	
			// Identify PatSaTECH
			$mj_order->partner_id	= 'patsatech';
			
			try {
				$mj_order->create();
				$result = array(
					'result' => 'success',
					'redirect' => $mj_order->checkout_url
				);
				return $result;
			} catch (Mijireh_Exception $e) {
				$wpdeals->add_error( __('Mijireh Error: ', 'patsatech-wpdeals-mijireh' ) . $e->getMessage() );
			}
			
	    }
	
	
		/**
		 * init_mijireh function.
		 *
		 * @access public
		 */
		public function init_mijireh() {
			if ( ! class_exists( 'Mijireh' ) ) {
		    	require_once 'mijireh/Mijireh.php';
	
		    	if ( ! isset( $this ) ) {
			    	$settings = get_option( 'wpdeals_mijireh_settings', null );
			    	$key = ! empty( $settings['access_key'] ) ? $settings['access_key'] : '';
		    	} else {
			    	$key = $this->access_key;
		    	}
	
		    	Mijireh::$access_key = $key;
		    }
		}
	
	
	    /**
	     * page_slurp function.
	     *
	     * @access public
	     * @return void
	     */
	    public function page_slurp() {
		
	    	self::init_mijireh();
	
			$page 	= get_page( absint( $_POST['page_id'] ) );
			$url 	= get_permalink( $page->ID );
	    	$job_id = $url;
			if ( wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'publish' ) ) ) {
			  $job_id = Mijireh::slurp( $url, $page->ID, str_replace( 'https:', 'http:', add_query_arg( 'mijireh', 'ipn', home_url( '/' ) ) ) );
	    }
			echo $job_id;
			die;
		}
	    
	
	
	    /**
	     * add_page_slurp_meta function.
	     *
	     * @access public
	     * @return void
	     */
	    public function add_page_slurp_meta() {
	    	global $wpdeals;
	
	    	if ( self::is_slurp_page() ) {
	        	wp_enqueue_style( 'mijireh_css', WP_PLUGIN_URL . '/'. plugin_basename( dirname(__FILE__)) .'/assets/css/mijireh.css' );
	        	wp_enqueue_script( 'pusher', 'https://d3dy5gmtp8yhk7.cloudfront.net/1.11/pusher.min.js', null, false, true );
	        	wp_enqueue_script( 'page_slurp', WP_PLUGIN_URL . '/'. plugin_basename( dirname(__FILE__)) .'/assets/js/page_slurp.js', array('jquery'), false, true );
	
				add_meta_box(
					'slurp_meta_box', 		// $id
					'Mijireh Page Slurp', 	// $title
					array( 'wpdeals_mijireh', 'draw_page_slurp_meta_box' ), // $callback
					'page', 	// $page
					'normal', 	// $context
					'high'		// $priority
				);
			}
	    }
	
	
	    /**
	     * is_slurp_page function.
	     *
	     * @access public
	     * @return void
	     */
	    public function is_slurp_page() {
			global $post;
			$is_slurp = false;
			if ( isset( $post ) && is_object( $post ) ) {
				$content = $post->post_content;
				if ( strpos( $content, '{{mj-checkout-form}}') !== false ) {
					$is_slurp = true;
				}
			}
			return $is_slurp;
	    }
	
	
	    /**
	     * draw_page_slurp_meta_box function.
	     *
	     * @access public
	     * @param mixed $post
	     * @return void
	     */
	    public function draw_page_slurp_meta_box( $post ) {
	    	global $wpdeals;
	
	    	self::init_mijireh();
			
			$settings = get_option( 'wpdeals_mijireh_settings', null );
	
			echo "<div id='mijireh_notice' class='mijireh-info alert-message info' data-alert='alert'>";
			echo    "<h2>Slurp your custom checkout page!</h2>";
			echo    "<p>Get the page designed just how you want and when you're ready, click the button below and slurp it right up.</p>";
			echo    "<div id='slurp_progress' class='meter progress progress-info progress-striped active' style='display: none;'><div id='slurp_progress_bar' class='bar' style='width: 20%;'>Slurping...</div></div>";
			
			if(!empty($settings['access_key'])){
			
				echo    "<p><a href='#' id='page_slurp' rel=". $post->ID ." class='button-primary'>Slurp This Page!</a> ";
				echo    '<a class="nobold" href="https://secure.mijireh.com/checkout/' . $settings['access_key'] . '" id="view_slurp" target="_new">Preview Checkout Page</a></p>';
				
			}else{
			
				echo '<p style="color:red;font-size:15px;text-shadow: none;"><b>Please enter you Access Key in Mijireh Settings. <a class="nobold" target="_blank" href="' . home_url('/wp-admin/admin.php?page=jigoshop_settings&tab=payment-gateways') . '" id="view_slurp" target="_new">Enter Access Key</a></b></p>';
				
			}
			
			echo  "</div>";
			
	    }
	}

	/**
	 * Add the gateway to WP Deals
	 **/
	function add_mijireh_gateway( $methods ) {
		$methods[] = 'wpdeals_mijireh'; return $methods;
	}
	
	add_filter('wpdeals_payment_gateways', 'add_mijireh_gateway' );
}