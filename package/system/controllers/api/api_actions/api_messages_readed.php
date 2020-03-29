<?php

class actionMessagesApiMessagesReaded extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $check_sig = true;

    public $request_params = array(
        'ids' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('regexp', '/^([0-9]{1}[0-9,]*)$/')
            )
        )
    );

    public function run(){

        $ids = explode(',', $this->request->get('ids'));

        $this->model->filterIn('id', $ids);
        $this->model->filterEqual('to_id', $this->cms_user->id);

        $this->model->updateFiltered('{users}_messages', array(
           'is_new' => 0
        ));

        $this->result = array(
            'ids'  => $ids,
            'user_id' => $this->cms_user->id
        );

    }

}
