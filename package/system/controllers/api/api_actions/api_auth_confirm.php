<?php

class actionAuthApiAuthConfirm extends cmsAction {

    /**
     * Блокировка прямого вызова экшена
     * обязательное свойство
     * @var boolean
     */
    public $lock_explicit_call = true;
    /**
     * Результат запроса
     * обязательное свойство
     * @var array
     */
    public $result;

    /**
     * Возможные параметры запроса
     * с правилами валидации
     * Если запрос имеет параметры, необходимо описать их здесь
     * Правила валидации параметров задаются по аналогии с полями форм
     * @var array
     */
    public $request_params = array(
        'code' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('regexp', '/^[0-9a-f]{32}$/i')
            )
        )
    );

    private $user;

    public function validateApiRequest() {

        if (empty($this->options['is_reg_enabled'])){
            return array('error_code' => 323);
        }

        if (!$this->isIPAllowed(cmsUser::get('ip'))){

            return array(
                'error_code' => 15,
                'error_msg'  => strip_tags(sprintf(LANG_AUTH_RESTRICTED_IP, cmsUser::get('ip')))
            );

        }

        $this->user = $this->model_users->getUserByPassToken($this->request->get('code', ''));
        if (!$this->user) {
            return array('error_code' => 1110);
        }

        return false;

    }

    public function run(){

        $this->model_users->unlockUser($this->user['id']);
        $this->model_users->clearUserPassToken($this->user['id']);

		cmsEventsManager::hook('user_registered', $this->user);

        $auth_user = array();

        if ($this->options['reg_auto_auth']){

            $this->user = $this->model_users->getUser($this->user['id']);

            $this->user['avatar'] = cmsModel::yamlToArray($this->user['avatar']);
            if ($this->user['avatar']){
                foreach($this->user['avatar'] as $size => $path){
                    $this->user['avatar'][$size] = $this->cms_config->upload_host_abs.'/'.$path;
                }
            }

            $this->user = cmsEventsManager::hook('user_login', $this->user);

            cmsUser::setUserSession($this->user);

            $this->model_users->updateUserIp($this->user['id']);

            cmsEventsManager::hook('auth_login', $this->user['id']);

            unset($this->user['password_hash'], $this->user['password'], $this->user['password_salt'], $this->user['pass_token'], $this->user['date_token'], $this->user['ip'], $this->user['is_admin']);

            $auth_user = array(
                'session_name' => session_name(),
                'session_id'   => session_id(),
                'expires_in'   => ini_get('session.gc_maxlifetime'),
                'user_id'      => $this->user['id'],
                'user_info'    => $this->user
            );

        }

        $this->result = array(
            'auth_user'    => $auth_user,
            'success'      => true,
            'success_text' => LANG_REG_SUCCESS_VERIFIED
        );

    }

}
