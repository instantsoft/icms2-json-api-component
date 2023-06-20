<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		                  http://instantmedia.ru/                         //
//                                written by Fuze                             //
//                                                                            //
/******************************************************************************/

class actionApiMethod extends cmsAction {

    private $method_params          = [];
    private $method_controller_name = null;
    private $method_action_name     = null;

    /**
     * Объект контроллера api метода
     * @var object
     */
    private $method_controller = null;

    /**
     * Объект класса api метода
     * @var object
     */
    private $method_action = null;

    public function __construct($controller, $params = []) {

        parent::__construct($controller, $params);

        $this->loadApiKey();

    }

    /**
     * Инициализирует метод:
     * загружает контроллер, экшен
     * @param string $method_name
     * @return \actionApiMethod
     */
    private function initMethod($method_name) {

        $this->method_name = $method_name;

        if (!$this->method_name) {
            return $this;
        }

        $segments = explode('.', $method_name);

        // контроллер
        if (isset($segments[0])) {

            $this->method_controller_name = trim($segments[0]);

            if ($this->method_controller_name && !preg_match('/^[a-z]{1}[a-z0-9_]*$/', $this->method_controller_name)) {
                $this->method_controller_name = null;
            }

            if ($this->method_controller_name && !cmsCore::isControllerExists($this->method_controller_name)) {
                $this->method_controller_name = null;
            }

            if ($this->method_controller_name) {
                $this->method_controller = cmsCore::getController($this->method_controller_name, $this->request);
            }
        }
        // действие
        if (isset($segments[1])) {

            $this->method_action_name = trim($segments[1]);

            if ($this->method_action_name && !preg_match('/^[a-z]{1}[a-z0-9_]*$/', $this->method_action_name)) {
                $this->method_action_name = null;
            }

            if ($this->method_action_name && $this->method_controller !== null) {
                $this->method_controller->current_action = 'api_' . $this->method_controller_name . '_' . $this->method_action_name;
            }
        }
        // Параметры действия
        if (count($segments) > 2) {
            $this->method_params = array_slice($segments, 2);
        }

        return $this;
    }

    /**
     * Выполняет метод api запроса
     * Метод строится по принципу controller.action_name
     * action_name - это специальный экшен. В самом контроллере-родителе он
     * называться должен с префиксом api_request_, например api_request_action_name
     * @param string $method_name Название метода API
     */
    public function run($method_name = null){

        // устанавливаем метод и проверяем запрос
        if(!$this->initMethod($method_name)->checkRequest()){
            return false;
        }

        // проверяем сначала экшен
        // Важно! Болокируйте экшен от прямого выполнения свойством lock_explicit_call
        // https://docs.instantcms.ru/dev/controllers/actions#действия-во-внешних-файлах

        $api_dir_action_file = $this->root_path.'api_actions/'.$this->method_controller->current_action.'.php';
        $action_file = $this->method_controller->root_path.'actions/'.$this->method_controller->current_action.'.php';
        $action_file = is_readable($api_dir_action_file) ? $api_dir_action_file : $action_file;

        if(is_readable($action_file)){

            $class_name = 'action'.string_to_camel('_', $this->method_controller_name).string_to_camel('_', $this->method_controller->current_action);

            include_once $action_file;

            if(!class_exists($class_name, false)){
                cmsCore::error(sprintf(ERR_CLASS_NOT_DEFINED, str_replace(PATH, '', $action_file), $class_name));
            }

            $this->method_action = new $class_name($this->method_controller);

        } else {

            // теперь проверяем хук
            $hook_file = $this->method_controller->root_path . 'hooks/'.$this->method_controller->current_action.'.php';

            $class_name = 'on'.string_to_camel('_', $this->method_controller_name).string_to_camel('_', $this->method_controller->current_action);

            if (is_readable($hook_file)){

                if (!class_exists($class_name, false)){
                    include_once $hook_file;
                }

                if(!class_exists($class_name, false)){
                    cmsCore::error(sprintf(ERR_CLASS_NOT_DEFINED, str_replace(PATH, '', $hook_file), $class_name));
                }

                $this->method_action = new $class_name($this->method_controller);

            }

        }

        // нечего запускать
        if($this->method_action === null){
            return $this->error(3);
        }

        // ставим свойство результирующего массива, если такового нет
        if(!isset($this->method_action->result)){
            $this->method_action->result = array(
                'count' => 0,
                'items' => array()
            );
        }

        // если передан ip адрес, считаем его адресом посетителя
        // для различных проверок компонентов
        // т.к. движок определяет ip адрес места запроса
        if($this->request->has('ip')){

            $ip = $this->request->get('ip', '');

            if (!$ip || filter_var($ip, FILTER_VALIDATE_IP) !== $ip) {
                return $this->error(777);
            }

            // совместимость
            if(method_exists('cmsUser', 'setIp')){
                cmsUser::setIp($ip);
            }

        }

        // проверяем csrf, если включена проверка
        if(!empty($this->method_action->check_csrf)){
            if (!cmsForm::validateCSRFToken($this->request->get('csrf_token', ''))){
                return $this->error(0, LANG_API_ERROR_CSRF_TOKEN);
            }
        }

        // проверяем sig, если включена проверка
        if(!empty($this->method_action->check_sig)){
            if(!check_sig($this->request->get('sig', ''))){
                return $this->error(115);
            }
        }

        // проверяем авторизацию, если метод её требует
        if(!empty($this->method_action->auth_required)){
            if(!$this->cms_user->is_logged){
                return $this->error(71);
            }
        }

        // проверяем админ доступ, если метод этого требует
        if(!empty($this->method_action->admin_required)){
            if(!$this->cms_user->is_logged){
                return $this->error(71);
            }
            if(!$this->cms_user->is_admin){
                return $this->error(710);
            }
            // грузим язык админки
            cmsCore::loadControllerLanguage('admin');
        }

        // ставим ключ API в свойство
        $this->method_action->key = $this->key;
        $this->method_action->method_name = $this->method_name;
        // опции api в свойство
        $this->method_action->api_options = $this->options;

        // валидация параметров запроса
        $params_error = $this->validateMethodParams();
        if($params_error !== false){
            return $this->error(100, '', $params_error);
        }

        // валидация запроса, если нужна
        if(method_exists($this->method_action, 'validateApiRequest')){
            $error = call_user_func_array(array($this->method_action, 'validateApiRequest'), $this->method_params);
            if($error !== false){
                return $this->error(
                    (isset($error['error_code']) ? $error['error_code'] : 100),
                    (isset($error['error_msg']) ? $error['error_msg'] : ''),
                    (isset($error['request_params']) ? $error['request_params'] : array())
                );
            }
        }

        // сам запрос
        // экшен/хук формирует данные в свойство $this->method_action->result
        // вся обработка ошибок на этом этапе должна быть закончена
        call_user_func_array(array($this->method_action, 'run'), $this->method_params);

        // если нужно убрать ячейки
        if(isset($this->method_action->unset_fields)){
            foreach ($this->method_action->unset_fields as $key_name => $unset) {

                if($unset['type'] == 'item'){

                    foreach ($unset['unsets'] as $unset_field) {
                        unset($this->method_action->result[$key_name][$unset_field]);
                    }

                } else {

                    foreach ($this->method_action->result[$key_name] as $key => $item) {
                        foreach ($unset['unsets'] as $unset_field) {
                            unset($this->method_action->result[$key_name][$key][$unset_field]);
                        }
                    }

                }

            }
        }

        // если передали разбивку на страницы, формируем флаг наличия следующей страницы
        if(!empty($this->method_action->result['paging'])){

            $pages = ceil($this->method_action->result['count'] / $this->method_action->result['paging']['per_page']);
            if($pages > $this->method_action->result['paging']['page']){
                $this->method_action->result['paging']['has_next'] = true;
            } else {
                $this->method_action->result['paging']['has_next'] = false;
            }

        }

        // фиксируем результат запроса
        $this->setSuccess($this->method_action->result);

        // действия после успешного запроса
        return $this->afterRequest();
    }

