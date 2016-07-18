<?php

class DPD_Service extends WC_Shipping_Method {

	public function __construct() {
		$this->id                 	= 'dpd_Service';
		//$this->title       			= __('DPD Service', DPD_SERVICE_DOMAIN);
		$this->method_title 		= __('DPD Service', DPD_SERVICE_DOMAIN);
		$this->method_description 	= __( 'DPD Parcel Finder', DPD_SERVICE_DOMAIN );
		$this->countries 	  		= $this->id.'_countries';

		$this->init();
	}

	function init() {
		// Load the settings API
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		$this->enabled			= $this->get_option( 'enabled' );
		$this->title 			= $this->get_option( 'title' );
		$this->api 				= $this->get_option( 'api' );
		$this->api_username 	= $this->get_option( 'api_username' );
		$this->api_password 	= $this->get_option( 'api_password' );
		$this->print_label		= $this->get_option( 'print_label' );
		$this->base_cost 		= $this->get_option( 'base_cost' );
		$this->free_shipping	= $this->get_option( 'free_shipping' );
		$this->sending_depot 	= $this->get_option( 'sending_depot' );

		$this->company_name		= $this->get_option('company_name');
		$this->company_street 	= $this->get_option('company_street');
		//$this->company_country	= $this->get_option('company_country');
		$this->company_postcode	= $this->get_option('company_postcode');
		$this->company_city		= $this->get_option('company_city');

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_countries' ) );	
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_api' ) );		
	}

	/* 
	* Custom settings Kiala
	*/
	function init_form_fields(){
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable', DPD_SERVICE_DOMAIN),
				'type' => 'checkbox',
				'default' => true,
				'label' => __('Enable DPD Service', DPD_SERVICE_DOMAIN)
			),
			'api' => array(
				'title' => __('API', DPD_SERVICE_DOMAIN),
				'type' => 'select',
				'default' => 'Development (testing)',
				'label' => __('This tells the DPD Library if we are testing or in production',DPD_SERVICE_DOMAIN),
				'desc_tip' => true,
				'options' => array(
					'https://public-dis-stage.dpd.nl/Services/' => __('Development (testing)',DPD_SERVICE_DOMAIN),
					'https://public-dis.dpd.nl/Services/' => __('Production (live)',DPD_SERVICE_DOMAIN),
				),
			),
			'api_username' => array(
				'title' => 'DPD Username',
				'type' => 'text',
				'label' => __('This is the username you got from DPD to login to their service', DPD_SERVICE_DOMAIN),
				'desc_tip' => true,
			),
			'api_password' => array(
				'title' => 'DPD Password',
				'type' => 'password',
				'label' => __('This is the password you got from DPD to login to their service', DPD_SERVICE_DOMAIN),
				'desc_tip' => true
			),
			'print_label' => array(
				'title' => 'Print DPD Label',
				'type' => 'checkbox',
				'label' => __('This enables DPD Label printing for all orders', DPD_SERVICE_DOMAIN),
				'desc_tip' => true
			),
			'title' => array(
				'title' 		=> __( 'Method Title', DPD_SERVICE_DOMAIN ),
				'type' 			=> 'text',
				'description' 	=> __( 'This controls the title which the user sees during checkout.', DPD_SERVICE_DOMAIN ),
				'default'		=> __( 'DPD Service', DPD_SERVICE_DOMAIN ),
				'desc_tip'		=> true,
			),
			'sending_depot' => array(
				'title' 		=> __( 'Sending Depot', DPD_SERVICE_DOMAIN ),
				'type' 			=> 'select',
				'description' 	=> __( 'This sets the Sending Depot ID for the DPD Service.', DPD_SERVICE_DOMAIN ),
				'default'		=> '',
				'desc_tip'		=> true,
				'options'		=> array(
						'0530' => 'Depot Mechelen',
						'0532' => 'Depot Flemalle',
						'0534' => 'Courcelles',
						'0536' => 'Aalter',
					)
			),
			'base_cost' => array(
				'title' 		=> __( 'Base cost', DPD_SERVICE_DOMAIN ),
				'type' 			=> 'text',
				'description' 	=> __( 'This sets the default cost of the DPD Parcel Service when no other price is found.', 'woocommerce' ),
				'default'		=> __( '5', DPD_SERVICE_DOMAIN ),
				'desc_tip'		=> true,
			),
			'free_shipping' => array(
				'title' 		=> __( 'Free shipping', DPD_SERVICE_DOMAIN ),
				'type' 			=> 'text',
				'description' 	=> __( 'Sets an amount that grants free shipping after the cart price equals or is greater than the set price. Empty or 0 means no free shipping.', 'woocommerce' ),
				'default'		=> __( '0', DPD_SERVICE_DOMAIN ),
				'desc_tip'		=> true,
			),


			'company_name' => array(
				'title'			=> __( 'Company name', DPD_SERVICE_DOMAIN ),
				'type'			=> 'text',
				'description'	=> __( 'Set the company name on shipping labels', DPD_SERVICE_DOMAIN ),
				'desc_tip'		=> true,
			),

			'company_street' => array(
				'title'			=> __( 'Company street', DPD_SERVICE_DOMAIN ),
				'type'			=> 'text',
				'description'	=> __( 'Set the company street on shipping labels', DPD_SERVICE_DOMAIN ),
				'desc_tip'		=> true,
			),

			'company_postcode' => array(
				'title'			=> __( 'Company postcode', DPD_SERVICE_DOMAIN ),
				'type'			=> 'text',
				'description'	=> __( 'Set the company postcode on shipping labels', DPD_SERVICE_DOMAIN ),
				'desc_tip'		=> true,
			),

