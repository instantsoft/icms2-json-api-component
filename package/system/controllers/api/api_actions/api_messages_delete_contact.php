<?php

class actionMessagesApiMessagesDeleteContact extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $check_sig = true;

    public $request_params = array(
        'contact_id' => array(
            'default' => 0,
            'rules'   => array(
                array('required'),
                array('digits')
            )
        )
    );

    private $contact_id;

    public function validateApiRequest() {

        $this->contact_id = $this->request->get('contact_id');

        $contact = $this->model->getContact($this->cms_user->id, $this->contact_id);

        if (!$contact){
            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => array(
                    'contact_id' => ERR_VALIDATE_INVALID
                )
            );
        }

        return false;

    }

    public function run(){

        $this->model->deleteContact($this->cms_user->id, $this->contact_id);

        $count = $this->model->getContactsCount($this->cms_user->id);

        $this->result = array(
            'count' => $count
        );

    }

}
