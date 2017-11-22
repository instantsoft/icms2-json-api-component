<?php

class actionWidgetsApiWidgetsGetPages extends cmsAction {

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
    public $request_params = array();

    public function validateApiRequest() {
        return false;
    }

    public function run(){

        $pages = $this->model->getPages();

        $this->result['count'] = count($pages);
        $this->result['items'] = $pages;

    }

}
