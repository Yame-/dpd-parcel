<?php
/**
 * DIS Translate
 *
 * This object will provide translating for specific/known platforms.
 *
 * Implemented cache systems
 *   - Drupal (6,7): https://drupal.com
 *   - Magento 1: https://magento.com/
 *   - phpFastCache: http://www.phpfastcache.com/
 *   - Prestashop: https://www.prestashop.com/
 *
 * @author: Michiel Van Gucht <michiel.vangucht@dpd.be>
 * @version: 1.0
 * @package: DIS Classes
 */
 
class DisTranslate 
{

  public static function t($data)
  {
    /** 
     * PRESTASHOP
     */
    if(defined('_PS_VERSION_'))
    {
      switch(substr(_PS_VERSION_, 0, 3))
      {
        
        //case '1.4':
        //case '1.5':
        case '1.6':
          return $data;
          // if(is_callable(array('TranslateCore', 'getModuleTranslation')))
          // { 
            // // Context::getContext();
          // }
          break;
      }
    }
  }

}