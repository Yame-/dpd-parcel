<?php
/**
 * DIS Cache
 *
 * This object will provide caching for specific/known platforms.
 *
 * Implemented cache systems
 *   - Drupal (6,7): https://drupal.com
 *   - Magento 1: https://magento.com/
 *   - phpFastCache: http://www.phpfastcache.com/
 *        ^- This was the first caching system implemented, it was so easy to setup and use.
 *   - Prestashop: https://www.prestashop.com/
 *
 * @author: Michiel Van Gucht <michiel.vangucht@dpd.be>
 * @version: 1.0
 * @package: DIS Classes
 */
 
class DisCache
{
  /**
   * Save an object to the cache
   * @param string $name
   * @param string $value
   * @param int $time In seconds
   * @return bool
   */
  public static function set($name, $value, $time = false)
  {
    if(!$time)
        $expiration = 0;
      else
        $expiration = $time;
    
    /**
     * DRUPAL 6 AND 7
     */
    if(defined('DRUPAL_ROOT'))
    {
      if(is_callable('cache_set'))
      {
        if(cache_set($name, $value, 'cache', time() + $expiration))
          return true;
      }
    }
    
    /**
     * MAGENTO 1.*
     */
    if(is_callable(array('Mage', 'app')))
    {
      $cache = Mage::app()->getCache();
      if($cache)
      {
        // serialize, because it doesn't take objects otherwise.
        if($cache->save(serialize($value), $name, array("DIS_cache"), $time))
          return true;
      }
    }

    /**
     * WordPress
     */
    if(defined('ABSPATH')){
      if(is_callable('update_option')){
        update_option($name, array('name' => $name, 'time' => $time, 'value' => serialize($value)));
        return true;
      }
    }
    
    /** 
     * PRESTASHOP
     */
    if(defined('_PS_VERSION_'))
    {
      switch(substr(_PS_VERSION_, 0, 3))
      {
        
        case '1.4':
        case '1.5':
        case '1.6':
          if(is_callable(array('Cache', 'getInstance'))
            && is_callable(array('Cache', 'set'))) {
            $cache = Cache::getInstance();
            if($cache->set($name, $value, $expiration))
              return true;
          }
          break;
      }
    }
    
    /** 
     * phpFastCache
     */
    if(function_exists('phpFastCache'))
    {
      $cache = phpFastCache\CacheManager::getInstance();
      // Only return true when cache set succeeded.
      // This will allow the rest of the cache options to try their luck
      if($cache->set($name, $value, $expiration))
        return true;
    }
    
    // If nothing worked, return false
    DisLogger::Log('No (known/implemented) cache method was found.', DisLogger::WARN);
    return false;
  }
  
  /**
   * Lookup an object in the cache
   * @param string name
   * @return bool|mixed
   */
  public static function get($name)
  {
    /**
     * DRUPAL 6 AND 7
     */
    if(defined('DRUPAL_ROOT'))
    {
      if(is_callable('cache_get'))
      {
        $value = cache_get($name);
        if($value)
          return $value;
      }
    }
    
    /**
     * MAGENTO 1.*
     */
    if(is_callable(array('Mage', 'app')))
    {
      $cache = Mage::app()->getCache();
      if($cache)
      {
        $value = $cache->load($name);
        if($value)
          return unserialize($value);
      }
    }

    /**
     * WordPress
     */
    if(defined('ABSPATH')){
      if(is_callable('update_option')){
        $cache = get_option($name);
        $cache['value'] = unserialize($cache['value']);
        return $cache['value'];
      }
    }
    
    /** 
     * PRESTASHOP
     */
    if(defined('_PS_VERSION_'))
    {
      switch(substr(_PS_VERSION_, 0, 3))
      {
        case '1.4':
        case '1.5':
        case '1.6':
          if(is_callable(array('Cache', 'getInstance'))) {
            $cache = Cache::getInstance();
            if($cache->exists($name)) {
              $value = $cache->get($name);
              if($value)
                return $value;
            }
          }
          break;
      }
    }
    
    /** 
     * phpFastCache
     */
    if(function_exists("phpFastCache"))
    {
      $cache = phpFastCache\CacheManager::getInstance();
      $value = $cache->get($name);
      // Same note as the set method. Let the other cache options give it a try.
      if($value)
        return $value;
    }
    
    // If nothing worked, return false
    DisLogger::log("Getting value from cache failed (" . $name . ")", DisLogger::DEBUG);
    return false;
  }
  
  /**
   * Delete an object from the cache
   * @param string name
   * @return bool (true if deleted, false if not found)
   */
  public static function delete($name)
  {
    /**
     * DRUPAL 6 AND 7
     */
    if(defined('DRUPAL_ROOT'))
    {
      if(is_callable('cache_clear_all'))
      {
        if(cache_clear_all($name, 'cache'))
          return true;
      }
    }
    
    /**
     * MAGENTO 1.*
     */
    if(is_callable(array('Mage', 'app')))
    {
      $cache = Mage::app()->getCache();
      if($cache)
      {
        if($cache->remove($name))
          return true;
      }
    }

    /**
     * WordPress
     */
    if(defined('ABSPATH')){
      if(is_callable('update_option')){
        delete_option('DIS_cache');
      }
    }
    
    /** 
     * PRESTASHOP
     */
    if(defined('_PS_VERSION_'))
    {
      switch(substr(_PS_VERSION_, 0, 3))
      {
        
        case '1.4':
        case '1.5':
        case '1.6':
          if(is_callable(array('Cache', 'getInstance'))
            && is_callable(array('Cache', 'exists'))
            && is_callable(array('Cache', 'delete'))) {
            $cache = Cache::getInstance();
            if($cache->exists($name)
              && $cache->delete($name)) {
              return true;
            }
          }
          break;
      }
    }
    
    /** 
     * phpFastCache
     */
    if(function_exists('phpFastCache'))
    {
      $cache = phpFastCache\CacheManager::getInstance();
      // Same note as the set method. Let the other cache options give it a try.
      if($cache->delete($name))
        return true;
    }
    
    // If nothing worked, ... return false.
    DisLogger::log('Deleting value from cache failed (or was not found) (' . $name . ')', DisLogger::DEBUG);
    return false;
  }
 
}
