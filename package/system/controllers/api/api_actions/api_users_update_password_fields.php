<?php

class actionUsersApiUsersUpdatePasswordFields extends cmsAction {

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

    public $auth_required = true;

    public function run(){

        $this->result = array();

        $form = $this->getForm('password');
        if(!$form){ return; }

        $this->result['item'] = form_to_params($form);
        $this->result['sig']  = get_sig();

    }

}
