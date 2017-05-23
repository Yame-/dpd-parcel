<?php
/**
 * Plugin Name: DPD Service for WooCommerce
 * Plugin URI: http://yame.be/plugins/dpd
 * Description: Enables the posibility to integrate DPD Parcel Shop Finder service into your e-commerce store with a breeze.
 * Version: 1.3.3
 * Author: Yame
 * Author URI: http://yame.be/
 * License: GPL
 * Text Domain: dpd-service-for-woocommerce
 * Domain Path: /languages
 */

// Prevent direct file access
defined( 'ABSPATH' ) or exit;

// Set our domain
define('DPD_SERVICE_DOMAIN', 'dpd-service-for-woocommerce');

// Check if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	// Add gmap js
	function dpd_load_scripts()
	{
		$dpd_options = get_option('woocommerce_dpd_Service_settings');
		wp_enqueue_script( 'dpd-service-google-api', 'https://maps.googleapis.com/maps/api/js?sensor=false&key=' . $dpd_options['gmaps_api_key'] );
		wp_register_script( 'dpd-service-map', plugins_url( '/js/dpd-service-gmap.js', __FILE__ ), array('jquery'), '1.0', true );
	    if ( is_checkout() ) {
	    	wp_enqueue_script( 'dpd-service-map');
		}
	    wp_enqueue_style( 'dpd-service-style', plugins_url( '/style.css', __FILE__ ) );
	}
	add_action( 'wp_enqueue_scripts', 'dpd_load_scripts' );

	function load_custom_wp_admin_style() {
	        wp_register_style( 'custom_wp_admin_css', plugins_url( '/css/dpd-admin.css', __FILE__ ), false, '1.0.0' );
	        wp_enqueue_style( 'custom_wp_admin_css' );
	}
	add_action( 'admin_enqueue_scripts', 'load_custom_wp_admin_style' );

	// Translations
	function my_plugin_load_plugin_textdomain() {
	    $lel = load_plugin_textdomain( DPD_SERVICE_DOMAIN, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}
	add_action( 'plugins_loaded', 'my_plugin_load_plugin_textdomain' );

	// Add shipping service
	function dpd_service(){
		if ( ! class_exists( 'DPD_Service' ) ) {
			include 'dpd-service.class.php';
		}	
	}
	add_action( 'woocommerce_shipping_init', 'dpd_service' );

	// Register shipping method
	function add_dpd_service( $methods ) {
		$methods[] = 'dpd_service'; 
		return $methods;
	}
	add_filter( 'woocommerce_shipping_methods', 'add_dpd_service' );

	// Calculate distance function
	function distance($lat1, $lon1, $lat2, $lon2, $unit) {

	  $theta = $lon1 - $lon2;
	  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
	  $dist = acos($dist);
	  $dist = rad2deg($dist);
	  $miles = $dist * 60 * 1.1515;
	  $unit = strtoupper($unit);

	  if ($unit == "K") {
	    return ($miles * 1.609344);
	  } else if ($unit == "N") {
	      return ($miles * 0.8684);
	    } else {
	        return $miles;
	      }
	}

	// Generate JS to inject found parcels into Google Map
	function buildParcelShopsJS($js_script, $center){
		$i = 0;

		$js = '<script>
		var markers = [];
		var prev = "";
		var infoWindow = new google.maps.InfoWindow();
		var icon = {
			url: "'.plugins_url('/img/DPD_ParcelShop_icon.png',__FILE__).'", // url
		    /*size: new google.maps.Size(50, 40), // size*/
		    origin: new google.maps.Point(0,0), // origin
		    anchor: new google.maps.Point(0, 0) // anchor 
		}
		function addDPDMarkers(map_){';

		$js .= '
		markers.push(marker_center = new google.maps.Marker({
			position: {lat: '.$center['lat'].', lng: '.$center['lng'].'},
			map: map_,
			title: "'.__('Your place', DPD_SERVICE_DOMAIN).'",
		}));
		';

		// Markers
		foreach($js_script as $s){
			$js .='
			markers.push(marker_'.$i.' = new google.maps.Marker({
	          position: {lat: '.$s->latitude.', lng: '.$s->longitude.'},
	          map: map_,
	          title:  "'.$s->company.'",
	          icon: icon
	        }));

			var contentString_'.$i.' = "<div class=\'informationWindow\'>" +
			"<h2>'.$s->company.'</h2>" +
			"<p class=\'parcelAddress\'>' .$s->street. ', '.$s->zipCode.' ' .$s->city. '</p>" +';

			$closed = array();
			$open_ = '';

			foreach( $s->openingHours as $open ){

				if( empty($open->openMorning) && empty($open->closeAfternoon) ){
					$closed[] = $open->weekday;
				} else {

					// Morning
					if( empty( $open->openMorning ) && empty( $open->closeMorning )) { // Morning closed
						$morning = '';
					} elseif ( !empty( $open->openMorning ) && empty( $open->closeMorning ) ){
						$morning = $open->openMorning;
					} else {
						$morning = $open->openMorning . ' - ' . $open->closeMorning;
					}

					// Afternoon
					if( empty( $open->openAfternoon ) && empty( $open->closeAfternoon )) { // Morning closed
						$afternoon = '';
					} elseif ( !empty( $open->openAfternoon ) && empty( $open->closeAfternoon ) ){
						$afternoon = $open->openAfternoon;
					} else {
						$afternoon = $open->openAfternoon . ' - ' . $open->closeAfternoon;
					}

					if( empty( $morning ) ){
						$openingHours = $afternoon;
					} elseif ( empty( $afternoon ) ){
						$openingHours = $morning;
					} else {
						$openingHours = $morning . ' ' . $afternoon;
					}

					$open_ .= '"<div class=\'openingHour\'><div class=\'day\'>'. $open->weekday .'</div><div class=\'hour\'>' .$openingHours. '</div></div>" + ';
				}

			}

			$js .= '"<div class=\'parcelClosed\'> '.__('Closed on ', DPD_SERVICE_DOMAIN).' '.implode(', ', $closed).'</div>" + ';
			$js .= $open_;

			$js .= '"<a href=\'#\' class=\'selectDPDParcel\' data-parcelID=\''.$s->parcelShopId.'\' data-street=\''.$s->street.'\' data-city=\''.$s->city.'\' data-postcode=\''.$s->zipCode.'\' data-name=\''.$s->company.'\'>'.__('Deliver my parcel here', 'DPD_SERVICE_DOMAIN').'</a>" +
			"</div>";

	        marker_'.$i.'.addListener("click", function() {
	          infoWindow.close();
	          infoWindow.setContent( contentString_'.$i.' );
	          infoWindow.open(map_, marker_'.$i.');
	        });
			';
			
			$i++;
		}	

		// Set center
		$js .= 'var bounds = new google.maps.LatLngBounds();
		for (var i = 0; i < markers.length; i++) {
			bounds.extend(markers[i].getPosition());
		}
		map_.fitBounds(bounds);';

		$js .= '}</script>';
		return $js;
	}

	// Add DPD Table
	function add_dpd_service_table(){
		global $woocommerce;

		$shipping_method 	= $woocommerce->session->get('chosen_shipping_methods');
		$postcode 			= $woocommerce->customer->get_postcode();

		if( $shipping_method[0] == 'dpd_service' ){

			if( !$postcode ){
				echo '<tr><td colspan="2"><p>'.__('Please enter your address before selecting a Parcel.', DPD_SERVICE_DOMAIN).'</p></td></tr>';
			} else {

				echo '<tr><td colspan="2">';

				require_once 'classes/Cache.php';
				require_once 'classes/Logger.php';
				require_once 'classes/ParcelShopFinder.php';
				require_once 'classes/Shipment.php';
				require_once 'classes/Login.php';

				$dpd_options = get_option('woocommerce_dpd_Service_settings');

				$dpd_login = new DisLogin($dpd_options['api_username'], $dpd_options['api_password'], $dpd_options['api']);
				$dpd_parcel = new DisParcelShopFinder($dpd_login);

				$result = $dpd_parcel->search(

					array(
						'Street' => $woocommerce->customer->get_shipping_address(),
						'HouseNo' => '',
						'Country' => $woocommerce->customer->get_shipping_country(),
						'ZipCode' => $woocommerce->customer->get_shipping_postcode(),
						'City' => $woocommerce->customer->get_shipping_city(),
					)

				);
				/* Login for every req, should check if token is set => BAD */
				?>
				<h3 id="order_review_heading"><?= __('Select your DPD Parcel Shop', DPD_SERVICE_DOMAIN); ?></h3>

				<p class="address_error"><?= __('We were not able to locate your address, please try another one.', DPD_SERVICE_DOMAIN) ?></p>

				<a href="#" class="openDPDParcelMap"><?= __('Choose your DPD Parcel Shop', DPD_SERVICE_DOMAIN) ?></a>
				<a href="#" class="otherDPDParcel"><?= __('Choose another DPD Parcel Shop', DPD_SERVICE_DOMAIN) ?></a>

				<div class="chosenParcel">
				</div>

				<script>
				jQuery(document).ready(function($){
					if( $('#map').length == 0 ){
						$('body').prepend('<div id="overlay"><div id="map"></div></div>');
					}
				});
				</script>

				<?php // Loop through found parcels for the address, feed them to JS also
				if( count($result->shops) > 0): ?>

					<?php // Loop and set distance ?> 
					<?php foreach( $result->shops as $shop ): ?>
						<?php $shop->distance = distance($result->center->lat, $result->center->lng, $shop->latitude, $shop->longitude, 'K'); ?>
					<?php endforeach; ?>

					<?php
					usort($result->shops, function($a, $b) {
					    return round($a->distance) - round($b->distance);
					});?>

					<?php
					$js_script = array();
					?>
			
					<div class="dpd_service_shops">
					<?php foreach( $result->shops as $shop ): ?>

						<?php
						$js_script[] = $shop;
						?>

						<div class="dpd_service_shop" id="<?=$shop->parcelShopId?>">
						<h4><label for="dpdParcel"><div class="pull-left"><?= $shop->company; ?> | <?= round($shop->distance,2) ?>km </label></div><div class="pull-right"><input type="radio" data-parcelID="<?=$shop->parcelShopId?>" data-street="<?=$shop->street?>" data-name="<?=$shop->company?>" data-city="<?=$shop->city?>" data-postcode="<?=$shop->zipCode?>" name="dpdParcel" class="selectDPDParcelradio" value="<?=$shop->parcelShopId?>"></div></h4>
						<p>
							<?= $shop->street; ?><br>
							<?= $shop->zipCode; ?> <?= $shop->city; ?>
						</p>
						</div>

					<?php endforeach; ?>
					</div>

					<input type="hidden" name="chosenParcelShopID" value="">
					<input type="hidden" name="chosenParcelShop_street" value="">
					<input type="hidden" name="chosenParcelShop_city" value="">
					<input type="hidden" name="chosenParcelShop_postcode" value="">
					<input type="hidden" name="chosenParcelShop_name" value="">


					<?php
					// Inject found parcels into Google Map
					echo buildParcelShopsJS($js_script, array('lat' => $result->center->lat, 'lng' => $result->center->lng));
					?>

				<?php endif; ?>

				<!--<pre>
					<?php print_r($result); ?>
				</pre>-->
				<?php
				echo '</td></tr>';
			}
		}
	}
	add_action('woocommerce_review_order_after_order_total', 'add_dpd_service_table');

	// Form submit and validation 
	function dpd_service_save_dpd_parcel_shop( $order_id ) {
		global $woocommerce; 

		if ( ! empty( $_POST['dpdParcel'] ) ) {
	    	update_post_meta( $order_id, 'dpd_service_parcelID', sanitize_text_field( $_POST['dpdParcel'] ) );
		}

	    if ( ! empty( $_POST['chosenParcelShopID'] ) ) {
	        update_post_meta( $order_id, 'dpd_service_parcelID', sanitize_text_field( $_POST['chosenParcelShopID'] ) );
	    }

	    if( ! empty( $_POST['dpdParcel'] ) || ! empty( $_POST['chosenParcelShopID'] ) ){

	    	update_post_meta( $order_id, 'dpd_service_street', sanitize_text_field( $_POST['chosenParcelShop_street'] ) );
	    	update_post_meta( $order_id, 'dpd_service_postcode', sanitize_text_field( $_POST['chosenParcelShop_postcode'] ) );
	    	update_post_meta( $order_id, 'dpd_service_city', sanitize_text_field( $_POST['chosenParcelShop_city'] ) );
	    	update_post_meta( $order_id, 'dpd_service_name', sanitize_text_field( $_POST['chosenParcelShop_name'] ) );

	    }
	}
	add_action( 'woocommerce_checkout_update_order_meta', 'dpd_service_save_dpd_parcel_shop' );
	
	/*
	* Validates if a DPD Parcel shop has been selected
	*/
	function dpd_service_parcel_shop_validation() {
		global $woocommerce; 

		// If chosen DPD and parcel id is not found in hidden field or radio button, we show error
		if($_POST['shipping_method'][0] == 'dpd_service' && (!$_POST['chosenParcelShopID'] && !$_POST['dpdParcel'])){
			wc_add_notice( __( 'Please choose a <strong>DPD Parcel Shop</strong>', DPD_SERVICE_DOMAIN ), 'error' );
		}
	}
	add_action('woocommerce_checkout_process', 'dpd_service_parcel_shop_validation');

	// Add chosen DPD Parcel Shop to Order Details
	function dpd_service_show_id_on_order_details($post){
		?>
		
		<h2><?php echo __('Chosen DPD Parcel Shop', DPD_SERVICE_DOMAIN);?></h2>
		<p>
		<strong><?=get_post_meta($post->id, 'dpd_service_name', true)?></strong><br>
		<?=get_post_meta($post->id, 'dpd_service_street', true)?><br>
		<?=get_post_meta($post->id, 'dpd_service_postcode', true)?> <?=get_post_meta($post->id, 'dpd_service_city', true)?>
		</p>
		<style>
		.addresses .col-2 {
			display: none;
		}
		</style>
		<?php
	}
	add_action('woocommerce_order_details_after_order_table', 'dpd_service_show_id_on_order_details');

	// DPD Shipping Label creation on Order Dashboard
	function dpd_service_add_shipping_label_box(){
		global $post;

		if( $post->post_type != 'shop_order' ){
			return;
		}

		$current_order = new WC_Order( $post->ID );

		$dpd_options = get_option('woocommerce_dpd_Service_settings');

		if( $shipping_method == 'dpd_service' || $dpd_options['print_label'] == 'yes' ){
			add_meta_box( 'meta-box-id', __( 'DPD Shipping Label', 'dpd_service' ), 'dpd_service_create_label_link', 'shop_order', 'side', 'high');
		}

    	function dpd_service_create_label_link(){
    		global $post;

    		$current_order = new WC_Order( $post->ID );

    		$address = $current_order->get_address();
    		$shipping = $current_order->get_address('shipping');

    		$weight = 0;
			if ( sizeof( $current_order->get_items() ) > 0 ) {
				foreach( $current_order->get_items() as $item ) {
					if ( $item['product_id'] > 0 ) {
						$_product = $current_order->get_product_from_item( $item );
						if ( ! $_product->is_virtual() ) {
							$weight += $_product->get_weight() * $item['qty'];
						}
					}
				}
			}

			$shipping_method = $current_order->get_items('shipping');
			foreach( $shipping_method as $el ){
				$shipping_id = $el['method_id'];
			}
			$shipping_method = $shipping_id;

			$parcel = ($shipping_method == 'dpd_service') ? 'yes' : 'no';

    		$url = add_query_arg(array(
    			'page' 			=> 'dpd_download_shipment_label',
    			'name'			=> $address['first_name'] . ' ' . $address['last_name'],
    			'parcel_id' 	=> get_post_meta($post->ID, 'dpd_service_parcelID', true),
    			'email' 		=> urlencode($address['email']),
    			'phone' 		=> urlencode($address['phone']),
    			'country' 		=> urlencode($shipping['country']),
    			'street' 		=> urlencode($shipping['address_1']),
    			'city' 			=> $shipping['city'],
    			'postcode' 		=> $shipping['postcode'],
    			'country' 		=> $shipping['country'],
    			'email' 		=> $address['email'],
    			'phone' 		=> $address['phone'],
    			'weight'		=> wc_get_weight( $weight, 'g' ) / 10,
    			'order_id'		=> $post->ID,
    			'parcel'		=> $parcel,

    		),admin_url());
    		?>
		
    		<a href="<?=$url?>" class="postDPDLabel" target="_blank"><?= __('Download DPD Shipment Label', DPD_SERVICE_DOMAIN) ?></a>
    		<script>
    		</script>
    		<?php
    	}
	}
	add_action('add_meta_boxes', 'dpd_service_add_shipping_label_box');

	// DPD Shipping Label creation on Order Dashboard
	function dpd_service_add_shipping_return_label_box(){
		global $post;

		if( $post->post_type != 'shop_order' ){
			return;
		}

		$current_order = new WC_Order( $post->ID );

		$dpd_options = get_option('woocommerce_dpd_Service_settings');

		if( $shipping_method == 'dpd_service' || $dpd_options['print_label'] == 'yes' ){
			add_meta_box( 'meta-box-id-2', __( 'DPD Return Label', 'dpd_service' ), 'dpd_service_create_return_label_link', 'shop_order', 'side', 'high');
		}

    	function dpd_service_create_return_label_link(){
    		global $post;

    		$current_order = new WC_Order( $post->ID );

    		$address = $current_order->get_address();
    		$shipping = $current_order->get_address('shipping');

    		$weight = 0;
			if ( sizeof( $current_order->get_items() ) > 0 ) {
				foreach( $current_order->get_items() as $item ) {
					if ( $item['product_id'] > 0 ) {
						$_product = $current_order->get_product_from_item( $item );
						if ( ! $_product->is_virtual() ) {
							$weight += $_product->get_weight() * $item['qty'];
						}
					}
				}
			}

			$shipping_method = $current_order->get_items('shipping');
			foreach( $shipping_method as $el ){
				$shipping_id = $el['method_id'];
			}
			$shipping_method = $shipping_id;

			$parcel = ($shipping_method == 'dpd_service') ? 'yes' : 'no';

    		$url = add_query_arg(array(
    			'page' 			=> 'dpd_download_shipment_label',
    			'name'			=> $address['first_name'] . ' ' . $address['last_name'],
    			'parcel_id' 	=> get_post_meta($post->ID, 'dpd_service_parcelID', true),
    			'email' 		=> urlencode($address['email']),
    			'phone' 		=> urlencode($address['phone']),
    			'country' 		=> urlencode($shipping['country']),
    			'street' 		=> urlencode($shipping['address_1']),
    			'city' 			=> $shipping['city'],
    			'postcode' 		=> $shipping['postcode'],
    			'country' 		=> $shipping['country'],
    			'email' 		=> $address['email'],
    			'phone' 		=> $address['phone'],
    			'weight'		=> wc_get_weight( $weight, 'g' ) / 10,
    			'order_id'		=> $post->ID,
    			'parcel'		=> $parcel,
    			'return'		=> 'yes'

    		),admin_url());
    		?>
		
    		<a href="<?=$url?>" class="postDPDLabel" target="_blank"><?= __('Download DPD Return Label', DPD_SERVICE_DOMAIN) ?></a>
    		<script>
    		</script>
    		<?php
    	}
	}
	add_action('add_meta_boxes', 'dpd_service_add_shipping_return_label_box');

	function dpd_service_add_shipping_details_admin(){
		global $post;
		$current_order = new WC_Order( $post->ID );

		$shipping_method = $current_order->get_items('shipping');
		foreach( $shipping_method as $el ){
			$shipping_id = $el['method_id'];
		}
		$shipping_method = $shipping_id;

		if( $shipping_method == 'dpd_service' ){
		?>
		<h4><?=__('Shipment via DPD ParcelShop', DPD_SERVICE_DOMAIN);?></h4>
		<strong><?=get_post_meta($post->ID, 'dpd_service_name', true)?></strong><br>
		<?=get_post_meta($post->ID, 'dpd_service_street', true)?><br>
		<?=get_post_meta($post->ID, 'dpd_service_postcode', true)?> <?=get_post_meta($post->ID, 'dpd_service_city', true)?>
		<style>
		.order_data_column:last-child .address {
		    display: none;
		}
		</style>
		<?php
		}
	}
	add_action('woocommerce_admin_order_data_after_shipping_address', 'dpd_service_add_shipping_details_admin');

	/*function is_dpd_service_shipping_method($order){
		$current_order = new WC_Order( $order->id );

		$shipping_method = $current_order->get_items('shipping');
		foreach( $shipping_method as $el ){
			$shipping_id = $el['method_id'];
		}
		return $shipping_id;
	}*/

	function dpd_service_add_shipping_details_email( $order, $is_admin ){
		//global $post;
		$current_order = new WC_Order( $order->id );

		$shipping_method = $current_order->get_items('shipping');
		foreach( $shipping_method as $el ){
			$shipping_id = $el['method_id'];
		}
		$shipping_method = $shipping_id;

		if( $shipping_method == 'dpd_service' ){
		?>
		<h2><?=__('Shipment via DPD ParcelShop', DPD_SERVICE_DOMAIN);?></h2>
		<strong><?=get_post_meta($order->id, 'dpd_service_name', true)?></strong><br>
		<?=get_post_meta($order->id, 'dpd_service_street', true)?><br>
		<?=get_post_meta($order->id, 'dpd_service_postcode', true)?> <?=get_post_meta($order->id, 'dpd_service_city', true)?>
		<?php
		}
	}
	add_action('woocommerce_email_after_order_table', 'dpd_service_add_shipping_details_email', 10, 2);

	function dpd_service_add_shipment_label_download_page(){
		add_submenu_page(null, 'dpd_download_shipment_label', 'dpd_download_shipment_label', 'read', 'dpd_download_shipment_label');
	}
	add_action('admin_menu', 'dpd_service_add_shipment_label_download_page');

	if( isset($_GET['page']) && $_GET['page'] == 'dpd_download_shipment_label' ){
		include 'dpd-service-download-shipment-label.php';
	}

	// Hide shipping address on parcel emails
	function remove_shipping_address_in_email( $shipping_methods ){
		$shipping_methods[] = 'dpd_service';
		return $shipping_methods;
	}           
	add_filter('woocommerce_order_hide_shipping_address', 'remove_shipping_address_in_email');

	// Change address when parcel method
	function change_address_parcel_method($address, $order){

		$current_address = $order->get_formatted_billing_address();

		// YES! Let's change output address
		if( get_post_meta($order->id, 'dpd_service_parcelID', true) ){
			$address = array(
				'first_name'    => $current_address->billing_first_name,
				'last_name'     => $current_address->billing_last_name,
				'company'       => get_post_meta($order->id, 'dpd_service_name', true),
				'address_1'     => get_post_meta($order->id, 'dpd_service_street', true),
				'address_2'     => $current_address->billing_address_2,
				'city'          => get_post_meta($order->id, 'dpd_service_city', true),
				'state'         => $current_address->billing_state,
				'postcode'      => get_post_meta($order->id, 'dpd_service_postcode', true),
				'country'       => $current_address->billing_country
			);

			return $address;
		} else {
			return $address;
		}
	}
	add_filter('woocommerce_order_formatted_shipping_address', 'change_address_parcel_method', 10, 2);

}