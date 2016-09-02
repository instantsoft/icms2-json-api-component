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

    public function __construct($request){

        parent::__construct($request);

        // устанавливаем ошибку по-умолчанию
        $this->setError(1);

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
     */
    public function setError($error_code, $error_msg = '') {

        if($error_msg){
            $this->output_error['error'] = array(
                'error_code' => ($error_code ? $error_code : 0),
                'error_msg'  => $error_msg
            );
        } else {
            $this->output_error['error'] = array(
                'error_code' => $error_code,
                'error_msg'  => constant('LANG_API_ERROR'.$error_code)
            );
        }

        // если уже есть результат, очищаем его
        $this->output_success = array();

        return $this;

    }

    /**
     * Возвращает результирующий массив: либо массив ошибки, либо успешный результат работы
     * @return array
     */
    public function getOutputdata() {
        return ($this->output_success ? $this->output_success : $this->output_error);
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