			'company_city' => array(
				'title'			=> __( 'Company city', DPD_SERVICE_DOMAIN ),
				'type'			=> 'text',
				'description'	=> __( 'Set the company city on shipping labels', DPD_SERVICE_DOMAIN ),
				'desc_tip'		=> true,
			),

			/*'company_country' => array(
				'type' => 'country'
			),*/

			'countries' 	=> array(
				'type' => 'countries'
			)
		);
	}

	public function calculate_shipping( $package = array() ) {
		global $woocommerce;

		// Update, ship only to allowed countries
		$allowed_countries = array_keys($woocommerce->countries->get_allowed_countries());

		$country = $package['destination']['country'];
		$rates_per_country = get_option( $this->countries );
		$free_shipping = $this->free_shipping;
		$allowed_parcel_countries = array();
		$price = $this->base_cost;

		if( count($rates_per_country) > 0 ){
			foreach( $rates_per_country as $c ){

				$allowed_parcel_countries[] = $c['country'];

				if( $country == $c['country']){

					$price = $c['price'];
					break;
				}
			}
		}

		// Is country in allowed countries? 
		if( in_array( $country, $allowed_countries) ){

			// Is country in allowed parcel countries?
			if( in_array( $country, $allowed_parcel_countries ) ){

				// Check if free shipping (base) in enabled
				if( !empty($free_shipping) && $free_shipping != 0 ){
					if( $package['contents_cost'] >= $free_shipping ){
						$price = 0;
					}
				}

				$rate = array(
					'id'    => 'dpd_service',
					'label' => $this->title,
					'cost'  => $price,
					'taxes' => '',
					'calc_tax' => 'per_order'
				);
			
				$this->add_rate($rate);

			}
		}
	}

	public function admin_options() {
	?>
	<h2><?php _e('DPD Service', DPD_SERVICE_DOMAIN); ?></h2>
	<table class="form-table">
	<?php $this->generate_settings_html(); ?>
	</table> 
	<?php
	}

	// Save our api url, username & password to options
	public function process_api(){

		$api_url 		= sanitize_text_field($_POST['api']);
		$api_username 	= sanitize_text_field($_POST['api_username']);
		$api_password 	= sanitize_text_field($_POST['api_password']);

		update_option( 'api_url', $api_url );
		update_option( 'api_username', $api_username );
		update_option( 'api_password', $api_password );
	}

	public function process_countries(){

		$countries = $_POST['countries'];
		$countries_new = array();

		$items = count( $countries['country'] );

		for($i=0;$i<$items;$i++){
			$countries_new[] = array(
				'country' => $countries['country'][$i],
				'country_name' => $countries['country_name'][$i],
				'price' => $countries['price'][$i],
			);
		}

		update_option( $this->countries, $countries_new );

	}

	public function generate_countries_html(){
		global $woocommerce;

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="woocommerce_dpd_Service_sending_depot"><?= __('Countries', DPD_SERVICE_DOMAIN)?></label>
			<td class="forminp">
				<fieldset>
				<?php
			 	$countries_obj   = new WC_Countries();
			    $countries   = $countries_obj->__get('countries');
			    echo '<div id="selectCountry" style="width: 20%; float: left; margin-right: 2%;">';

			    woocommerce_form_field('my_country_field', array(
			    'type'       => 'select',
			    'class'      => array( 'chzn-drop' ),
			    'label'      => __('Select a country', DPD_SERVICE_DOMAIN),
			    'placeholder'    => __('Enter something', DPD_SERVICE_DOMAIN),
			    'options'    => $countries
			    )
			    );
			    echo '</div>';

			    ?>
			    <div class="selectPrice" style="width:20%; margin-right: 2%; float: left;">
			    <?php
			    woocommerce_form_field('country_cost_price', array(
			    	'type' => 'text',
			    	'label' => 'Set a price'
			    ))
				?>
				</div>

				<div style="clear:both"></div>

				<a class="button-primary addCountryPrice" ><?= __('Add Country', DPD_SERVICE_DOMAIN) ?></a>

				<hr>
					<?php
					$c = get_option( $this->countries, true );
					?>
					<ul class="countries" style="">
						<?php
						foreach( $c as $country ){
						?>
						<li>
							<input type="hidden" value="<?=$country['country']?>" name="countries[country][]">
							<input type="hidden" value="<?=$country['country_name']?>" name="countries[country_name][]">
							<input type="hidden" value="<?=$country['price']?>" name="countries[price][]">
							<?=$country['country_name']?> &euro; <?=$country['price']?>,- <a href="#" class="button-primary deleteCountry"> X </a>
						</li>
						<?php
						}
						?>
					</ul>
				<hr>
				</fieldset>
			</td>
		</tr>

		<script>
		jQuery(document).ready(function(){
			jQuery('.addCountryPrice').on('click', function(){
				jQuery('.countries').append('<li><input type="hidden" value="'+jQuery('#my_country_field').val()+'" name="countries[country][]"><input type="hidden" value="'+jQuery('#my_country_field option:selected').text()+'" name="countries[country_name][]"><input type="hidden" value="'+jQuery('#country_cost_price').val().replace(',','.')+'" name="countries[price][]">'+jQuery('#my_country_field option:selected').text()+' &euro; '+jQuery('#country_cost_price').val()+',- <a href="#" class="button-primary deleteCountry"> X </a></li>')
			});

			jQuery('body').on('click', '.deleteCountry', function(){
				jQuery(this).parent().remove();
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}
}