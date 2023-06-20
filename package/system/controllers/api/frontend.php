<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		                  http://instantmedia.ru/                         //
//                                written by Fuze                             //
//                                                                            //
/******************************************************************************/

class api extends cmsFrontend {

    protected $useOptions = true;

    private $output_success = [];
    private $output_error   = [];

    public $key         = null;
    public $method_name = null;

    public $start_time = null;

    public function __construct($request) {

        $this->start_time = microtime(true);

        parent::__construct($request);

        // устанавливаем ошибку по-умолчанию
        $this->setError(1);
    }

    public function loadApiKey() {

        if ($this->key !== null) {
            return $this;
        }

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

        if (empty($this->key)) {
            return $this->error(101);
        }

        if ($this->key['ip_access'] && !string_in_mask_list(cmsUser::getIp(), $this->key['ip_access'])) {
            return $this->error(15);
        }

        if (!$this->key['is_pub']) {
            return $this->error(2);
        }

        return true;
    }

    public function actionIndex() {
        return cmsCore::errorForbidden();
    }

    /**
     * Универсальный метод, который позволяет запускать последовательность других методов,
     * сохраняя и фильтруя промежуточные результаты
     */
    public function actionExecute() {

        $this->loadApiKey();

        // если передан ip адрес, считаем его адресом посетителя
        // для различных проверок компонентов
        // т.к. движок определяет ip адрес места запроса
        if ($this->request->has('ip')) {

            $ip = $this->request->get('ip', '');

            if (!$ip || filter_var($ip, FILTER_VALIDATE_IP) !== $ip) {
                return $this->error(777);
            }

            // совместимость
            if (method_exists('cmsUser', 'setIp')) {
                cmsUser::setIp($ip);
            }
        }

        if (!$this->checkRequest()) {
            return false;
        }

        $code = $this->request->get('code', '');
        if (!$code) {
            return $this->error(100);
        }

        $methods = json_decode($code, true);
        if (json_last_error() !== JSON_ERROR_NONE || !$methods) {
            return $this->error(12);
        }

        $response         = [];
        $max_method_count = 10;

        if (count($methods) > $max_method_count) {
            return $this->error(13);
        }

        foreach ($methods as $method_param) {

            if (empty($method_param['method'])) {
                return $this->error(13);
            }

            $this->request->setData(!empty($method_param['params']) ? $method_param['params'] : []);

            $method_result = $this->runExternalAction('method', [$method_param['method']]);

            if (!$method_result) {
                return $this->error(13);
            }

            $response[!empty($method_param['key']) ? $method_param['key'] : $method_param['method']] = $this->output_success['response'];
        }

        $this->setSuccess($response);

    }

    /**
     * Дополняем метод окончания вызова экшена
     * @param string $action_name
     * @return boolean
     */
    public function after($action_name) {

        parent::after($action_name);

        if (!$this->cms_user->is_logged && $this->output_success) {

            $this->output_success['session'] = [
                'session_name' => session_name(),
                'session_id'   => session_id()
            ];
        }

        $this->renderJSON();

        return true;
    }

    /**
     * Результат запроса
     * @param array $api_request_result
     */
    public function setSuccess($api_request_result) {

        $success = [
            'response' => $api_request_result
        ];

        if ($this->cms_config->debug && cmsUser::isAdmin()) {

            $success['debug'] = [
                'time' => cmsDebugging::getTime('cms', 4),
                'mem'  => round(memory_get_usage(true) / 1024 / 1024, 2),
                'data' => cmsDebugging::getPointsData()
            ];
        }

        $this->output_success = $success;

    }

    /**
     * Устанавливает ошибку запроса
     * @param integer $error_code
     * @param string $error_msg
     * @param array $request_params
     */
    public function setError($error_code, $error_msg = '', $request_params = []) {

        if ($error_msg) {

            $this->output_error['error'] = [
                'error_code'     => ($error_code ? $error_code : 0),
                'error_msg'      => $error_msg,
                'request_params' => $request_params
            ];

        } else {

            $this->output_error['error'] = [
                'error_code'     => $error_code,
                'error_msg'      => constant('LANG_API_ERROR' . $error_code),
                'request_params' => $request_params
            ];
        }

        // если уже есть результат, очищаем его
        $this->output_success = [];

        return $this;
    }

    /**
     * Фиксирует ошибку запроса
     * @param integer $error_code
     * @param string $error_msg
     * @param array $request_params
     */
    public function error($error_code, $error_msg = '', $request_params = []) {

        // записываем в лог ошибку, если включена их фиксация
        if (!empty($this->options['log_error'])) {

            $this->model->log([
                'request_time' => number_format((microtime(true) - $this->start_time), 4),
                'error'        => $error_code,
                'method'       => $this->method_name,
                'key_id'       => (!empty($this->key['id']) ? $this->key['id'] : null)
            ]);
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
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $key       = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $out[$key] = $value;
            } else {
                $out[$key] = $value;
            }
        }
        return $out;

    }
}

function api_image_src($images, $size_preset = false) {

    $config = cmsConfig::getInstance();

    if (!is_array($images)) {
        $images = cmsModel::yamlToArray($images);
    }

    if (!$images) {
        return null;
    }

    $result = array();

    $keys = array_keys($images);
    // значит массив изображений
    if ($keys[0] === 0) {
        foreach ($images as $image) {
            $result[] = api_image_src($image);
        }
    } else {
        foreach ($images as $preset => $path) {
            $result[$preset] = $config->upload_host_abs . '/' . $path;
        }
    }

    return $result;
}

function form_to_params($form) {

    $params = array('csrf_token' => array(
            'title'  => 'csrf_token',
            'fields' => array(
                array(
                    'title'       => null,
                    'data'        => array(
                        'type' => 'hidden'
                    ),
                    'type'        => 'string',
                    'name'        => 'csrf_token',
                    'rules'       => array(
                        array('required')
                    ),
                    'var_type'    => 'string',
                    'items'       => null,
                    'placeholder' => null,
                    'default'     => cmsForm::getCSRFToken()
                )
            )
    ));

    $structure = $form->getStructure();

    foreach ($structure as $key => $fieldset) {

        if (empty($fieldset['childs'])) {
            continue;
        }

        $param = array(
            'title'  => (!empty($fieldset['title']) ? $fieldset['title'] : null),
            'hint'   => (!empty($fieldset['hint']) ? $fieldset['hint'] : null),
            'fields' => array()
        );

        foreach ($fieldset['childs'] as $field) {

            $param['fields'][$field->getName()] = array(
                'title'      => $field->title,
                'field_type' => isset($field->field_type) ? $field->field_type : $field->class, // совместимость
                'type'       => (!empty($field->type) ? $field->type : null),
                'name'       => $field->getName(),
                'rules'      => $field->getRules(),
                'var_type'   => $field->var_type,
                'items'      => (method_exists($field, 'getListItems') ? $field->getListItems() : null),
                'options'    => (!empty($field->options) ? $field->options : null),
                'attributes' => (!empty($field->attributes) ? $field->attributes : null),
                'hint'       => (!empty($field->hint) ? $field->hint : null),
                'units'      => (!empty($field->units) ? $field->units : null),
                'default'    => (isset($field->default) ? $field->default : null)
            );
        }

        $params[$key] = $param;
    }

    return $params;
}

function get_sig() {
    $ip = cmsUser::getIp();
    return md5($ip . md5(md5(cmsConfig::get('host')) . md5(cmsConfig::get('db_base')) . sprintf('%u', ip2long($ip))));
}

function check_sig($sig) {
    return $sig === get_sig();
}
