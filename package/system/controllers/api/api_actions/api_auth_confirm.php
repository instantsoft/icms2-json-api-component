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
     * Флаг, обязующий проверять параметр sig запроса
     * sig привязан к домену сайта и к ip адресу посетителя
     * @var boolean
     */
    public $check_sig = true;

    /**
     * Возможные параметры запроса
     * с правилами валидации
     * Если запрос имеет параметры, необходимо описать их здесь
     * Правила валидации параметров задаются по аналогии с полями форм
     * @var array
     */
    public $request_params = array(
        'code' => array(
            'rules'   => array(
                array('required'),
                array('regexp', '/^[0-9a-f]{32}$/i')
            )
        ),
        'user_id' => array(
            'default' => 0,
            'rules'   => array(
                array('required'),
                array('digits')
            )
        )
    );

    private $users_model, $user;

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

        $this->users_model = cmsCore::getModel('users');

        $this->user = $this->users_model->getUserByPassToken($this->request->get('code'));
        if (!$this->user || $this->user['id'] != $this->request->get('user_id')) {
            return array('error_code' => 1110);
        }

        return false;

    }

    public function run(){

        $this->users_model->unlockUser($this->user['id']);
        $this->users_model->clearUserPassToken($this->user['id']);

		cmsEventsManager::hook('user_registered', $this->user);

        $this->result = array(
            'success'      => true,
            'success_text' => LANG_REG_SUCCESS_VERIFIED
        );

    }

}
