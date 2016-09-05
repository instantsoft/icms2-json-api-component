<?php
/******************************************************************************/
//                                                                            //
//                             InstantMedia 2016                              //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class actionApiMethod extends cmsAction {

    private $key = null;

    private $method_name       = null;
    private $method_params     = array();
    private $method_controller_name = null;
    private $method_action_name     = null;

    private $method_controller = null;
    private $method_action     = null;

    public function __construct($controller, $params=array()){

        parent::__construct($controller, $params);

        $this->loadApiKey();

        // для метода after ставим коллбэк, нам не нужен вывод на экран шаблона
        $this->setCallback('after', array(array($controller, 'renderJSON')));

    }

    /**
     * Инициализирует метод:
     * загружает контроллер, экшен
     * @param string $method_name
     * @return \actionApiMethod
     */
    private function initMethod($method_name) {

        $this->method_name = $method_name;
        if(empty($this->method_name)){ return $this; }

        $segments = explode('.', $method_name);

        // контроллер
        if (isset($segments[0])) {

            $this->method_controller_name = trim($segments[0]);

            if ($this->method_controller_name && !preg_match('/^[a-z]{1}[a-z0-9_]*$/', $this->method_controller_name)){
                $this->method_controller_name = null;
            }

            if ($this->method_controller_name && !cmsCore::isControllerExists($this->method_controller_name)) {
                $this->method_controller_name = null;
            }

            if($this->method_controller_name){
                $this->method_controller = cmsCore::getController($this->method_controller_name, $this->request);
            }

        }
        // действие
        if (isset($segments[1])) {

            $this->method_action_name = trim($segments[1]);

            if ($this->method_action_name && !preg_match('/^[a-z]{1}[a-z0-9_]*$/', $this->method_action_name)){
                $this->method_action_name = null;
            }

            if($this->method_action_name && $this->method_controller !== null){
                $this->method_controller->current_action = 'api_'.$this->method_controller_name.'_'.$this->method_action_name;
            }

        }
        // Параметры действия
        if (count($segments) > 2){
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
        // http://docs.instantcms.ru/dev/controllers/actions#действия-во-внешних-файлах

        $api_dir_action_file = $this->root_path.'api_actions/'.$this->method_controller->current_action.'.php';
        $action_file = $this->method_controller->root_path.'actions/'.$this->method_controller->current_action.'.php';
        $action_file = is_readable($api_dir_action_file) ? $api_dir_action_file : $action_file;

        if(is_readable($action_file)){

            $class_name = 'action'.string_to_camel('_', $this->method_controller_name).string_to_camel('_', $this->method_controller->current_action);

            include_once $action_file;

            $this->method_action = new $class_name($this->method_controller);

        } else {

            // теперь проверяем хук
            $hook_file = $this->method_controller->root_path . 'hooks/'.$this->method_controller->current_action.'.php';

            $class_name = 'on'.string_to_camel('_', $this->method_controller_name).string_to_camel('_', $this->method_controller->current_action);

            if (is_readable($hook_file)){

                if (!class_exists($class_name)){
                    include_once $hook_file;
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

        // валидация параметров запроса
        $params_error = $this->validateMethodParams();
        if($params_error !== false){
            return $this->error(100, '', $params_error);
        }

        // валидация запроса, если нужна
        if(method_exists($this->method_action, 'validateApiRequest')){
            $error = call_user_func_array(array($this->method_action, 'validateApiRequest'), $this->method_params);
            if($error !== false){
                return $this->error(100, $error['error_msg']);
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
        if(!empty($this->options['log_success'])){
            $this->model->log(array(
                'request_time' => number_format(cmsCore::getTime(), 4),
                'method' => $this->method_name,
                'key_id' => $this->key['id']
            ));
        }

        return true;

    }

    private function validateMethodParams() {

        if(empty($this->method_action->request_params)){
            return false;
        }

        $errors = array();

        // фалидация аналогична валидации форм
        foreach ($this->method_action->request_params as $param_name => $rules) {

            $value = $this->request->get($param_name, null);

            if (is_null($value) && isset($rules['default'])) {

                $value = $rules['default'];

                $this->request->set($param_name, $value);

            }

            foreach ($rules['rules'] as $rule) {

                if (!$rule) { continue; }

                $validate_function = "validate_{$rule[0]}";

                $rule[] = $value;

                unset($rule[0]);

                $result = call_user_func_array(array($this, $validate_function), $rule);

                // если получилось false, то дальше не проверяем, т.к.
                // ошибка уже найдена
                if ($result !== true) {
                    $errors[$param_name] = $result;
                    break;
                }

            }
        }

        if (!sizeof($errors)) { return false; }

        return $errors;

    }

    /**
     * Проверяет запрос на ошибки
     * @return boolean
     */
    public function checkRequest() {

        $parent_succes = parent::checkRequest();
        if(!$parent_succes){ return false; }

        if(empty($this->method_name) ||
                empty($this->method_controller_name) ||
                $this->method_controller === null){
            return $this->error(3);
        }

        if(empty($this->method_action_name)){
            return $this->error(8);
        }

        if(!$this->method_controller->isEnabled()){
            return $this->error(23);
        }

        return true;

    }

}
