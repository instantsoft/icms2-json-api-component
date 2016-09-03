<?php
/******************************************************************************/
//                                                                            //
//                             InstantMedia 2016                              //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class api extends cmsFrontend {

	protected $useOptions = true;

    private $output_success = array();
    private $output_error = array();

    public $key = null;

    public function __construct($request){

        parent::__construct($request);

        // устанавливаем ошибку по-умолчанию
        $this->setError(1);

    }

    public function loadApiKey() {

        if($this->key !== null){ return $this; }

        $headers = apache_request_headers();

        $api_key = !empty($headers['api_key']) ? $headers['api_key'] : $this->request->get('api_key', '');

        $this->key = $this->model->getKey($api_key);

        return $this;

    }

    /**
     * Проверяет запрос на ошибки
     * @return boolean
     */
    public function checkRequest() {

        if(empty($this->key)){
            return $this->error(101);
        }

        if($this->key['ip_access'] && !string_in_mask_list(cmsUser::getIp(), $this->key['ip_access'])){
            return $this->error(15);
        }

        if(!$this->key['is_pub']){
            return $this->error(2);
        }

        return true;

    }

    /**
     * Универсальный метод, который позволяет запускать последовательность других методов,
     * сохраняя и фильтруя промежуточные результаты
     */
    public function actionExecute() {

        $this->setCallback('after', array(array($this, 'renderJSON')));

        $this->loadApiKey();

        if(!$this->checkRequest()){
            return false;
        }

        $code = $this->request->get('code', '');
        if(!$code){ return $this->error(100); }

        $methods = json_decode($code, true);
        if (json_last_error() !== JSON_ERROR_NONE || !$methods) {
            return $this->error(12);
        }

        $response = array();
        $max_method_count = 10;

        if(count($methods) > $max_method_count){
            return $this->error(13);
        }

        foreach ($methods as $method_param) {

            if(empty($method_param['method'])){ return $this->error(13); }

            $this->request->setData(!empty($method_param['params']) ? $method_param['params'] : array());

            $method_result = $this->runExternalAction('method', array($method_param['method']));

            if(!$method_result){ return $this->error(13); }

            $response[$method_param['method']] = $this->output_success['response'];

        }

        $this->setSuccess($response);

    }

    /**
     * Дополняем метод окончания вызова экшена
     * @param string $action_name
     * @return boolean
     */
    public function after($action_name){

        parent::after($action_name);

        $this->processCallback('after', array());

        return true;

    }

    /**
     * Результат запроса
     * @param array $api_request_result
     */
    public function setSuccess($api_request_result) {
        $this->output_success = array(
            'response' => $api_request_result
        );
    }

    /**
     * Устанавливает ошибку запроса
     * @param integer $error_code
     * @param string $error_msg
     * @param array $request_params
     */
    public function setError($error_code, $error_msg = '', $request_params = array()) {

        if($error_msg){
            $this->output_error['error'] = array(
                'error_code'     => ($error_code ? $error_code : 0),
                'error_msg'      => $error_msg,
                'request_params' => $request_params
            );
        } else {
            $this->output_error['error'] = array(
                'error_code'     => $error_code,
                'error_msg'      => constant('LANG_API_ERROR' . $error_code),
                'request_params' => $request_params
            );
        }

        // если уже есть результат, очищаем его
        $this->output_success = array();

        return $this;

    }

    /**
     * Фиксирует ошибку запроса
     * @param integer $error_code
     * @param string $error_msg
     * @param array $request_params
     */
    public function error($error_code, $error_msg = '', $request_params = array()) {

        // записываем в лог ошибку, если включена их фиксация
        if(!empty($this->options['log_error'])){
            $this->model->log(array(
                'request_time' => number_format(cmsCore::getTime(), 4),
                'error'  => $error_code,
                'method' => $this->method_name,
                'key_id' => (!empty($this->key['id']) ? $this->key['id'] : null)
            ));
        }

        $this->setError($error_code, $error_msg, $request_params);

        return false;

    }

    /**
     * Возвращает результирующий массив: либо массив ошибки, либо успешный результат работы
     * @return array
     */
    public function getOutputdata() {
        return ($this->output_success ? $this->output_success : $this->output_error);
    }

    public function renderJSON() {
        return $this->cms_template->renderJSON($this->getOutputdata(), true);
    }

}

// apache_request_headers replicement for nginx
if (!function_exists('apache_request_headers')) {
    function apache_request_headers() {
        foreach($_SERVER as $key=>$value) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $key=str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $out[$key]=$value;
            }else{
                $out[$key]=$value;
            }
        }
        return $out;
    }
}
