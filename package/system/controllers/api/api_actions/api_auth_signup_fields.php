<?php

class actionAuthApiAuthSignupFields extends cmsAction {

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

    public function validateApiRequest() {

        if (empty($this->options['is_reg_enabled'])){
            return array('error_code' => 323);
        }

        if (!$this->isIPAllowed(cmsUser::get('ip'))){

            return array(
                'error_code' => 15,
                'error_msg'  => strip_tags(sprintf(LANG_AUTH_RESTRICTED_IP, cmsUser::get('ip')))
            );

        }

        return false;

    }

    public function run(){

        $this->result = array();

        $form = $this->getForm('registration');
        if(!$form){ return; }

        //
        // Добавляем поле для кода приглашения,
        // если регистрация доступна только по приглашениям
        //
        if ($this->options['is_reg_invites']){

            $fieldset_id = $form->addFieldsetToBeginning(LANG_REG_INVITED_ONLY);

            $form->addField($fieldset_id, new fieldString('inv', array(
                'title' => LANG_REG_INVITE_CODE,
                'rules' => array(
                    array('required'),
                    array('min_length', 10),
                    array('max_length', 10)
                )
            )));

        }

        //
        // Добавляем поле выбора группы,
        // при наличии публичных групп
        //
        $public_groups = cmsCore::getModel('users')->getPublicGroups();

        if ($public_groups) {

            $pb_items = array();
            foreach($public_groups as $pb) { $pb_items[ $pb['id'] ] = $pb['title']; }

            $form->addFieldToBeginning('basic',
                new fieldList('group_id', array(
                        'title' => LANG_USER_GROUP,
                        'items' => $pb_items
                    )
                )
            );

        }

        //
        // Добавляем в форму обязательные поля профилей
        //
        $content_model = cmsCore::getModel('content');
        $content_model->setTablePrefix('');
        $content_model->orderBy('ordering');
        $fields = $content_model->getRequiredContentFields('{users}');

        // Разбиваем поля по группам
        $fieldsets = cmsForm::mapFieldsToFieldsets($fields);

        // Добавляем поля в форму
        foreach($fieldsets as $fieldset){

            $fieldset_id = $form->addFieldset($fieldset['title']);

            foreach($fieldset['fields'] as $field){
                if ($field['is_system']) { continue; }
                $form->addField($fieldset_id, $field['handler']);
            }

        }

        $this->result['item'] = form_to_params($form);
        $this->result['sig']  = get_sig();

    }

}
