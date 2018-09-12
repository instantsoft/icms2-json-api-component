<?php

class actionUsersApiUsersAdd extends cmsAction {

    public function __construct($controller, $params=array()) {

        parent::__construct($controller, $params);

        $this->is_submitted = $this->request->has('submit');

        if($this->is_submitted){
            $this->check_sig = true;
        }

    }

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
    public $check_sig = false;

    public $admin_required = true;

    /**
     * Возможные параметры запроса
     * с правилами валидации
     * Если запрос имеет параметры, необходимо описать их здесь
     * Правила валидации параметров задаются по аналогии с полями форм
     * @var array
     */
    public $request_params = array();

    private $is_submitted = false;

    public function validateApiRequest() {

        if(!$this->is_submitted){
            return false;
        }

        $form = $this->getUserForm();
        if(!$form){ return array('error_code' => 1); }

        // загружаем модель пользователя
        $this->users_model = cmsCore::getModel('users');

        $user = $form->parse($this->request, true);

        $errors = $form->validate($this,  $user, false);

        if (mb_strlen($user['password1']) < 6) {
            $errors['password1'] = sprintf(ERR_VALIDATE_MIN_LENGTH, 6);
        }

        if($errors){

            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => $errors
            );

        }

        $result = $this->users_model->addUser($user);

        if (!$result['success']){

            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => (array)$result['errors']
            );

        }

        $user['id'] = $result['id'];

        cmsUser::setUPS('first_auth', 1, $user['id']);

        $this->user = $user;

        return false;

    }

    public function run(){

        if(!$this->is_submitted){
            return $this->returnForm();
        }

        $this->result = array(
            'user_id'         => $this->user['id'],
            'is_verify_email' => false,
            'success_text'    => sprintf(LANG_CP_USER_CREATED, $this->user['nickname'])
        );

    }

    private function returnForm() {

        $this->result = array();

        $form = $this->getUserForm();
        if(!$form){ return; }

        $this->result['item'] = form_to_params($form);
        $this->result['sig']  = get_sig();

    }

    private function getUserForm() {

        cmsCore::loadControllerLanguage('admin');

        $form = $this->getControllerForm('admin', 'user', array('add'));
        if(!$form){ return false; }

        $form->removeFieldset('permissions');

        return $form;

    }

}
