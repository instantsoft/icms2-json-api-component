<?php

class actionAuthApiAuthLogout extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public function run(){

        $user_id = $this->cms_user->id;

        cmsEventsManager::hook('auth_logout', $this->cms_user->id);

        cmsUser::logout();

        $this->result = array(
            'user_id' => $user_id
        );

    }

}
