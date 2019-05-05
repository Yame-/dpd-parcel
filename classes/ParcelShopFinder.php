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
 
class DisParcelShopFinder
{
  /*
   * @const WEBSERVICE_PARCELSHOP the final part of the url to the service.
   */
  CONST WEBSERVICE_PARCELSHOP = 'ParcelShopFinderService.svc?wsdl';
  
  /**
   * Login
   * @access public
   * @var DisLogin
   */
  public $login;
  
  /**
   * The results
   * @access public
   * @var array
   */
  public $result;

  protected $googleAPIKey;
  
  /**
   * @param DisLogin $login
   */
  public function __construct(DisLogin $login, $googleAPIKey)  
  {
    $this->result = new stdClass();
    $this->login = $login;
    $this->googleAPIKey = $googleAPIKey;
  }
  
  /**
   * Search shops around a geolocation point or address
   * @param array $data
   * @return stdClass Containing the geoencoded center and the shops around it.
   */
  public function search($data = array())
  {
    $cacheUID = hash('adler32', serialize($data));
    //$cachedRequest = DisCache::get('Dis'.$cacheUID.'Data');
    $cachedResult = DisCache::get('Dis'.$cacheUID.'Result');
    //if( $cachedRequest
    //  && $cachedResult
    //  && $cachedRequest == $data) 
    if($cachedResult)
    {
      DisLogger::log("Using cached parcel shop result " . $cacheUID, DisLogger::DEBUG);
      $this->result = $cachedResult;
    }
    else
    {
      // Cache the data for 600 seconds (or 10 minutes)
      DisCache::set('Dis'.$cacheUID.'Data', $data, 600);
      
      
      if(!isset($data['Limit']))
        $data['Limit'] = 10;
        
      if(isset($data['DayOfWeek']) || isset($data['TimeOfDay']))
        $data['Limit'] =  $data['Limit'] * 2;
      
      if(!(isset($data['Long']) && isset($data['Lat'])))
      {
        $address = "";
        if(isset($data['Query']))
        {
          $address = $data['Query'];
        } else {
          $address_array = array();
          
          if(isset($data['Street']))
            $address_array[] = $data['Street'];
          
          if(isset($data['HouseNo']))
            $address_array[] = $data['HouseNo'];
          
          if(isset($data['Country']))
            $address_array[] = $data['Country'];
            
          if(isset($data['ZipCode']))
            $address_array[] = $data['ZipCode'];
          
          if(isset($data['City']))
            $address_array[] = $data['City'];

          $address = implode(' ', $address_array);
        }
        
        $googleGeoCoding = $this->getGoogleMapsCenter($address);
        if($googleGeoCoding) {
          $data['Long'] = $googleGeoCoding->lng;
          $data['Lat'] = $googleGeoCoding->lat;
        } else {
          DisLogger::log("Couldn't encode address, " . $address . ", with google geocode API.", DisLogger::DEBUG);
          return false;
        }
      }
      
      $request = array(
        'longitude' => substr($data['Long'],0,6)
        ,'latitude' => substr($data['Lat'],0,6)
        ,'limit' => $data['Limit']
      );
      
      if(!isset($data['filters'])) {
        $request['consigneePickupAllowed'] = 'true';
      }
      
      $counter = 0;
      $stop = false;
      while($counter < 2 
        && !$stop)
      {
        try {
          //$client = new SoapClient($this->login->getWebserviceUrl(self::WEBSERVICE_PARCELSHOP));
          $url = $this->login->getWebserviceUrl(self::WEBSERVICE_PARCELSHOP);
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
          
          $result = $client->findParcelShopsByGeoData($request);
          $stop = true;
        } 
        catch (SoapFault $soapE) 
        {
          if(isset($soapE->detail->authenticationFault->errorCode)
            && $soapE->detail->authenticationFault->errorCode == 'LOGIN_7')
          {
            // If there was a login error we'll try one more time (counter) after a login refresh
            DisLogger::log("Shop finder failed due to authentication error", DisLogger::DEBUG);
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
      
      if(!isset($result->parcelShop))
      {
        DisLogger::log("Shop finder returned empty", DisLogger::DEBUG);
        return false;
      }
      else
      {
        $this->result->center = new stdClass();
        $this->result->center->lng = $data['Long'];
        $this->result->center->lat = $data['Lat'];
        $this->result->shops = array();
        
        foreach($result->parcelShop as $parcelShop)
        {
          if($this->filter($parcelShop, $data)){
            $this->result->shops[$parcelShop->parcelShopId] = $this->cleanHours($parcelShop);
          }
        }
        // Cache the result for 600 seconds (or 10 minutes)
        DisCache::set('Dis'.$cacheUID.'Result', $this->result, 600);
      }
    }
    return $this->result;
  }
  
  private function cleanHours($shop) {
    foreach($shop->openingHours as $key => $day) {
      if($day->openMorning == $day->closeMorning) {
        $shop->openingHours[$key]->openMorning = '';
        $shop->openingHours[$key]->closeMorning = '';
      }
      if($day->closeMorning == $day->openAfternoon) {
        $shop->openingHours[$key]->closeMorning = '';
        $shop->openingHours[$key]->openAfternoon = '';
      }
      if($day->openAfternoon == $day->closeAfternoon) {
        $shop->openingHours[$key]->openAfternoon = '';
        $shop->openingHours[$key]->closeAfternoon = '';
      }
    }
    return $shop;
  }
  
  private function filter($shop, $data)
  {
    if(isset($data['DayOfWeek']) && isset($data['TimeOfDay'])) {
      $day = $shop->openingHours[$data['DayOfWeek']];
      return $this->dayOpen($day) && $this->timeOpen($day, $data['TimeOfDay']);
    } elseif (isset($data['DayOfWeek']))  {
      return $this->dayOpen($shop->openingHours[$data['DayOfWeek']]);
    } elseif (isset($data['TimeOfDay'])) {
      foreach($shop->openingHours as $day) {
        if($this->timeOpen($day, $data['TimeOfDay']))
          return true;
      }
    }
    
    return true;
  }
  
  private function dayOpen($day) {
    return !($day->openMorning == $day->closeMorning
      && $day->openMorning == $day->openAfternoon
      && $day->openMorning == $day->closeAfternoon);
  }
  
  private function timeOpen($day, $time) {
    return ($day->openMorning <= $time && $time < $day->closeMorning) 
      || ($day->openAfternoon <= $time && $time < $day->closeAfternoon);
  }
  
  /**
   * Geo-encode an address
   * @todo Add other places(?) and not only first result.
   * @param string $query The address/place to look for
   * @return stdClass First result of the geoCoding
   */
  private function getGoogleMapsCenter($query)
  {

    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($query) . '&key=' .$this->googleAPIKey. '&sensor=false';
  
    if (function_exists('curl_version'))
  {
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      $result = curl_exec($curl);
      $obj = json_decode($result);
      curl_close($curl);
  }
  else if (file_get_contents(__FILE__) && ini_get('allow_url_fopen'))
  {
      $source = file_get_contents($url);
    $obj = json_decode($source);
    
  } else {
    $obj = false;
  }
        
    $result = new stdClass();
    
    if(count($obj->results) == 0) {
        return false;
    } else {
        // TODO: Manage multiple results.
        $result->lat = $obj->results[0]->geometry->location->lat;
        $result->lng = $obj->results[0]->geometry->location->lng;
        return $result;
    }
  }
}
