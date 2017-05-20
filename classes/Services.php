<?php

class DisServices
{
  public $default;
  public $services;

  public function __construct()  
  {
    $this->default = new stdClass();
    $this->default->max_width = 175;      // Real max length
    $this->default->max_circum = 125;     // (max-height + max-depth) * 2 < 125
    $this->default->max_weight = 31.5;
    $this->default->zones = array('Europe');
    $this->default->weight_ranges = array(0,3,31);
    $this->default->shipment_settings = array(
        'cod' => false
        ,'comp' => false
        ,'e10' => false
        ,'e12' => false
        ,'e18' => false
        ,'dps' => false
        ,'predict' => false
        ,'sat' => false
    );
    
    $this->services = array();
    
    $this->services[0] = new stdClass();
    $this->services[0]->name = 'Home';
    $this->services[0]->type = 'B2B';
    $this->services[0]->description = 'Get your parcel delivered at your place';
    $this->services[0]->shipment_settings = array(
        'cod' => false
        ,'comp' => false
        ,'e10' => false
        ,'e12' => false
        ,'e18' => false
        ,'dps' => false
        ,'predict' => false
        ,'sat' => false
    );
    
    $this->services[1] = new stdClass();
    $this->services[1]->name = 'Home With Predict';
    $this->services[1]->type = 'B2C';
    $this->services[1]->description = 'Get your parcel delivered at your place (with notification of delivery)';
    $this->services[1]->shipment_settings = array(
        'cod' => false
        ,'comp' => false
        ,'e10' => false
        ,'e12' => false
        ,'e18' => false
        ,'dps' => false
        ,'predict' => true
        ,'sat' => false
    );
    
    $this->services[2] = new stdClass();
    $this->services[2]->name = 'Pickup';
    $this->services[2]->type = 'PSD';
    $this->services[2]->description = 'Get your parcel delivered at a Pickup point and collect it at your convenience.';
    $this->services[2]->max_width = 100;
    $this->services[2]->max_circum = 200;
    $this->services[2]->max_weight = 20;
    $this->services[2]->weight_ranges = array(0,3,10,20);
    $this->services[2]->shipment_settings = array(
        'cod' => false
        ,'comp' => false
        ,'e10' => false
        ,'e12' => false
        ,'e18' => false
        ,'dps' => true
        ,'predict' => false
        ,'sat' => false
    );
    
    $this->services[3] = new stdClass();
    $this->services[3]->name = 'Guarantee';
    $this->services[3]->type = 'B2B';
    $this->services[3]->description = 'Get your parcel delivered at your place, the day after shipment. Guaranteed.';
    $this->services[3]->shipment_settings = array(
        'cod' => false
        ,'comp' => false
        ,'e10' => false
        ,'e12' => false
        ,'e18' => true
        ,'dps' => false
        ,'predict' => false
        ,'sat' => false
    );
    
    $this->services[4] = new stdClass();
    $this->services[4]->name = 'Express 10';
    $this->services[4]->type = 'B2B';
    $this->services[4]->description = 'Get your parcel delivered at your place before 10 o\'clock, the day after shipment.';
    $this->services[4]->shipment_settings = array(
        'cod' => false
        ,'comp' => false
        ,'e10' => true
        ,'e12' => false
        ,'e18' => false
        ,'dps' => false
        ,'predict' => false
        ,'sat' => false
    );
    
    $this->services[5] = new stdClass();
    $this->services[5]->name = 'Express 12';
    $this->services[5]->type = 'B2B';
    $this->services[5]->description = 'Get your parcel delivered at your place before 12 o\'clock, the day after shipment.';
    $this->services[5]->shipment_settings = array(
        'cod' => false
        ,'comp' => false
        ,'e10' => false
        ,'e12' => true
        ,'e18' => false
        ,'dps' => false
        ,'predict' => false
        ,'sat' => false
    );
  }
}