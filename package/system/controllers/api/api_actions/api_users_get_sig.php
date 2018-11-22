<?php

class actionUsersApiUsersGetSig extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public function run(){

        $this->result = array(
            'sig' => get_sig(),
            'csrf_token' => cmsForm::getCSRFToken()
        );

    }

}
