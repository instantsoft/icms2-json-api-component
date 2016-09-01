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
    private $method_name = null;

    public function __construct($controller, $params=array()){

        parent::__construct($controller, $params);

        $headers = apache_request_headers();

        $api_key = !empty($headers['api_key']) ? $headers['api_key'] : $this->request->get('api_key', '');

        $this->key = $this->model->getKey($api_key);

    }

    public function __destruct() {
        return $this->cms_template->renderJSON($this->getOutputdata(), true);
    }

    public function setMethodName($method_name) {
        $this->method_name = $method_name; return $this;
    }

    public function run($method_name = null){

        // устанавливаем метод и проверяем запрос
        $this->setMethodName($method_name)->checkRequest();

        // логика запроса и разбор методов

        // действия после успешного запроса
        $this->afterRequest();

    }

    private function afterRequest() {

        if(!empty($this->options['log_success'])){
            $this->model->log(array(
                'method' => $this->method_name,
                'key_id' => $this->key['id']
            ));
        }

        return $this;

    }

    private function checkRequest() {

        if(empty($this->key)){
            $this->error(101);
        }

        if($this->key['allow_ips'] && !string_in_mask_list(cmsUser::getIp(), $this->key['allow_ips'])){
            $this->error(15);
        }

        if(!$this->key['is_pub']){
            $this->error(2);
        }

        if(empty($this->method_name)){
            $this->error(3);
        }

        return $this;

    }

    private function error($error_code, $error_msg = '') {

        if(!empty($this->options['log_error'])){
            $this->model->log(array(
                'error'  => $error_code,
                'method' => $this->method_name,
                'key_id' => (!empty($this->key['id']) ? $this->key['id'] : null)
            ));
        }

        $this->setError($error_code, $error_msg);

        die;

    }

}
