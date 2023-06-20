<?php

class actionContentApiContentGet extends cmsAction {

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
        'page' => array(
            'default' => 1,
            'rules'   => array(
                array('digits')
            )
        ),
        'is_not_paginated' => array(
            'default' => 0,
            'rules'   => array(
                array('digits')
            )
        ),
        'cat_id' => array(
            'default' => 1,
            'rules'   => array(
                array('digits')
            )
        ),
        'folder_id' => array(
            'default' => 0,
            'rules'   => array(
                array('digits')
            )
        ),
        'user_id' => array(
            'default' => 0,
            'rules'   => array(
                array('digits')
            )
        ),
        'group_id' => array(
            'default' => 0,
            'rules'   => array(
                array('digits')
            )
        ),
        'dataset_id' => array(
            'default' => 0,
            'rules'   => array(
                array('digits')
            )
        ),
        'ids' => array(
            'default' => '',
            'rules'   => array(
                array('regexp', '/^([0-9,]+)$/i')
            )
        )
    );

    private $ctype;
    private $cat = array('id' => false);
    private $dataset, $user, $folder, $group = array();

    public function validateApiRequest($ctype_name=null) {

        if(!$ctype_name){
            return array('error_msg' => LANG_API_EMPTY_CTYPE);
        }

        $this->ctype = $this->model->getContentTypeByName($ctype_name);

        if(!$this->ctype){
            return array('error_msg' => LANG_API_EMPTY_CTYPE);
        }

        // категория
        $cat_id = $this->request->get('cat_id');
        if(!empty($this->ctype['is_cats']) && $cat_id > 1){

            $this->cat = $this->model->getCategory($this->ctype['name'], $cat_id);
            if (!$this->cat){
                return array('error_msg' => LANG_API_ERROR100);
            }

        }

        // набор
        $dataset_id = $this->request->get('dataset_id');
        if($dataset_id){

            $this->dataset = $this->model->getContentDataset($dataset_id);
            if (!$this->dataset){
                return array('error_msg' => LANG_API_ERROR100);
            }

        }

        // пользователь
        $user_id = $this->request->get('user_id');
        if($user_id){

            $this->user = $this->model->getItemById('{users}', $user_id);
            if (!$this->user){
                return array('error_msg' => LANG_API_ERROR100);
            }

        }

        // папка
        $folder_id = $this->request->get('folder_id');
        if($folder_id){

            $this->folder = $this->model->$this->getItemById('content_folders', $folder_id);
            if (!$this->folder){
                return array('error_msg' => LANG_API_ERROR100);
            }

        }

        // группа
        $group_id = $this->request->get('group_id');
        if($group_id){

            $this->group = $this->model->getItemById('groups', $group_id);
            if (!$this->group){
                return array('error_msg' => LANG_API_ERROR100);
            }

        }

        return false;

    }

    public function run($ctype_name){

        // просмотр списка запрещен
        if (empty($this->ctype['options']['list_on'])) { return; }

        // параметры
        $perpage   = (empty($this->ctype['options']['limit']) ? 10 : $this->ctype['options']['limit']);
        $page      = $this->request->get('page');
        $hide_root = !empty($this->ctype['options']['is_empty_root']) && $this->cat['id'] == 1;

        // разбивка на страницы если нужна
        if(!$this->request->get('is_not_paginated')){
            $this->result['paging'] = array(
                'page'     => $page,
                'per_page' => $perpage
            );
        }

        // если записи в корне мы не показываем
        if($hide_root){ return; }

        // категории выключены, а передали категорию
        if (empty($this->ctype['is_cats']) && $this->cat['id'] > 1) { return; }

        // если нужен список по id
        $ids = $this->request->get('ids');
        if($ids){

            $ids = explode(',', $ids);
            $ids = array_filter($ids);

            if($ids){
                $this->model->filterIn('id', $ids);
            }

        }

        // если передан набор, фильтруем по нему
        if($this->dataset){
            $this->model->applyDatasetFilters($this->dataset);
        }

        // Фильтр по категории
        if ($this->cat['id'] > 1) {
            $this->model->filterCategory($this->ctype['name'], $this->cat, $this->ctype['is_cats_recursive']);
        }

        // фильтр по пользователю
        if($this->user){
            $this->model->filterEqual('user_id', $this->user['id']);
        }

        // фильтр по папке
        if($this->folder){
            $this->model->filterEqual('folder_id', $this->folder['id']);
        }

        // фильтр по группе
        if($this->group){
            $this->model->filterEqual('parent_id', $this->group['id'])->
                filterEqual('parent_type', 'group')->
                orderBy('date_pub', 'desc')->forceIndex('parent_id');
        }

        // Скрываем записи из скрытых родителей (приватных групп и т.п.)
        $this->model->filterHiddenParents();

        // фильтрация по полям и свойствам
        $props = $props_fields = $filters = array();

        $fields = cmsCore::getModel('content')->getContentFields($this->ctype['name']);

        if ($this->cat['id'] && $this->cat['id'] > 1){
            // Получаем поля-свойства
            $props = cmsCore::getModel('content')->getContentProps($this->ctype['name'], $this->cat['id']);
        }

		// проверяем запросы фильтрации по полям
		foreach($fields as $name => $field){

			if (!$field['is_in_filter']) { continue; }
			if (!$this->request->has($name)){ continue; }

			$value = $this->request->get($name, false, $field['handler']->getDefaultVarType(true));
			if (!$value) { continue; }

			if($field['handler']->applyFilter($this->model, $value) !== false){
                $filters[$name] = $value;
            }

		}

		// проверяем запросы фильтрации по свойствам
		if ($props && is_array($props)){

            $props_fields = $this->getPropsFields($props);

			foreach($props as $prop){

				$name = "p{$prop['id']}";

				if (!$prop['is_in_filter']) { continue; }
				if (!$this->request->has($name)){ continue; }

                $prop['handler'] = $props_fields[$prop['id']];

				$value = $this->request->get($name, false, $prop['handler']->getDefaultVarType(true));
				if (!$value) { continue; }

				if($this->model->filterPropValue($this->ctype['name'], $prop, $value) !== false){

                    $filters[$name] = $value;

                }

			}

		}

        // Сначала проверяем настройки типа контента
        if (!empty($this->ctype['options']['privacy_type']) &&
                in_array($this->ctype['options']['privacy_type'], array('show_title', 'show_all'), true)) {
            $this->model->disablePrivacyFilter();
        }

        // А потом, если разрешено правами доступа, отключаем фильтр приватности
        if (cmsUser::isAllowed($this->ctype['name'], 'view_all')) {
            $this->model->disablePrivacyFilter();
        }

        // Постраничный вывод
        if($this->request->get('is_not_paginated')){
            $this->model->limit(0);
        } else {
            $this->model->limitPage($page, $perpage);
        }

		list($this->ctype, $this->model) = cmsEventsManager::hook('content_list_filter', array($this->ctype, $this->model));
		list($this->ctype, $this->model) = cmsEventsManager::hook("content_{$this->ctype['name']}_list_filter", array($this->ctype, $this->model));

        // Получаем количество и список записей
        $total = $this->model->getContentItemsCount($this->ctype['name']);
        $items = $this->model->getContentItems($this->ctype['name']);

        list($this->ctype, $items) = cmsEventsManager::hook('content_before_list', array($this->ctype, $items));
        list($this->ctype, $items) = cmsEventsManager::hook("content_{$this->ctype['name']}_before_list", array($this->ctype, $items));
        list($this->ctype, $items) = cmsEventsManager::hook('content_api_list', array($this->ctype, $items));

        $result_items = array();

        if($items){
            foreach ($items as $key => $item) {

                $is_private = $item['is_private'] == 1 && !$item['user']['is_friend'];

                $item['ctype'] = $this->ctype;

                foreach($fields as $name => $field){

                    if (!$field['is_in_list']) { unset($items[$key][$name]); continue; }

                    if (empty($item[$name]) || $field['is_system']) { continue; }

                    if ($field['groups_read'] && !$this->cms_user->isInGroups($field['groups_read'])) {
                        unset($items[$key][$name]); continue;
                    }

                    if($is_private){
                        $items[$key][$name] = null; continue;
                    }

                    if (in_array($field['type'], array('images','image'))){
                        $items[$key][$name] = api_image_src($item[$name]);
                    } else
                    if ($name != 'title'){
                        $items[$key][$name] = $field['handler']->setItem($item)->parseTeaser($item[$name]);
                    }

                }

                $result_items[] = $items[$key];

            }
        }

        foreach($fields as $name => $field){
            unset($fields[$name]['handler']);
        }

        $this->result['count']    = $total;
        $this->result['items']    = $result_items;
        $this->result['additionally'] = array(
            'fields'       => $fields,
            'props'        => $props,
            'filters'      => $filters,
            'ctype'        => $this->ctype,
            'category'     => $this->cat,
            'dataset'      => $this->dataset,
            'group'        => $this->group
        );

    }

}
