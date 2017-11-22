<?php

class actionUsersApiUsersEmailExists extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $request_params = array(
        'email' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('email')
            )
        )
    );

    public function run(){

        $user = $this->model_users->getUserByEmail($this->request->get('email', ''));

        $this->result = array(
            'exists' => $user ? true : false
        );

    }

}
