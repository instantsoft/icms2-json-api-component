<?php

class actionUsersApiUsersAddToGroups extends cmsAction {

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

    public $admin_required = true;

    /**
     * Возможные параметры запроса
     * с правилами валидации
     * Если запрос имеет параметры, необходимо описать их здесь
     * Правила валидации параметров задаются по аналогии с полями форм
     * @var array
     */
    public $request_params = array(
        'user_id' => array(
            'default' => 0,
            'rules'   => array(
                array('required'),
                array('digits')
            )
        ),
        'group_ids' => array(
            'default' => array(),
            'rules'   => array(
                array('required')
            )
        )
    );

    private $users_model, $user;

    public function validateApiRequest() {

        $group_ids = $this->request->get('group_ids', array());

        foreach ($group_ids as $group_id) {
            if(!is_numeric($group_id)){
                return array('request_params' => array(
                    'group_ids' => ERR_VALIDATE_DIGITS
                ));
            }
        }

        $this->users_model = cmsCore::getModel('users');

        $this->user = $this->users_model->getUser($this->request->get('user_id'));

        if (!$this->user) {
            return array('error_code' => 113);
        }

        if ($this->user['is_admin']) {
            return array('error_code' => 15);
        }

        return false;

    }

    public function run(){

        $this->user['groups'] = array_merge($this->user['groups'], $this->request->get('group_ids', array()));
        $this->user['groups'] = array_unique($this->user['groups']);

        $this->model->updateUser($this->user['id'], array(
            'groups'     => $this->user['groups'],
            'date_group' => null
        ));

        $this->result = array(
            'success' => true,
            'groups'  => $this->user['groups']
        );

    }

}
