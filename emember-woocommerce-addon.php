<?php 
/**
 * Plugin Name: eMember WooCommerce Addon
 * Plugin URI: http://www.tipsandtricks-hq.com/wordpress-emember-easy-to-use-wordpress-membership-plugin-1706
 * Description: eMember Addon that allows you to accept membership payment via WooCommerce
 * Version: 1.1
 * Author: Tips and Tricks HQ
 * Author URI: http://www.tipsandtricks-hq.com/
 * Requires at least: 3.0
*/

if (!defined('ABSPATH')) exit;

//Add the meta box in the woocommerce product add/edit interface
add_action( 'add_meta_boxes', 'emember_woo_meta_boxes' );
function emember_woo_meta_boxes()
{
	add_meta_box( 'emember-woo-product-data', 'WP eMember Membership', 'emember_woo_membership_level_data_box', 'product', 'normal', 'high' );
}
function emember_woo_membership_level_data_box($wp_post_obj)
{
	$level_id = get_post_meta( $wp_post_obj->ID, 'emember_woo_product_level_id', true );
	echo "Membership Level ID: ";
	echo '<input type="text" size="10" name="emember_woo_product_level_id" value="'.$level_id.'" />';
	echo '<p>A membership account with the specified level ID will be created for the user who purchase this product.</p>';
}


//Save the membership level data to the post meta with the product when it is saved
add_action('save_post', 'emember_woo_save_product_data', 10, 2 );
function emember_woo_save_product_data($post_id, $post_obj )
{
    // Check post type for woocommerce product
    if ( $post_obj->post_type == 'product' ) {
        // Store data in post meta table if present in post data
        if ( isset( $_POST['emember_woo_product_level_id'] )) {
            update_post_meta( $post_id, 'emember_woo_product_level_id', $_POST['emember_woo_product_level_id'] );
        }
    }
}

//Handle membership creation after the transaction (if needed)
add_action('woocommerce_order_status_completed','emember_woo_handle_woocommerce_payment');//Executes when a status changes to completed
add_action('woocommerce_checkout_order_processed','emember_woo_handle_woocommerce_payment');
function emember_woo_handle_woocommerce_payment($order_id)
{
	eMember_log_debug("WooCommerce emember integration - Order processed... checking if member account needs to be created or updated.",true);
	$order = new WC_Order($order_id);

	$order_status = $order->status;
	eMember_log_debug("WooCommerce emember integration - Order status: ".$order_status,true);
	if($order_status != "completed" && $order_status != "Processing"){
		eMember_log_debug("WooCommerce emember integration - Order status for this transaction is not in a 'completed' or 'processing' state. Membership update won't be handled at this stage.",true);
		eMember_log_debug("The membership account creation or update for this transaciton will be handled when you set the order status to completed.",true);
		return;
	}

	$ipn_data = array();
	$ipn_data['first_name'] = $order->billing_first_name;
	$ipn_data['last_name'] = $order->billing_last_name;
	$ipn_data['payer_email'] = $order->billing_email;
	$ipn_data['address_street'] = $order->billing_address_1;
	$ipn_data['address_city'] = $order->billing_city;
	$ipn_data['address_state'] = $order->billing_state;
	$ipn_data['address_zip'] = $order->billing_postcode;
	$ipn_data['address_country'] = $order->billing_country;
	$subscr_id = $order_id;//The txn_id
	
	$order_items = $order->get_items();
	foreach ( $order_items as $item_id => $item ) 
	{
		if($item['type'] == 'line_item') 
		{
			$_product = $order->get_product_from_item( $item );
			$post_id = $_product->id;
			$level_id = get_post_meta( $post_id, 'emember_woo_product_level_id', true );
			if(!empty($level_id)){
				eMember_log_debug("Membership Level ID (".$level_id.") is present in this product. Processing membership account related tasks...",true);
				include_once(WP_EMEMBER_PATH.'ipn/eMember_handle_subsc_ipn_stand_alone.php');
				eMember_handle_subsc_signup_stand_alone($ipn_data,$level_id,$subscr_id);
				return;
			}
		}
	}

}

