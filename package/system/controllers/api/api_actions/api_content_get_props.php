<?php

class actionContentApiContentGetProps extends cmsAction {

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
        'cat_id' => array(
            'rules'   => array(
                array('required'),
                array('digits')
            )
        ),
        'is_in_filter' => array(
            'default' => 0,
            'rules'   => array(
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

            $this->model->filterIn('p.id', $ids);

        }

        if($this->request->get('is_in_filter')){
            $this->model->filterEqual('p.is_in_filter', 1);
        }

        $fields = $this->model->getContentProps($this->ctype['name'], $this->request->get('cat_id'));

        $this->result['count'] = count($fields);
        $this->result['items'] = $fields;

    }

}
