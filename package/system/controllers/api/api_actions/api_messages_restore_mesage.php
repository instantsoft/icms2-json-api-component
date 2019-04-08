<?php

class actionMessagesApiMessagesRestoreMesage extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $check_sig = true;

    public $request_params = array(
        'message_id' => array(
            'default' => 0,
            'rules'   => array(
                array('required'),
                array('digits')
            )
        )
    );

    public function run(){

        $message_id = $this->request->get('message_id');

        $this->model->restoreMessages($this->cms_user->id, $message_id);

        $this->result = array(
            'message_id' => $message_id,
            'user_id'    => $this->cms_user->id
        );

    }

}
