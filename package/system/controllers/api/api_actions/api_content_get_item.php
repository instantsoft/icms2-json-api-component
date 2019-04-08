<?php

class actionContentApiContentGetItem extends cmsAction {

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
        'item_id' => array(
            'default' => 0,
            'rules'   => array(
                array('required'),
                array('digits')
            )
        )
    );

    private $ctype, $item;

    public function validateApiRequest($ctype_name=null) {

        if(!$ctype_name){
            return array('error_msg' => LANG_API_EMPTY_CTYPE);
        }

        $this->ctype = $this->model->getContentTypeByName($ctype_name);

        if(!$this->ctype){
            return array('error_msg' => LANG_API_EMPTY_CTYPE);
        }

        // получаем запись
        $this->item = $this->model->getContentItem($this->ctype['name'], $this->request->get('item_id'));
        if(!$this->item){
            return array('error_msg' => LANG_API_ERROR100);
        }

        // Проверяем прохождение модерации
        $is_moderator = $this->cms_user->is_admin || $this->model->userIsContentTypeModerator($this->ctype['name'], $this->cms_user->id);
        if (!$this->item['is_approved']){
            if (!$is_moderator && $this->cms_user->id != $this->item['user_id']){
                return array(
                    'error_code' => 320
                );
            }
        }

        // Проверяем публикацию
        if (!$this->item['is_pub']){
            if (!$is_moderator && $this->cms_user->id != $this->item['user_id']){
                return array(
                    'error_code' => 321
                );
            }
        }

        // Проверяем, что не удалено
        if (!empty($this->item['is_deleted'])){
            if (!$is_moderator){
                return array('error_msg' => LANG_API_ERROR100);
            }
        }

        // Проверяем приватность
        if ($this->item['is_private'] == 1){ // доступ только друзьям

            $is_friend           = $this->cms_user->isFriend($this->item['user_id']);
            $is_can_view_private = cmsUser::isAllowed($this->ctype['name'], 'view_all');

            if (!$is_friend && !$is_can_view_private && !$is_moderator){
                // если в настройках указано скрывать
                if(empty($this->ctype['options']['privacy_type']) || $this->ctype['options']['privacy_type'] == 'hide'){
                    return array(
                        'error_code' => 15
                    );
                }
                // иначе пишем, к кому в друзья нужно проситься
                return array(
                    'error_code' => 15,
                    'error_msg'  => sprintf(
                        LANG_CONTENT_PRIVATE_FRIEND_INFO,
                        (!empty($this->ctype['labels']['one']) ? $this->ctype['labels']['one'] : LANG_PAGE),
                        href_to('users', $this->item['user_id']),
                        htmlspecialchars($this->item['user']['nickname'])
                    )
                );
            }

        }

        // Проверяем ограничения доступа из других контроллеров
        if ($this->item['is_parent_hidden'] || $this->item['is_private']){
            $is_parent_viewable_result = cmsEventsManager::hook('content_view_hidden', array(
                'viewable'     => true,
                'item'         => $this->item,
                'is_moderator' => $is_moderator,
                'ctype'        => $this->ctype
            ));
            if (!$is_parent_viewable_result['viewable']){
                return array(
                    'error_code' => 15
                );
            }
        }

        return false;

    }

    public function run($ctype_name){

        $category = $props = array();
        $this->result = array();

        // просмотр записей запрещен
        if (empty($this->ctype['options']['item_on'])) { return; }

        $this->item['ctype_name'] = $this->ctype['name'];

        if ($this->ctype['is_cats'] && $this->item['category_id'] > 1){
            $category = $this->model->getCategory($this->ctype['name'], $this->item['category_id']);
        }

        // Получаем поля для данного типа контента
        $fields = $this->model->getContentFields($this->ctype['name']);

        // Получаем поля-свойства
        $this->item['props'] = array();

        if ($this->ctype['is_cats'] && $this->item['category_id'] > 1){
            $props = $this->model->getContentProps($this->ctype['name'], $this->item['category_id']);
            $props_values = $this->model->getPropsValues($this->ctype['name'], $this->item['id']);
            if ($props && array_filter((array)$props_values)) {
                $props_fields = $this->getPropsFields($props);
                $props_fieldsets = cmsForm::mapFieldsToFieldsets($props);
                foreach($props_fieldsets as $fieldset){

                    $props_result = array(
                        'title'  => $fieldset['title'],
                        'fields' => array()
                    );
                    if ($fieldset['fields']){
                        foreach($fieldset['fields'] as $prop){
                            if (isset($props_values[$prop['id']])) {
                                $prop_field = $props_fields[$prop['id']];
                                $props_result['fields'][$prop['id']] = array(
                                    'title' => $prop['title'],
                                    'value' => $prop_field->setItem($this->item)->parse($props_values[$prop['id']])
                                );
                            }
                        }
                    }

                    $this->item['props'][] = $props_result;

                }
            }
        }

        list($this->ctype, $this->item, $fields) = cmsEventsManager::hook('content_before_item', array($this->ctype, $this->item, $fields));
        list($this->ctype, $this->item, $fields) = cmsEventsManager::hook("content_{$this->ctype['name']}_before_item", array($this->ctype, $this->item, $fields));
        list($this->ctype, $this->item, $fields) = cmsEventsManager::hook('api_content_before_item', array($this->ctype, $this->item, $fields));

        // парсим поля
        foreach($fields as $name => $field){

            if (!$field['is_in_item']) { unset($this->item[$name]); continue; }

            if (empty($this->item[$name]) || $field['is_system']) { continue; }

            // проверяем что группа пользователя имеет доступ к чтению этого поля
            if ($field['groups_read'] && !$this->cms_user->isInGroups($field['groups_read'])) {
                // если группа пользователя не имеет доступ к чтению этого поля,
                // проверяем на доступ к нему для авторов
                if (!empty($this->item['user_id']) && !empty($field['options']['author_access'])){

                    if (!in_array('is_read', $field['options']['author_access'])){
                        unset($this->item[$name]); continue;
                    }

                    if ($this->item['user_id'] == $this->cms_user->id){
                        unset($this->item[$name]); continue;
                    }

                }

                unset($this->item[$name]); continue;

            }

            if (in_array($field['type'], array('images','image'))){
                $this->item[$name] = api_image_src($this->item[$name]);
            } else
            if ($name != 'title'){
                $this->item[$name] = $field['handler']->setItem($this->item)->parseTeaser($this->item[$name]);
            }

        }

        // убираем ненужное
        foreach($fields as $name => $field){
            unset($fields[$name]['handler']);
        }

        $this->result['item'] = $this->item;
        $this->result['additionally'] = array(
            'ctype'    => $this->ctype,
            'fields'   => $fields,
            'props'    => $props,
            'category' => $category
        );

    }

}
