<?php

class actionWidgetsApiWidgetsGetWidgets extends cmsAction {

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
     * Возможные параметры запроса
     * с правилами валидации
     * Если запрос имеет параметры, необходимо описать их здесь
     * Правила валидации параметров задаются по аналогии с полями форм
     * @var array
     */
    public $request_params = array(
        'pages_ids' => array(
            'default' => '',
            'rules'   => array(
                array('regexp', '/^([0-9,]+)$/i')
            )
        )
    );

    private $matched_pages = 0;

    public function validateApiRequest() {

        $pages_ids = $this->request->get('pages_ids');

        if($pages_ids){

            $pages_ids = explode(',', $pages_ids);
            $pages_ids = array_filter($pages_ids);

            if($pages_ids){

                $this->matched_pages = $pages_ids;

            }

        }

        return false;

    }

    public function run(){

        $widgets_list = $this->model->getWidgetsForPages($this->matched_pages, $this->cms_template->getName());

        if (!is_array($widgets_list)){ return; }

        $device_type = cmsRequest::getDeviceType();

        $result_widgets = array();

        foreach ($widgets_list as $widget){

            // не выводим виджеты контроллеров, которые отключены
            if(!empty($widget['controller']) && !cmsController::enabled($widget['controller'])){
                continue;
            }

            // проверяем доступ для виджетов
            if (!$this->cms_user->isInGroups($widget['groups_view'])) { continue; }
            if (!empty($widget['groups_hide']) && $this->cms_user->isInGroups($widget['groups_hide']) && !$this->cms_user->is_admin) {
                continue;
            }

            // проверяем для каких устройств показывать
            if($widget['device_types'] && !in_array($device_type, $widget['device_types'])){
                continue;
            }

            $result = false;

            $file = 'system/'.cmsCore::getWidgetPath($widget['name'], $widget['controller']).'/widget.php';

            $class = 'widget' .
                        ($widget['controller'] ? string_to_camel('_', $widget['controller']) : '') .
                        string_to_camel('_', $widget['name']);

            if (!class_exists($class, false)) {
                cmsCore::includeFile($file);
                cmsCore::loadWidgetLanguage($widget['name'], $widget['controller']);
            }

            $widget_object = new $class($widget);

            $cache_key = 'widgets_api'.$widget['id'];
            $cache = cmsCache::getInstance();

            if($widget_object->isCacheable()){
                $result = $cache->get($cache_key);
            }

            if ($result === false){
                $result = call_user_func_array(array($widget_object, 'run'), array());
                if ($result !== false){
                    // Отдельно кешируем имя шаблона виджета, заголовок и враппер, поскольку они могли быть
                    // изменены внутри виджета, а в кеш у нас попадает только тот массив
                    // который возвращается кодом виджета (без самих свойств $widget_object)
                    $result['_wd_title'] = $widget_object->title;
                }
                if($widget_object->isCacheable()){
                    $cache->set($cache_key, $result);
                }
            }

            if ($result === false) { continue; }

            if (isset($result['_wd_title'])) { $widget_object->title = $result['_wd_title']; }

            $result_widgets[] = array(
                'widget_data' => $result,
                'widget'      => $widget
            );

        }

        $this->result['count'] = count($result_widgets);
        $this->result['items'] = $result_widgets;

    }

}
