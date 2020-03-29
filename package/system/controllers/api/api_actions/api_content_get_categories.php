<?php

class actionContentApiContentGetCategories extends cmsAction {

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
        'cat_ids' => array(
            'default' => '',
            'rules'   => array(
                array('regexp', '/^([0-9,]+)$/i')
            )
        ),
        'is_tree' => array(
            'default' => 0,
            'rules'   => array(
                array('digits')
            )
        ),
        'cat_level' => array(
            'default' => 0,
            'rules'   => array(
                array('digits')
            )
        ),
        'parent_id' => array(
            'default' => 1,
            'rules'   => array(
                array('digits')
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

        if (empty($this->ctype['is_cats'])) {
            return;
        }

        $categories = array();

        $cat_ids = $this->request->get('cat_ids');
        if($cat_ids){

            $cat_ids = explode(',', $cat_ids);
            $cat_ids = array_filter($cat_ids);

            if($cat_ids){

                $this->model->filterIn('id', $cat_ids);

                $categories = $this->model->get($this->model->table_prefix.$ctype_name.'_cats');

            }

        } else {

            if($this->request->get('is_tree')){

                $categories = $this->model->getSubCategoriesTree($this->ctype['name'], $this->request->get('parent_id'), $this->request->get('cat_level'));

            } else {

                $categories = $this->model->getSubCategories($this->ctype['name'], $this->request->get('parent_id'));

            }

        }

        $this->result['count'] = count($categories);
        $this->result['items'] = $categories;

    }

}
