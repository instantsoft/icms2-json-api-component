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

        $headers = apache_request_headers();

        $api_key = !empty($headers['api_key']) ? $headers['api_key'] : $this->request->get('api_key', '');

        $this->key = $this->model->getKey($api_key);

    }

    /**
     * Нам не нужен вывод на экран шаблона
     * @return exit
     */
    public function __destruct() {
        return $this->cms_template->renderJSON($this->getOutputdata(), true);
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
                $this->method_controller->current_action = 'api_request_'.$this->method_action_name;
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
        $this->initMethod($method_name)->checkRequest();

        // проверяем сначала экшен
        // Важно! Болокируйте экшен от прямого выполнения свойством lock_explicit_call
        // http://docs.instantcms.ru/dev/controllers/actions#действия-во-внешних-файлах
        $action_file = $this->method_controller->root_path . 'actions/'.$this->method_controller->current_action.'.php';

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
            $this->error(3);
        }

        // валидация запроса, если нужна
        if(method_exists($this->method_action, 'validateApiRequest')){
            $error = call_user_func_array(array($this->method_action, 'validateApiRequest'), $this->method_params);
            if($error !== false){
                $this->error(100, $error['error_msg']);
            }
        }

        // сам запрос
        // экшен/хук должен отдавать строго массив данных
        // вся обработка ошибок на этом этапе должна быть закончена
        $this->setSuccess(call_user_func_array(array($this->method_action, 'run'), $this->method_params));

        // действия после успешного запроса
        $this->afterRequest();

    }

    /**
     * Действия после успешного запроса
     * @return \actionApiMethod
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

        return $this;

    }

    /**
     * Проверяет запрос на ошибки
     * Если находит - завершает работу
     * @return \actionApiMethod
     */
    private function checkRequest() {

        if(empty($this->key)){
            $this->error(101);
        }

        if($this->key['ip_access'] && !string_in_mask_list(cmsUser::getIp(), $this->key['ip_access'])){
            $this->error(15);
        }

        if(!$this->key['is_pub']){
            $this->error(2);
        }

        if(empty($this->method_name) ||
                empty($this->method_controller_name) ||
                $this->method_controller === null ||
                empty($this->method_action_name)){
            $this->error(3);
        }

        if(!$this->method_controller->isEnabled()){
            $this->error(23);
        }

        return $this;

    }

    /**
     * Фиксирует, выводит ошибку запроса и завершает работу
     * @param integer $error_code
     * @param string $error_msg
     */
    private function error($error_code, $error_msg = '') {

        // записываем в лог ошибку, если включена их фиксация
        if(!empty($this->options['log_error'])){
            $this->model->log(array(
                'request_time' => number_format(cmsCore::getTime(), 4),
                'error'  => $error_code,
                'method' => $this->method_name,
                'key_id' => (!empty($this->key['id']) ? $this->key['id'] : null)
            ));
        }

        $this->setError($error_code, $error_msg);

        die;

    }

}
