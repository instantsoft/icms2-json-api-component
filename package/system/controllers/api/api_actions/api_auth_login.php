<?php

class actionAuthApiAuthLogin extends cmsAction {

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
        'email' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('email')
            )
        ),
        'password' => array(
            'default' => '',
            'rules'   => array(
                array('required')
            )
        )
    );

    /**
     * Массив ключей для удаления
     * @var array
     */
    public $unset_fields = array(
        'user_info' => array(   // название ключа в $this->result
            'type'   => 'item', // list или item
            'unsets' => array(  // массив названий ключей для удаления
                'password', 'password_salt', 'pass_token', 'date_token', 'ip', 'is_admin'
            )
        )
    );

    private $user;

    public function validateApiRequest() {

        // если авторизован, проверки не выполняем
        if($this->cms_user->is_logged){

            $this->user = $this->model_users->getUser($this->cms_user->id);

            return false;

        }


        $logged_id = cmsUser::login($this->request->get('email', ''), $this->request->get('password', ''));

        if(!$logged_id){
            return array(
                'error_code' => 5
            );
        }

        $this->user = $this->model_users->getUser($logged_id);

        if ($this->user['is_admin']) {

            cmsUser::logout();

            return array('error_code' => 15);

        }

        $this->user['avatar'] = cmsModel::yamlToArray($this->user['avatar']);
        if ($this->user['avatar']){
            foreach($this->user['avatar'] as $size => $path){
                $this->user['avatar'][$size] = $this->cms_config->upload_host_abs.'/'.$path;
            }
        }

        $this->user['is_online'] = true;

        cmsEventsManager::hook('auth_login', $logged_id);

        return false;

    }

    public function run(){

        $this->result = array(
            'session_name' => session_name(),
            'session_id'   => session_id(),
            'expires_in'   => ini_get('session.gc_maxlifetime'),
            'user_id'      => $this->user['id'],
            'user_info'    => $this->user
        );

    }

}
