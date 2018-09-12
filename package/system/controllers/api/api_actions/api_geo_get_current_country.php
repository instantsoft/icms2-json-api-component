<?php

class actionGeoApiGeoGetCurrentCountry extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public function run(){

        $this->result = array(
            'item' => $this->getGeoByIp()
        );

    }

}
