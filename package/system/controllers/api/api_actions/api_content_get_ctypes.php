<?php

class actionContentApiContentGetCtypes extends cmsAction {

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

    /**
     * Служебное свойство типа контента
     * для этого экшена
     * @var array
     */
    private $ctype;

    public function validateApiRequest($ctype_name=null) {

        if(!$ctype_name){ return false; }

        $this->ctype = $this->model->getContentTypeByName($ctype_name);

        if(!$this->ctype){
            return array(
                'error_code' => 322
            );
        }

        return false;

    }

    public function run(){

        if(empty($this->ctype)){

            $ctypes = $this->model->getContentTypes();

            $this->result['count'] = count($ctypes);
            $this->result['items'] = $ctypes;

        } else {

            $this->result = $this->ctype;

        }

    }

}
