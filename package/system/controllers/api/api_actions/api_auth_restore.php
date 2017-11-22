<?php

class actionAuthApiAuthRestore extends cmsAction {

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
        'email' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('email')
            )
        ),
    );

    private $user;

    public function validateApiRequest() {

        $email = $this->request->get('email', '');

        $this->user = $this->model_users->getUserByEmail($email);

        if (!$this->user) {
            return array('error_code' => 113);
        }

        if ($this->user['is_admin']) {
            return array('error_code' => 15);
        }

        if($this->user['is_locked']) {

            return array('request_params' => array(
                'email' => LANG_RESTORE_BLOCK.($this->user['lock_reason'] ? '. '.$this->user['lock_reason'] : '')
            ));

        } elseif($this->user['pass_token']) {

            return array('request_params' => array(
                'email' => LANG_RESTORE_TOKEN_IS_SEND
            ));

        }

        return false;

    }

    public function run(){

        $pass_token = string_random(32, $this->user['email']);

        $this->model_users->updateUserPassToken($this->user['id'], $pass_token);

        $messenger = cmsCore::getController('messages');

        $to = array('email' => $this->user['email'], 'name' => $this->user['nickname']);
        $letter = array('name' => 'reg_restore');

        $messenger->sendEmail($to, $letter, array(
            'nickname'    => $this->user['nickname'],
            'page_url'    => href_to_abs('auth', 'reset', $pass_token),
            'pass_token'  => $pass_token,
            'valid_until' => html_date(date('d.m.Y H:i', time() + (24 * 3600)), true),
        ));

        $this->result = array(
            'user_id'      => $this->user['id'],
            'success'      => true,
            'success_text' => LANG_TOKEN_SENDED,
            'sig'          => get_sig()
        );

    }

}
