<?php

class actionMessagesApiMessagesDeleteNotice extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $request_params = array(
        'id' => array(
            'default' => 0,
            'rules'   => array(
                array('digits')
            )
        )
    );

    public function run(){

        $notice_id = $this->request->get('id');

        if($notice_id){

            $notice = $this->model->getNotice($notice_id);

            if($notice && $notice['user_id'] == $this->cms_user->id && !empty($notice['options']['is_closeable'])){
                $this->model->deleteNotice($notice_id);
            }

        } else {
            $this->model->deleteUserNotices($this->cms_user->id);
        }

        $this->result = array(
            'count' => $this->model->getNoticesCount($this->cms_user->id)
        );

    }

}
