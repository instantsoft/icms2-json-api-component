<?php

class actionContentApiContentGetFolders extends cmsAction {

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
        'user_id' => array(
            'rules'   => array(
                array('required'),
                array('digits')
            )
        ),
        'ids' => array(
            'default' => '',
            'rules'   => array(
                array('regexp', '/^([0-9,]+)$/i')
            )
        )
    );

    /**
     * Массив ключей для удаления
     * @var array
     */
    public $unset_fields = array(
        'items' => array(       // название ключа в $this->result
            'type'   => 'list', // list или item
            'unsets' => array(  // массив названий ключей для удаления
                'handler'
            )
        )
    );

    /**
     * Служебное свойство типа контента
     * для этого экшена
     * @var array
     */
    private $ctype;

    public function validateApiRequest($ctype_name=null) {

        if(!$ctype_name){
            return array('error_msg' => LANG_API_EMPTY_CTYPE);
        }

        $this->ctype = $this->model->getContentTypeByName($ctype_name);

        if(!$this->ctype){
            return array('error_msg' => LANG_API_EMPTY_CTYPE);
        }

        return false;

    }

    public function run($ctype_name){

        $ids = $this->request->get('ids');
        if($ids){

            $ids = explode(',', $ids);
            $ids = array_filter($ids);

            $this->model->filterIn('id', $ids);

        }

        $fields = $this->model->getContentFolders($this->ctype['id'], $this->request->get('user_id'));

        $this->result['count'] = count($fields);
        $this->result['items'] = $fields;

    }

}
