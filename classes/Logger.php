<?php
/**
 * DIS Logger
 *
 * This object will provide error handling for specific platforms.
 *   - Drupal: https://drupal.com
 *   - Magento 1: https://magento.com/
 *   - PHP error_log (last resort)
 *   - Prestashop: https://www.prestashop.com/
 *   - Zend_Log: http://www.zend.com/
 *
 * @author: Michiel Van Gucht <michiel.vangucht@dpd.be>
 * @version: 1.0
 * @package: DIS Classes
 */
 
class DisLogger
{
  CONST DEBUG = 0;
  CONST INFO = 1;
  CONST WARN = 2;
  CONST ERROR = 3;
  CONST CRITICAL = 4;
  /**
   * 
   * @param string
   */
  public static function log($message, $level, $exception = false)
  {
    if(defined('_DIS_MINIMAL_LOG_LEVEL_'))
      $minimalLogLevel = _DIS_MINIMAL_LOG_LEVEL_;
    else
      $minimalLogLevel = self::DEBUG;
    
    if($minimalLogLevel <= $level)
    {
      /** DRUPAL / Watchdog
       * WATCHDOG_EMERGENCY: Emergency, system is unusable.
       * WATCHDOG_ALERT: Alert, action must be taken immediately.
       * WATCHDOG_CRITICAL: Critical conditions.
       * WATCHDOG_ERROR: Error conditions.
       * WATCHDOG_WARNING: Warning conditions.
       * WATCHDOG_NOTICE: (default) Normal but significant conditions.
       * WATCHDOG_INFO: Informational messages.
       * WATCHDOG_DEBUG: Debug-level messages.
       */
      if(is_callable('watchdog')) {
        $priority;
        switch($level)
        {
          case self::DEBUG:
            $priority = WATCHDOG_DEBUG;
            break;
          case self::INFO:
            $priority = WATCHDOG_INFO;
            break;
          case self::WARN:
            $priority = WATCHDOG_WARNING;
            break;
          case self::ERROR:
            $priority = WATCHDOG_ERROR;
            break;
          case self::CRITICAL:
            $priority = WATCHDOG_ALERT;
            break;
        }
        if(watchdog('DIS', $message, $priority))
          return true;
      }
      
      /** Zend_Log + MAGENTO / MAGE
       * EMERG   = 0;  // Emergency: system is unusable
       * ALERT   = 1;  // Alert: action must be taken immediately
       * CRIT    = 2;  // Critical: critical conditions
       * ERR     = 3;  // Error: error conditions
       * WARN    = 4;  // Warning: warning conditions
       * NOTICE  = 5;  // Notice: normal but significant condition
       * INFO    = 6;  // Informational: informational messages
       * DEBUG   = 7;  // Debug: debug messages
       */
      if(class_exists('Zend_Log'))
      {
        $priority;
        switch($level)
        {
          case self::DEBUG:
            $priority = Zend_Log::DEBUG;
            break;
          case self::INFO:
            $priority = Zend_Log::INFO;
            break;
          case self::WARN:
            $priority = Zend_Log::WARN;
            break;
          case self::ERROR:
            $priority = Zend_Log::ERR;
            break;
          case self::CRITICAL:
            $priority = Zend_Log::ALERT;
            break;
        }
        // The MAGENTO bit
        if(class_exists('Mage')
          && is_callable(array('Mage', 'log')))
        {
          if(Mage::log($message, $priority, 'dpd.log'))
            return true;
        }
        // Custom Zend Logger (Log to the syslog)
        else
        {
          $writer = new Zend_Log_Writer_Syslog(array('application' => 'DIS'));
          $logger = new Zend_Log($writer);
          if($logger->log($message, $priority))
            return true;
        }
      }
      
      // PRESTASHOP
      if(defined('_PS_VERSION_'))
      {
        switch(substr(_PS_VERSION_, 0, 3))
        {
          // They all use the same Logger method, but just in case.
          case '1.4':
          case '1.5':
          case '1.6':
            if(class_exists('Logger')
              && is_callable(array('Logger', 'addLog')))
            {
              if($level == 0)
                $severity = 1;
              else
                $severity = $level;
              if(Logger::addLog('DIS Services: ' . $message, $severity, null, null, null, true))
                return true;
              break;
            }
        }
      }
      
      // Final option, logging via PHP
      $priority;
      switch($level)
      {
        case self::DEBUG:
          $priority = "DEBUG";
          break;
        case self::INFO:
          $priority = "INFO";
          break;
        case self::WARN:
          $priority = "WARNING";
          break;
        case self::ERROR:
          $priority = "ERROR";
          break;
        case self::CRITICAL:
          $priority = "CRITICAL";
          break;
      }
      if(error_log('DIS '. $priority . ':  ' . $message))
        return true;
      
      // Defeat
        return false;
    }
  }
  
  public static function logSoapException($soapE) 
  {
    switch($soapE->faultcode) 
    {
      case 'WSDL':
        self::log($soapE->faultstring, self::WARN);
        break;
      case 's:Server':
      case 's:LOGIN_7':
        self::log($soapE->detail->authenticationFault->errorCode . ': ' . $soapE->detail->authenticationFault->errorMessage, self::ERROR);
        break;
      case 's:SERVER':
        self::log($soapE->detail->faultCodeType->faultCodeField  . ': ' . $soapE->detail->faultCodeType->messageField, self::ERROR);
        break;
      default:
        self::log('Faultcode not recognized (' . $soapE->faultcode .')', self::DEBUG);
        var_dump($soapE);
        break;
    }
  }
}
