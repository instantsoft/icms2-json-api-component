<?php
final class cmsApi {

    /**
     * Время кеширования ответа в секундах
     */
    const cache_time = 300;

    /**
     * Ключ API
     */
    const api_key = 'YOU_KEY';

    /**
     * Паттерн базового url для методов API
     */
    const api_point = 'https://example.com/%s/method/';

    /**
     * Паттерн базового url для метода execute
     */
    const api_point_execute = 'https://example.com/%s/';

    /**
     * Кешированный результат от вызова метода users.get_sig
     * @var string
     */
    private static $signature = null;

    /**
     * Возвращает базовый url для метода execute
     * @return string
     */
    public static function getApiExecutePoint() {
        return sprintf(self::api_point_execute, cmsCore::getLanguageName());
    }

    /**
     * Возвращает базовый url для обычных методов
     * @return string
     */
    public static function getApiPoint() {
        return sprintf(self::api_point, cmsCore::getLanguageName());
    }

    /**
     * Возвращает сигнатуры csrf_token или sig
     * @param string $name csrf_token или sig
     * @return string
     */
    public static function getSignature($name = 'csrf_token') {

        if (self::$signature === null) {

            $response = self::getMethod('users.get_sig');

            self::$signature = $response['response'];

        }

        return isset(self::$signature[$name]) ? self::$signature[$name] : self::$signature;

    }

    /**
     * Запрашивает метод API
     *
     * @param string $name Имя метода
     * @param array $params Параметры методы
     * @param boolean $cacheable Кэшировать ответ
     * @param boolean $is_upload Флаг, что мы загружаем файлы
     * @param string $api_point Базовый url запроса
     * @return array
     */
    public static function getMethod($name, $params = [], $cacheable = false, $is_upload = false, $api_point = false) {

        if (!function_exists('curl_init')) {
            return [
                'error' => [
                    'error_msg' => 'Please, install curl'
                ]
            ];
        }

        if(!$api_point){
            $api_point = self::getApiPoint();
        }

        if ($cacheable) {

            $cache_file = cmsConfig::get('cache_path') . 'api/' . md5($name . serialize($params) . cmsCore::getLanguageName()) . '.dat';

            if (is_readable($cache_file)) {

                $time_diff = (time() - filemtime($cache_file));

                if ($time_diff < self::cache_time) {

                    $result = include $cache_file;

                    if ($result) {
                        return $result;
                    } else {
                        unlink($cache_file);
                    }

                } else {
                    unlink($cache_file);
                }
            }
        }

        $curl = curl_init();

        if (isset($params['cookie'])) {

            $cookie = [];

            foreach ($params['cookie'] as $k => $v) {
                $cookie[] = $k . '=' . $v;
            }

            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Cookie: ' . implode('; ', $cookie)]);

            unset($params['cookie']);

        } elseif (cmsUser::isLogged()) {

            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Cookie: ' . cmsUser::sessionGet('user_session:session_name') . '=' . cmsUser::sessionGet('user_session:session_id')]);

        } elseif (cmsUser::isSessionSet('guest_session:session_id')) {

            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Cookie: ' . cmsUser::sessionGet('guest_session:session_name') . '=' . cmsUser::sessionGet('guest_session:session_id')]);
        }

        $params['api_key'] = self::api_key;
        $params['ip']      = cmsUser::getIp();

        $params_string = !$is_upload ? http_build_query($params) : $params;

        curl_setopt($curl, CURLOPT_URL, $api_point . $name);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params_string);

        $_data = curl_exec($curl);

        if (!$_data) {
            return [
                'error' => [
                    'error_msg' => LANG_API_ERROR
                ]
            ];
        }

        $data = json_decode($_data, true);

        curl_close($curl);

        if ($data === false) {
            return [
                'error' => [
                    'error_msg' => json_last_error_msg()
                ]
            ];
        }

        if (isset($data['session'])) {
            cmsUser::sessionSet('guest_session:session_name', $data['session']['session_name']);
            cmsUser::sessionSet('guest_session:session_id', $data['session']['session_id']);
        }

        if ($cacheable) {
            file_put_contents($cache_file, '<?php return ' . var_export($data, true) . ';');
        }

        return $data;

    }

    /**
     * Выполняет метод execute
     * https://docs.instantcms.ru/manual/components/api/methods/execute
     *
     * @param array $params Параметр code метода в виде массива
     * @param boolean $cacheable Кэшировать ответ
     * @param boolean $is_upload Флаг, что мы загружаем файлы
     * @return array
     */
    public static function getExecute($params, $cacheable = false, $is_upload = false) {
        return self::getMethod('execute', ['code' => json_encode($params)], $cacheable, $is_upload, self::getApiExecutePoint());
    }

    public static function arrayToForm($data) {

        $form = new cmsForm();

        $form->addFieldset('', 'basic');

        foreach ($data as $fsets) {
            foreach ($fsets['fields'] as $field) {

                if($field['name'] == 'csrf_token'){
                    cmsUser::sessionSet('csrf_token', $field['default']);
                    continue;
                }

                $field_class = 'field' . string_to_camel('_',  $field['field_type'] );

                $form->addField('basic',
                    new $field_class($field['name'], $field)
                );

            }
        }

        return $form;

    }

}
