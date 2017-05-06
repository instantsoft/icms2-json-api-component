<?php

class actionUsersApiUsersGetGroups extends cmsAction {

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

    /**
     * Возможные параметры запроса
     * с правилами валидации
     * Если запрос имеет параметры, необходимо описать их здесь
     * Правила валидации параметров задаются по аналогии с полями форм
     * @var array
     */
    public $request_params = array();

    public function validateApiRequest() {
        return false;
    }

    public function run(){

        $groups = $this->model->getGroups();

        $this->result['count'] = count($groups);
        $this->result['items'] = $groups;
        $this->result['sig']   = get_sig();

    }

}
