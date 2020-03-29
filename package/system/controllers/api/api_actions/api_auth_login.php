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
        ),
        'remember' => array(
            'default' => 0
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
                'password_hash', 'date_token', 'ip', 'is_admin', 'ga_secret'
            )
        )
    );

    private $user = [];
    private $wait_2fa = false;
    private $twofa_type = '';
    private $twofa_params = [];

    public function validateApiRequest() {

        cmsCore::loadControllerLanguage('users');

        // если авторизован, проверки не выполняем
        if($this->cms_user->is_logged){

            $this->user = $this->model_users->getUser($this->cms_user->id);

            return false;

        }

        $logged_user = cmsUser::login($this->request->get('email'), $this->request->get('password'), $this->request->get('remember'), false);

        if (!$logged_user){
            return array(
                'error_code' => 5
            );
        }

        // Включена ли двухфакторная авторизация
        if(!empty($logged_user['2fa']) && !empty($this->options['2fa_params'][$logged_user['2fa']])){

            $twofa_params = $this->options['2fa_params'][$logged_user['2fa']];

            $context_request = clone $this->request;

            // Чтобы сработало свойство $lock_explicit_call в экшене $twofa_params['action']
            $context_request->setContext(cmsRequest::CTX_INTERNAL);

            // Говорим, что это вызов из API
            $context_request->set('api_context', true);

            $result = cmsCore::getController($twofa_params['controller'], $context_request)->
                    executeAction($twofa_params['action'], [$logged_user, new cmsForm(), [
                        'email' => $this->request->get('email'),
                        'password' => $this->request->get('password'),
                        'remember' => $this->request->get('remember')
                    ]]);

            // Говорим методу run(), что нужен 2fa
            if($result !== true){

                // Если уже был сабмит параметров
                // и возникли ошибки
                if($result['errors']){

                    return array(
                        'error_code'     => 100,
                        'error_msg'      => '',
                        'request_params' => $result['errors']
                    );

                }

                $this->wait_2fa = true;
                $this->twofa_type = $logged_user['2fa'];

                $result['form'] = form_to_params($result['form']);

                $this->twofa_params = $result;

                // Передаём управление в метод run()
                return false;
            }

        }

        if (empty($this->api_options['allow_admin_login']) && $logged_user['is_admin']) {
            return array('error_code' => 15);
        }

        // Проверяем блокировку пользователя
        if ($logged_user['is_locked']) {

            $now = time();
            $lock_until = !empty($logged_user['lock_until']) ? strtotime($logged_user['lock_until']) : false;

            if ($lock_until && ($lock_until <= $now)){
                $this->model_users->unlockUser($logged_user['id']);
            } else {

                $notice_text = array(LANG_USERS_LOCKED_NOTICE);

                if($logged_user['lock_until']) {
                    $notice_text[] = sprintf(LANG_USERS_LOCKED_NOTICE_UNTIL, $logged_user['lock_until']);
                }

                if($logged_user['lock_reason']) {
                    $notice_text[] = sprintf(LANG_USERS_LOCKED_NOTICE_REASON, $logged_user['lock_reason']);
                }

                if($logged_user['lock_reason']){
                    $this->model_users->update('{users}', $logged_user['id'], array(
                        'ip' => null
                    ), true);
                }

                return array('error_msg' => implode("\n", $notice_text));

            }

        }

        // завершаем авторизацию
        cmsUser::loginComplete($logged_user, $this->request->get('remember'));

        $is_first_auth = cmsUser::getUPS('first_auth', $logged_user['id']);

        if ($is_first_auth){
            cmsUser::deleteUPS('first_auth', $logged_user['id']);
        }

        // Всё успешно
        $this->user = $logged_user;

        $this->user['avatar'] = cmsModel::yamlToArray($this->user['avatar']);
        if ($this->user['avatar']){
            foreach($this->user['avatar'] as $size => $path){
                $this->user['avatar'][$size] = $this->cms_config->upload_host_abs.'/'.$path;
            }
        }

        $this->user['is_online'] = true;

        return false;

    }

    public function run(){

        $is_first_auth = null;

        if(!empty($this->user['id'])){
            if(cmsUser::getUPS('first_auth', $this->user['id'])){
                cmsUser::deleteUPS('first_auth', $this->user['id']);
                $is_first_auth = true;
            } else {
                $is_first_auth = false;
            }
        }

        $this->result = array(
            'wait_2fa'     => $this->wait_2fa,
            '2fa_type'     => $this->twofa_type,
            '2fa_params'   => $this->twofa_params,
            'is_first_auth' => $is_first_auth,
            'remember_token' => (isset(cmsUser::$auth_token) ? cmsUser::$auth_token : false),
            'session_name' => session_name(),
            'session_id'   => session_id(),
            'expires_in'   => ini_get('session.gc_maxlifetime'),
            'user_id'      => !empty($this->user['id']) ? intval($this->user['id']) : 0,
            'user_info'    => $this->user
        );

    }

}

