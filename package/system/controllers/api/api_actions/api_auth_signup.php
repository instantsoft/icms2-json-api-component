<?php

class actionAuthApiAuthSignup extends cmsAction {

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
    public $check_sig = true;

    /**
     * Возможные параметры запроса
     * с правилами валидации
     * Если запрос имеет параметры, необходимо описать их здесь
     * Правила валидации параметров задаются по аналогии с полями форм
     * @var array
     */
    public $request_params = array();

    private $user;

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

        $form = $this->getForm('registration');
        if(!$form){ return array('error_code' => 1); }

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
        $public_groups = $this->model_users->getPublicGroups();

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

        $user = $form->parse($this->request, true);

        $user['groups'] = array();

        if (!empty($this->options['def_groups'])){
            $user['groups'] = $this->options['def_groups'];
        }

        if (isset($user['group_id'])) {
            if (!in_array($user['group_id'], $user['groups'])){
                $user['groups'][] = $user['group_id'];
            }
        }

        //
        // убираем поля которые не относятся к выбранной пользователем группе
        //
        foreach($fieldsets as $fieldset){
            foreach($fieldset['fields'] as $field){
                if (!$field['groups_edit']) { continue; }
                if (in_array(0, $field['groups_edit'])) { continue; }
                if (!in_array($user['group_id'], $field['groups_edit'])){
                    $form->disableField($field['name']);
                    unset($user[$field['name']]);
                }
            }
        }

        $errors = $form->validate($this,  $user, false);

        if($errors){

            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => $errors
            );

        }

        //
        // проверяем код приглашения
        //
        if ($this->options['is_reg_invites']){
            $invite = $this->model->getInviteByCode($user['inv']);
            if (!$invite) {
                $errors['inv'] = LANG_REG_WRONG_INVITE_CODE;
            } else {
                if ($this->options['is_invites_strict'] && ($invite['email'] != $user['email'])) {
                    $errors['inv'] = LANG_REG_WRONG_INVITE_CODE_EMAIL;
                } else {
                    $user['inviter_id'] = $invite['user_id'];
                }
            }
        }

        //
        // проверяем допустимость e-mail и имени
        //
        if (!$this->isEmailAllowed($user['email'])){
            $errors['email'] = sprintf(LANG_AUTH_RESTRICTED_EMAIL, $user['email']);
        }

        if (!$this->isNameAllowed($user['nickname'])){
            $errors['nickname'] = sprintf(LANG_AUTH_RESTRICTED_NAME, $user['nickname']);
        }

        if($errors){

            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => $errors
            );

        }

        list($errors, $user) = cmsEventsManager::hook('registration_validation', array(false, $user));

        if($errors){

            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => $errors
            );

        }

        unset($user['inv']);

        //
        // Блокируем пользователя, если включена верификация e-mail
        //
        if ($this->options['verify_email']){
            $user = array_merge($user, array(
                'is_locked'   => true,
                'lock_reason' => LANG_REG_CFG_VERIFY_LOCK_REASON,
                'pass_token'  => string_random(32, $user['email']),
                'date_token'  => ''
            ));
        }

        $user['nickname'] = strstr($user['email'], '@', true);

        $result = $this->model_users->addUser($user);

        if (!$result['success']){

            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => (array)$result['errors']
            );

        }


        $user['id'] = $result['id'];

        cmsUser::setUPS('first_auth', 1, $user['id']);

        $this->user = $user;

        return false;

    }

    public function run(){

        // отправляем письмо верификации e-mail
        if ($this->options['verify_email']){

            $messenger = cmsCore::getController('messages');
            $to = array('email' => $this->user['email'], 'name' => $this->user['nickname']);
            $letter = array('name' => 'reg_verify');

            $messenger->sendEmail($to, $letter, array(
                'nickname'    => $this->user['nickname'],
                'page_url'    => href_to_abs('auth', 'verify', $this->user['pass_token']),
                'pass_token'  => $this->user['pass_token'],
                'valid_until' => html_date(date('d.m.Y H:i', time() + ($this->options['verify_exp'] * 3600)), true)
            ));

        } else {

            cmsEventsManager::hook('user_registered', $this->user);

        }

        $this->result = array(
            'user_id'         => $this->user['id'],
            'is_verify_email' => (bool) $this->options['verify_email'],
            'success_text'    => ($this->options['verify_email'] ? sprintf(LANG_REG_SUCCESS_NEED_VERIFY, $this->user['email']) : LANG_REG_SUCCESS)
        );

    }

}
