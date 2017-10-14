<?php
/**
 * DIS Shipment
 *
 * Create a DPD label or request a pickup. 
 * 
 * @author: Michiel Van Gucht <michiel.vangucht@dpd.be>
 * @version: 1.0
 * @package: DIS Classes
 */
 
class DisShipment
{
  /*
   * @const WEBSERVICE_SHIPMENT the final part of the url to the service.
   */
  CONST WEBSERVICE_SHIPMENT = 'ShipmentService.svc?wsdl';
  
  /**
   * Login
   * @access public
   * @var DisLogin
   */
  public $login;
  
  /**
   * The request
   * Creating the request in array form links close together with the SOAP XML format.
   * Object oriented is possible, but not my favorite in this case
   * @access public
   * @var array
   */
  public $request = array();
  
  /**
   * The result
   * @access public
   * @var SoapResult
   */
  public $result;
  
  /**
   * @param DisLogin $login
   */
  public function __construct(DisLogin $login)  
  {
    $this->login = $login;
  }
  
  /**
   * Make the request to the shipment service.
   */
  public function send()
  {
    // Add the printing options if not set.
    if(!isset($request['printOptions']))
    {
      $this->request['printOptions']['printerLanguage'] = 'PDF';
      $this->request['printOptions']['paperFormat'] = 'A6';
    }
    
    $counter = 0;
    $stop = false;
    while($counter < 2 
      && !$stop)
    {
      try {
        //$client = new SoapClient($this->login->getWebserviceUrl(self::WEBSERVICE_SHIPMENT), array('trace' => 1));
        $url = $this->login->getWebserviceUrl(self::WEBSERVICE_SHIPMENT, array('trace' => 1));
          $opts = array(
            'http'=>array(
              'user_agent' => 'PHPSoapClient'
            ),
            'ssl' => array(
              'verify_peer' => false,
              'verify_peer_name' => false,
              'allow_self_signed' => true,
            )
          );

          $context = stream_context_create($opts);
          $client = new SoapClient($url,
              array('stream_context' => $context,
                'cache_wsdl' => WSDL_CACHE_NONE)
          );
        
        $soapHeader = $this->login->getSoapHeader();
        $client->__setSoapHeaders($soapHeader);
        $result = $client->storeOrders($this->request);
        $stop = true;
      } 
      catch (SoapFault $soapE) 
      {
        
        if(isset($soapE->detail->authenticationFault->errorCode)
          && ($soapE->detail->authenticationFault->errorCode == 's:LOGIN_7'
          || $soapE->detail->authenticationFault->errorCode == 'LOGIN_7'))
        {
          // If there was a login error we'll try one more time (counter) after a login refresh
          DisLogger::log("Label generation failed due to authentication error", DisLogger::DEBUG);
          $this->login->refresh();
        }
        else 
        {
          // Other errors stop the process
          DisLogger::logSoapException($soapE);
          return false;
        }
      }
      $counter++;
    }
    
    $this->result = $result;
    return $result;
  }
}
