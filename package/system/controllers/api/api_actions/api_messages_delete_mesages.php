<?php

class actionMessagesApiMessagesDeleteMesages extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $check_sig = true;

    public $request_params = array(
        'message_ids' => array(
            'default' => [],
            'rules'   => array(
                array('required')
            )
        )
    );

    public function run(){

        $_message_ids = $this->request->get('message_ids');

        $message_ids = [];

        foreach ($_message_ids as $message_id) {
            $message_ids[] = (int)$message_id;
        }

        $delete_msg_ids = $this->model->deleteMessages($this->cms_user->id, $message_ids);

        if($delete_msg_ids){
            $message_ids = array_diff($message_ids, $delete_msg_ids);
        }

        $this->result = array(
            'remove_text'    => LANG_PM_IS_DELETE,
            'message_ids'    => $message_ids,
            'delete_msg_ids' => $delete_msg_ids
        );

    }

}
