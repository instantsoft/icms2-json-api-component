<?php

class actionContentApiContentAddItem extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $check_sig = true;

    private $ctype, $item, $fields, $parents;

    public function validateApiRequest($ctype_name = null) {

        if(!$ctype_name){
            return array('error_msg' => LANG_API_EMPTY_CTYPE);
        }

        $this->ctype = $this->model->getContentTypeByName($ctype_name);

        if(!$this->ctype){
            return array('error_code' => 322);
        }

        $permissions = cmsEventsManager::hook('content_add_permissions', array(
            'can_add' => false,
            'ctype'   => $this->ctype
        ));

        $is_check_parent_perm = false;

        // проверяем наличие доступа
        if (!cmsUser::isAllowed($this->ctype['name'], 'add') && !$permissions['can_add']) {
            if (!cmsUser::isAllowed($this->ctype['name'], 'add_to_parent')) {
                if(!$this->cms_user->is_logged){
                    return array('error_code' => 71);
                }
                return array('error_code' => 322);
            }
            $is_check_parent_perm = true;
        }

        // проверяем что не превышен лимит на число записей
        $user_items_count = $this->model->getUserContentItemsCount($this->ctype['name'], $this->cms_user->id, false);

        if (cmsUser::isPermittedLimitReached($this->ctype['name'], 'limit', $user_items_count)){
            return array('error_msg' => sprintf(LANG_CONTENT_COUNT_LIMIT, $this->ctype['labels']['many']));
        }

        // Проверяем ограничение по карме
        if (cmsUser::isPermittedLimitHigher($this->ctype['name'], 'karma', $this->cms_user->karma)){
            return array('error_msg' => sprintf(LANG_CONTENT_KARMA_LIMIT, cmsUser::getPermissionValue($this->ctype['name'], 'karma')));
        }

		$this->item = array();

        // Определяем наличие полей-свойств
        $props = $this->model->getContentProps($this->ctype['name']);
        $this->ctype['props'] = $props;

        // Если этот контент можно создавать в группах (сообществах) то получаем список групп
        $groups_list = array();

        if ($this->ctype['is_in_groups'] || $this->ctype['is_in_groups_only']){

            $groups = cmsCore::getModel('groups')->getUserGroups($this->cms_user->id);

            $groups_list = ($this->ctype['is_in_groups_only']) ? array() : array('0'=>'');
            $groups_list = $groups_list + array_collection_to_list($groups, 'id', 'title');

            $group_id = $this->request->get('group_id', 0);
            // если вне групп добавление записей запрещено, даём выбор только одной группы
            if(!cmsUser::isAllowed($this->ctype['name'], 'add') && isset($groups_list[$group_id])){
                $groups_list = array($group_id => $groups_list[$group_id]);
            }

        }

        // Если включены личные папки - получаем их список
        $folders_list = array();

        if ($this->ctype['is_folders']){
            $folders_list = $this->model->getContentFolders($this->ctype['id'], $this->cms_user->id);
            $folders_list = array_collection_to_list($folders_list, 'id', 'title');
        }

        // Получаем поля для данного типа контента
        $this->fields = $this->model->orderBy('ordering')->getContentFields($this->ctype['name']);

        $form = $this->getItemForm($this->ctype, $this->fields, 'add', array(
            'groups_list' => $groups_list,
            'folders_list' => $folders_list
        ));

        $this->parents = $this->model->getContentTypeParents($this->ctype['id']);

        if ($this->parents){
            foreach($this->parents as $parent){

                if (!$this->request->has($parent['id_param_name'])){
                    continue;
                }

                if (!cmsUser::isAllowed($this->ctype['name'], 'add_to_parent') && !cmsUser::isAllowed($this->ctype['name'], 'bind_to_parent')) {
                    $form->hideField($parent['id_param_name']);
                    continue;
                }

                $parent_id = $this->request->get($parent['id_param_name'], 0);
                $parent_item = $parent_id ? $this->model->getContentItem($parent['ctype_name'], $parent_id) : false;

                if($parent_item){

                    if (!empty($is_check_parent_perm) && !$this->cms_user->is_admin){
                        if (cmsUser::isAllowed($this->ctype['name'], 'add_to_parent', 'to_own') && $parent_item['user_id'] != $this->cms_user->id){
                            return array('error_code' => 322);
                        }
                        if (cmsUser::isAllowed($this->ctype['name'], 'add_to_parent', 'to_other') && $parent_item['user_id'] == $this->cms_user->id){
                            return array('error_code' => 322);
                        }
                    }

                    $this->item[$parent['id_param_name']] = $parent_id;
                    $relation_id = $parent['id'];

                }

                break;

            }
        }

        if (!empty($is_check_parent_perm) && empty($relation_id)){
            return array('error_code' => 322);
        }

        // Заполняем поля значениями по-умолчанию, взятыми из профиля пользователя
        // (для тех полей, в которых это включено)
        foreach($this->fields as $field){
            if (!empty($field['options']['profile_value'])){
                $this->item[$field['name']] = $this->cms_user->{$field['options']['profile_value']};
            }
            if (!empty($field['options']['relation_id']) && !empty($relation_id)){
                if ($field['options']['relation_id'] != $relation_id){
                    $form->hideField($field['name']);
                }
            }
        }

		$this->ctype = cmsEventsManager::hook('content_add', $this->ctype);
        list($form, $this->item) = cmsEventsManager::hook("content_{$this->ctype['name']}_form", array($form, $this->item));

        $this->item['ctype_name'] = $this->ctype['name'];
		$this->item['ctype_id']   = $this->ctype['id'];
        $this->item['ctype_data'] = $this->ctype;

        if ($this->ctype['props']){
            $props_cat_id = $this->request->get('category_id', 0);
            if ($props_cat_id){
                $item_props = $this->model->getContentProps($this->ctype['name'], $props_cat_id);
                $item_props_fields = $this->getPropsFields($item_props);
                foreach($item_props_fields as $field){
                    $form->addField('props', $field);
                }
            }
        }

        // Парсим форму и получаем поля записи
        $this->item = array_merge($this->item, $form->parse($this->request, true));

        // Проверям правильность заполнения
        $errors = $form->validate($this,  $this->item);

        if ($this->parents && $is_check_parent_perm){

            $perm = cmsUser::getPermissionValue($this->ctype['name'], 'add_to_parent');

            foreach($this->parents as $parent){
                if (!empty($this->item[$parent['id_param_name']])){
                    $ids = explode(',', $this->item[$parent['id_param_name']]);
                    $this->model->filterIn('id', $ids);
                    $parent_items = $this->model->getContentItems($parent['ctype_name']);
                    if ($parent_items){
                        foreach($parent_items as $parent_item){
                            if ($perm == 'to_own' && $parent_item['user']['id'] != $this->cms_user->id) {
                                $errors[$parent['id_param_name']] = LANG_CONTENT_WRONG_PARENT;
                                break;
                            }
                            if ($perm == 'to_other' && $parent_item['user']['id'] == $this->cms_user->id) {
                                $errors[$parent['id_param_name']] = LANG_CONTENT_WRONG_PARENT;
                                break;
                            }
                        }
                    }
                }
            }

        }

        list($this->item, $errors) = cmsEventsManager::hook('content_validate', array($this->item, $errors));
        list($this->item, $errors, $this->ctype, $this->fields) = cmsEventsManager::hook("content_{$ctype['name']}_validate", array($this->item, $errors, $this->ctype, $this->fields), null, $this->request);

        if($errors){

            if($errors === true){
                $errors = array('csrf_token' => LANG_API_ERROR_CSRF_TOKEN);
            }

            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => $errors
            );

        }

        return false;

    }

    public function run($ctype_name){

        $succes_text = '';

        // форма отправлена к контексте черновика
        $is_draf_submitted = $this->request->has('to_draft');

        // несколько категорий
        if (!empty($this->ctype['options']['is_cats_multi'])){
            $add_cats = $this->request->get('add_cats', array());
            if (is_array($add_cats)){
                foreach($add_cats as $index=>$cat_id){
                    if (!is_numeric($cat_id) || !$cat_id){
                        unset($add_cats[$index]);
                    }
                }
                if ($add_cats){
                    $this->item['add_cats'] = $add_cats;
                }
            }
        }

        $is_moderator = $this->cms_user->is_admin || cmsCore::getModel('moderation')->userIsContentModerator($this->ctype['name'], $this->cms_user->id);
        $is_premoderation = cmsUser::isAllowed($this->ctype['name'], 'add', 'premod', true);

        if($is_draf_submitted){
            $this->item['is_approved'] = 0;
        } else {
            $this->item['is_approved'] = !$is_premoderation || $is_moderator;
        }

        $is_pub_control = cmsUser::isAllowed($this->ctype['name'], 'pub_on');
        $is_date_pub_allowed = $this->ctype['is_date_range'] && cmsUser::isAllowed($this->ctype['name'], 'pub_late');
        $is_date_pub_end_allowed = $this->ctype['is_date_range'] && cmsUser::isAllowed($this->ctype['name'], 'pub_long', 'any');
        $is_date_pub_days_allowed = $this->ctype['is_date_range'] && cmsUser::isAllowed($this->ctype['name'], 'pub_long', 'days');

        $date_pub_time = isset($this->item['date_pub']) ? strtotime($this->item['date_pub']) : time();
        $now_time = time();
        $now_date = strtotime(date('Y-m-d', $now_time));
        $is_pub = true;

        if ($is_date_pub_allowed){
            $time_to_pub = $date_pub_time - $now_time;
            $is_pub = $is_pub && ($time_to_pub < 0);
        }
        if ($is_date_pub_end_allowed && !empty($this->item['date_pub_end'])){
            $date_pub_end_time = strtotime($this->item['date_pub_end']);
            $days_from_pub = floor(($now_date - $date_pub_end_time)/60/60/24);
            $is_pub = $is_pub && ($days_from_pub < 1);
        } else if ($is_date_pub_days_allowed && !$this->cms_user->is_admin) {
            $days = $this->item['pub_days'];
            $date_pub_end_time = $date_pub_time + 60*60*24*$days;
            $days_from_pub = floor(($now_date - $date_pub_end_time)/60/60/24);
            $is_pub = $is_pub && ($days_from_pub < 1);
            $this->item['date_pub_end'] = date('Y-m-d', $date_pub_end_time);
        } else {
            $this->item['date_pub_end'] = false;
        }

        unset($this->item['pub_days']);
        if (!$is_pub_control) { unset($this->item['is_pub']); }
        if (!isset($this->item['is_pub'])) { $this->item['is_pub'] = $is_pub; }
        if (!empty($this->item['is_pub'])) { $this->item['is_pub'] = $is_pub; }

        $this->item = cmsEventsManager::hook('content_before_add', $this->item);
        $this->item = cmsEventsManager::hook("content_{$this->ctype['name']}_before_add", $this->item);

        $this->item = $this->model->addContentItem($this->ctype, $this->item, $this->fields);

        $this->bindItemToParents($this->ctype, $this->item, $this->parents);

        $this->item = cmsEventsManager::hook('content_after_add', $this->item);
        $this->item = cmsEventsManager::hook("content_{$this->ctype['name']}_after_add", $this->item);

        if(!$is_draf_submitted){

            if ($this->item['is_approved']){
                cmsEventsManager::hook('content_after_add_approve', array('ctype_name' => $this->ctype['name'], 'item' => $this->item));
                cmsEventsManager::hook("content_{$this->ctype['name']}_after_add_approve", $this->item);
            } else {

                $this->item['page_url'] = href_to_abs($this->ctype['name'], $this->item['slug'] . '.html');

                $succes_text = cmsCore::getController('moderation')->requestModeration($this->ctype['name'], $this->item);

            }

        }

        $this->result = array(
            'success_text' => $succes_text,
            'item_id'      => $this->item['id']
        );

    }

}
