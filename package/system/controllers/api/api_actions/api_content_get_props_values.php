<?php

class actionContentApiContentGetPropsValues extends cmsAction {

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
        'item_id' => array(
            'rules' => array(
                array('required'),
                array('digits')
            )
        ),
        'cat_id' => array(
            'rules' => array(
                array('required'),
                array('digits'),
                array('min', 2)
            )
        ),
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

        // просмотр записей запрещен
        if (empty($this->ctype['options']['item_on']) || empty($this->ctype['is_cats'])) { return; }

        $props_results = array();

        $props = $this->model->getContentProps($this->ctype['name'], $this->request->get('cat_id'));

        $props_values = $this->model->getPropsValues($this->ctype['name'], $this->request->get('item_id'));

        if ($props && array_filter((array)$props_values)) {

            $props_fields    = $this->getPropsFields($props);
            $props_fieldsets = cmsForm::mapFieldsToFieldsets($props);

            foreach($props_fieldsets as $fieldset){

                $props_result = array(
                    'title'  => $fieldset['title'],
                    'fields' => array()
                );
                if ($fieldset['fields']){
                    foreach($fieldset['fields'] as $prop){
                        if (isset($props_values[$prop['id']])) {
                            $prop_field = $props_fields[$prop['id']];
                            $props_result['fields'][$prop['id']] = array(
                                'title' => $prop['title'],
                                'value' => $prop_field->parse($props_values[$prop['id']])
                            );
                        }
                    }
                }

                $props_results[] = $props_result;

            }
        }

        $this->result['count'] = count($props_results);
        $this->result['items'] = $props_results;

    }

}