    /**
     * Действия после успешного запроса
     * @return boolean
     */
    private function afterRequest() {

        // записываем в лог, если включено
        if (!empty($this->options['log_success'])) {

            $this->model->log([
                'request_time' => number_format((microtime(true) - $this->start_time), 4),
                'method'       => $this->method_name,
                'key_id'       => $this->key['id']
            ]);
        }

        return true;
    }

    private function validateMethodParams() {

        if (empty($this->method_action->request_params)) {
            return false;
        }

        $errors = [];

        // валидация аналогична валидации форм
        foreach ($this->method_action->request_params as $param_name => $rules) {

            $value = $this->request->get($param_name, null);

            if (is_null($value) && isset($rules['default'])) {

                $value = $rules['default'];

                $this->request->set($param_name, $value);

            } elseif (!is_null($value) && isset($rules['default'])) {

                $value = $this->request->get($param_name, $rules['default']);

                // для применения типизации переменной
                $this->request->set($param_name, $value);
            }

            if (!empty($rules['rules'])) {
                foreach ($rules['rules'] as $rule) {

                    if (!$rule) {
                        continue;
                    }

                    $validate_function = "validate_{$rule[0]}";

                    $rule[] = $value;

                    unset($rule[0]);

                    $result = call_user_func_array([$this, $validate_function], $rule);

                    // если получилось false, то дальше не проверяем, т.к.
                    // ошибка уже найдена
                    if ($result !== true) {
                        $errors[$param_name] = $result;
                        break;
                    }
                }
            }
        }

        if (!sizeof($errors)) {
            return false;
        }

        return $errors;
    }

    /**
     * Проверяет запрос на ошибки
     * @return boolean
     */
    public function checkRequest() {

        $parent_succes = parent::checkRequest();

        if (!$parent_succes) {
            return false;
        }

        if (empty($this->method_name) ||
                empty($this->method_controller_name) ||
                $this->method_controller === null) {

            return $this->error(3);
        }

        if (empty($this->method_action_name)) {
            return $this->error(8);
        }

        if (!$this->method_controller->isEnabled()) {
            return $this->error(23);
        }

        $check_method_name = $this->method_controller_name . '.' . $this->method_action_name;

        $is_view = !$this->key['key_methods']['allow'] || in_array($check_method_name, $this->key['key_methods']['allow']);
        $is_hide = $this->key['key_methods']['disallow'] && in_array($check_method_name, $this->key['key_methods']['disallow']);

        // проверяем доступ к методу
        if (!$is_view || $is_hide) {
            return $this->error(24);
        }

        return true;
    }

}
