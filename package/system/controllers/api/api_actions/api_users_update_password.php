<?php

class actionUsersApiUsersUpdatePassword extends cmsAction {

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
    public $result = array();

    public $auth_required = true;

    public $check_sig = true;

    private $user;

    public function validateApiRequest() {

        cmsCore::loadControllerLanguage('auth');

        $this->user = $this->model->getUser($this->cms_user->id);

        $form = $this->getForm('password');
        if(!$form){ return array('error_code' => 1); }

        $data = $form->parse($this->request, true);

        $errors = $form->validate($this, $data);

        if($errors === true){
            $errors = array('csrf_token' => LANG_API_ERROR_CSRF_TOKEN);
        }

        if (!$errors){

            // совместимость
            if(method_exists($this->model, 'getUserByAuth')){

                $user = $this->model->getUserByAuth($this->user['email'], $data['password']);

                if (!$user){
                    $errors = array('password' => LANG_OLD_PASS_INCORRECT);
                }

            } else {

                $password_hash = md5(md5($data['password']) . $this->cms_user->password_salt);

                if ($password_hash != $this->cms_user->password){
                    $errors = array('password' => LANG_OLD_PASS_INCORRECT);
                }

            }

        }

        if($errors){

            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => $errors
            );

        }

        unset($data['password']);

        $result = $this->model->updateUser($this->user['id'], $data);

        if (!$result['success']){

            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => $result['errors']
            );

        }

        return false;

    }

    public function run(){

        $this->result = array(
            'success_text' => LANG_PASS_CHANGED,
            'user_id'      => $this->user['id']
        );

    }

}
