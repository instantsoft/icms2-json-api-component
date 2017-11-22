<?php

class actionAuthApiAuthReset extends cmsAction {

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
            'default' => '',
            'rules'   => array(
                array('required'),
                array('regexp', '/^[0-9a-f]{32}$/i')
            )
        ),
        'password1' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('min_length', 6)
            )
        ),
        'password2' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('min_length', 6)
            )
        ),
    );

    private $user;

    public function validateApiRequest() {

        $pass_token = $this->request->get('code', '');

        $this->user = $this->model_users->getUserByPassToken($pass_token);

        if (!$this->user) {
            return array('error_code' => 113);
        }

        if ($this->user['is_admin']) {
            return array('error_code' => 15);
        }

        if($this->user['is_locked']) {

            return array('request_params' => array(
                'code' => LANG_RESTORE_BLOCK.($this->user['lock_reason'] ? '. '.$this->user['lock_reason'] : '')
            ));

        }

        if ((strtotime($this->user['date_token']) + 3600) < time()){

            $this->model_users->clearUserPassToken($this->user['id']);

            return array('request_params' => array(
                'code' => LANG_RESTORE_TOKEN_EXPIRED
            ));

        }

        if($this->request->get('password1', '') !== $this->request->get('password2', '')) {

            return array('request_params' => array(
                'password1' => LANG_REG_PASS_NOT_EQUAL,
                'password2' => LANG_REG_PASS_NOT_EQUAL
            ));

        }

        return false;

    }

    public function run(){

        $this->model_users->updateUser($this->user['id'], array(
            'password1'    => $this->request->get('password1', ''),
            'password2'    => $this->request->get('password2', '')
        ));

        $this->model_users->clearUserPassToken($this->user['id']);

        $this->result = array(
            'user_id'      => $this->user['id'],
            'success'      => true,
            'success_text' => LANG_PASS_CHANGED
        );

    }

}
