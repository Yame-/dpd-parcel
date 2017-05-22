<?php

// Include wp-load.php so I can get_options from database
// What's the right way to do this ?
$parse_uri = explode( 'wp-admin', $_SERVER['SCRIPT_FILENAME'] );
require_once( $parse_uri[0] . 'wp-load.php' );

$dpd_options = get_option('woocommerce_dpd_Service_settings');

// Include classes
require_once 'classes/Cache.php';
require_once 'classes/Logger.php';
require_once 'classes/ParcelShopFinder.php';
require_once 'classes/Shipment.php';
require_once 'classes/Login.php';

//print_r($_GET);

//$params = json_decode(file_get_contents('php://input'),true);
//print_r($params);
//print_r($_POST);

$dpd_login = new DisLogin($dpd_options['api_username'], $dpd_options['api_password'], $dpd_options['api']);
$dpd_parcel = new DisParcelShopFinder($dpd_login);

$dpd_shipment = new DisShipment($dpd_login);

// Getting our order address
//$order_address_billing = $order->get_address();
//$order_address = $order->get_address('shipping');

// Prepare shipment request
$dpd_shipment->request = array(
	'printOptions' => array(
		'printerLanguage' 	=> 'PDF',
		'paperFormat' 		=> 'A6',
	),
	'order' => array(
		'generalShipmentData' => array(
			'sendingDepot' => '0530',
			'product' => 'CL',
			'sender' => array(
				'name1' => $dpd_options['company_name'],
				'street' => $dpd_options['company_street'],
				'country' => 'BE',/*$dpd_options['company_country'],*/
				'zipCode' => $dpd_options['company_postcode'],
				'city' => $dpd_options['company_city'],
				'customerNumber' => $dpd_options['api_username'],
				/*'mpsCustomerReferenceNumber1' => 'test'*/
			),
			'recipient' => array(
				'name1' => htmlspecialchars($_GET['name']),
				'street' => htmlspecialchars($_GET['street']),
				'country' => htmlspecialchars($_GET['country']),
				'zipCode' => htmlspecialchars(str_replace(' ',' ', $_GET['postcode'])),
				'city' => htmlspecialchars($_GET['city']),
				'phone' => htmlspecialchars($_GET['phone']),
				'email' => htmlspecialchars($_GET['email']),
			)
		),
		'parcels' => array(
			array(
				'parcellabelnumber' => '4004004',
				'customerReferenceNumber1' => 'Online Order #' . htmlspecialchars($_GET['order_id']),
				'weight' => htmlspecialchars($_GET['weight']),
			)
		),
		'productAndServiceData' => array(
			'orderType' => 'consignment',
		)
	)
);

if( $_GET['return'] == 'yes' ){
	$dpd_shipment->request['order']['parcels'][0]['returns'] = 'true';
}

if( $_GET['return'] != 'yes'){
	
	$dpd_shipment->request['order']['productAndServiceData']['predict'] = array(
		'channel' => 1,
		'value' => htmlspecialchars($_GET['email']),
	);

}

if( $_GET['parcel'] == 'yes' && $_GET['return'] != 'yes' ){

	$dpd_shipment->request['order']['productAndServiceData']['parcelShopDelivery'] = array(
		'parcelShopId' => htmlspecialchars($_GET['parcel_id']),
		'parcelShopNotification' => array(
			'channel' => 1,
			'value' => htmlspecialchars($_GET['email']),
			'language' => 'NL',
		)
	);

}

/*echo '<pre>';
print_r( $dpd_shipment->request) ;
exit;*/


$pdf = $dpd_shipment->send();
print_r( $dpd_shipment->error );
if( $pdf ){
	//print_r( $pdf );
	$PDFLabel = $pdf->orderResult->parcellabelsPDF;

	header('Content-Type: application/pdf');
	//header("Content-Disposition:attachment;filename='downloaded.pdf'");

	echo $PDFLabel;

	//readfile( $PDFLabel );
	//echo $PDFLabel;
}

?>