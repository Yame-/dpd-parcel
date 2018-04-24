<?php
/**
 * DIS Login
 *
 * This object will enable you to easily connect to the login service of our DIS web services.
 *
 * @author: Michiel Van Gucht <michiel.vangucht@dpd.be>
 * @version: 1.0
 * @package: DIS Classes
 */

class DisLogin
{
  /*
   * @const WEBSERVICE_LOGIN the final part of the url to the service.
   */
  CONST WEBSERVICE_LOGIN = 'LoginService.svc?wsdl';

  /*
   * @const AUTHENTICATION_NAMESPACE Authentication namespace for the authentication header in soap requests.
   */
  CONST AUTHENTICATION_NAMESPACE = 'http://dpd.com/common/service/types/Authentication/2.0';

  /**
   * The delisId
   * @access private
   * @var string
   */
  private $delisId;

  /**
   * The password
   * @access private
   * @var string
   */
  private $password;

  /**
   * The customerUid returned by the web service call
   * @access private
   * @var string
   */
  private $uid;
  /**
   * The authToken returned by the web service call
   * @access private
   * @var string
   */
  private $token;
  /**
   * The depot linked to the account
   * @access private
   * @var string
   */
  private $depot;

  /**
   * Get or set if the login has been refreshed.
   * <code>
   * <?php
   * $shipment = new DisShipment($cachedLogin);
   *
   * ... // $setting up the shipment.
   *
   * $shipment->send(); // Make the call to the shipment service
   *
   * if($shipment->login->refreshed) {
   *   $shipment->login->refreshed = false;
   *   // Recache login object here.
   * }
   * ?>
   * </code>
   * @access public
   * @var bool
   */
  public $refreshed = false;

  /**
   * Get or set the base url for the web services
   * Easy to switch between environments, but take your caching into account.
   * @access public
   * @var string
   */
  public $url;

  /**
   * The constructor
   * @param string $delisId Your DelisID
   * @param string $password Your password
   * @param string $url (optional) The base url for the web services, with trailing slash.
   */
  public function __construct($delisId, $password, $url = 'https://public-dis.dpd.nl/Services/')
  {
  $cachedLogin = DisCache::get("DisLogin");
  if( $cachedLogin
    && $cachedLogin->checkUrl($url)
    && $cachedLogin->checkCredentials($delisId, $password))
  {
    DisLogger::log("Using cached login", DisLogger::DEBUG);
    $this->delisId = $cachedLogin->delisId;
    $this->password = $cachedLogin->password;
    $this->url = $cachedLogin->url;
    $this->uid = $cachedLogin->uid;
    $this->token = $cachedLogin->token;
    $this->depot = $cachedLogin->depot;
  } else {
    $this->delisId = $delisId;
    $this->password = $password;
    $this->url = $url;

    // When the object is created it will always trigger a refresh because otherwise there wouldn't be a authToken available.
    $this->refresh();
  }
  }

  /**
   * Refresh the authentication token. (eg: When a call to an other service throws an authentication error)
   */
  public function refresh()
  {
    DisLogger::log("Login refresh requested", DisLogger::INFO);
    $this->refreshed = false;
    $this->login();
    $this->refreshed = true;
  }

  /**
   * The function that does the actual SOAP call to the web services.
   */
  private function login()
  {
    $result = false;

    try {
      $client = new SoapClient($this->getWebserviceUrl(self::WEBSERVICE_LOGIN));

      $result = $client->getAuth(array(
        'delisId' => $this->delisId
        ,'password' => $this->password
        ,'messageLanguage' =>'en_US'
        )
      );
    }
    catch(SoapFault $soapE)
    {
      DisLogger::logSoapException($soapE);
    }

    if(!$result)
    {
      DisLogger::log("Login failed", DisLogger::DEBUG);
      return false;
    }
    else
    {
      DisLogger::log("Login succeeded", DisLogger::DEBUG);
      $this->delisId = $result->return->delisId;
      $this->uid = $result->return->customerUid;
      $this->token = $result->return->authToken;
      $this->depot = $result->return->depot;
      DisCache::set("DisLogin", $this);
    }
  }

  /**
   * Get the soap header that has to be used with the other services
   * @return SOAPHeader
   */
  public function getSoapHeader()
  {
    $soapHeaderBody = array(
      'delisId' => $this->delisId
      ,'authToken' => $this->token
      ,'messageLanguage' => 'en_US'
    );

    return new SOAPHeader(self::AUTHENTICATION_NAMESPACE, 'authentication', $soapHeaderBody, false);
  }

  /**
   * Build the web service url from the base url (in the DisLogin object) and the provided end point
   * This function can be used by the other web service classes
   * @param string $serviceEndPoint The location of the service to call (eg: LoginService.svc?wsdl)
   * @return string
   */
  public function getWebserviceUrl($serviceEndPoint)
  {
    $url = $this->url;

    // Check if the url has a trailing slash, otherwise add it.
    // We do it here because the url variable is a public one
    if (substr($url, -1) != '/') {
        $url = $url . '/';
    }

    return $url . $serviceEndPoint;
  }

  /**
   * Use this function to determine if you need to create a new object or if you can use the cached one.
   * eg: When a switch between live and stage has been made by the user
   * @param string $url The url currently configured by the shipper
   * @return bool Returns false if the url has been altered.
   */
  public function checkUrl($url)
  {
    return ($this->url == $url);
  }

  /**
   * Use this function to determine if you need to create a new object or if you can use the cached one.
   * eg: When a user has changed his password.
   * @param string $delisID The delisID currently configured by the shipper
   * @param string $password The password currently configured by the shipper
   * @return bool Returns false if the credentials have been altered.
   */
  public function checkCredentials($delisId, $password)
  {
    return ($this->delisId == $delisId && $this->password == $password);
  }

  /**
   * Get the delisId set in this object
   */
  public function getDelisId()
  {
    return $this->delisId;
  }

  /**
   * Get the customerUid returned by the soap call
   * (Will trigger a refresh when not yet set)
   */
  public function getUid()
  {
    if(!$this->uid)
      $this->refresh();

    return $this->uid;
  }

  /**
   * Get the token returned by the soap call
   * (Will trigger a refresh when not yet set)
   */
  public function getToken()
  {
    if(!$this->token)
      $this->refresh();

    return $this->token;
  }

  /**
   * Get the depot returned by the soap call
   * (Will trigger a refresh when not yet set)
   */
  public function getDepot()
  {
    if(!$this->depot)
      $this->refresh();

    return $this->depot;
  }

  /**
   * Get the url configured to be used in the current object
   */
  public function getUrl()
  {
    return $this->url;
  }

  /**
   * Set the url configured to be used in the current object
   * (Will trigger a refresh when it differs from the previous url)
   */
  public function setUrl($url)
  {
    if($this->url != $url)
    {
      $this->url = $url;
      $this->refresh();
    }
  }

}
