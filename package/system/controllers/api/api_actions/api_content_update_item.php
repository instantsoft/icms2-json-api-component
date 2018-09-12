<?php

class actionContentApiContentUpdateItem extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $check_sig = true;

    public $request_params = array(
        'id' => array(
            'default' => 0,
            'rules'   => array(
                array('required'),
                array('digits')
            )
        )
    );

    private $ctype, $item, $is_owner, $is_premoderation, $is_moderator, $is_date_pub_ext_allowed, $fields;

    public function validateApiRequest($ctype_name = null) {

        if(!$ctype_name){
            return array('error_msg' => LANG_API_EMPTY_CTYPE);
        }

        $this->ctype = $this->model->getContentTypeByName($ctype_name);

        if(!$this->ctype){
            return array('error_code' => 322);
        }

        $this->item = $this->model->getContentItem($this->ctype['name'], $this->request->get('id'));
        if (!$this->item) { return array('error_code' => 778); }

        $this->item['ctype_id']   = $this->ctype['id'];
        $this->item['ctype_name'] = $this->ctype['name'];
        $this->item['ctype_data'] = $this->ctype;

        // автор записи?
        $this->is_owner = $this->item['user_id'] == $this->cms_user->id;

        // проверяем наличие доступа
        if (!cmsUser::isAllowed($this->ctype['name'], 'edit')) { return array('error_code' => 15); }
        if (!cmsUser::isAllowed($this->ctype['name'], 'edit', 'all') && !cmsUser::isAllowed($this->ctype['name'], 'edit', 'premod_all')) {
            if (
                (cmsUser::isAllowed($this->ctype['name'], 'edit', 'own') ||
                    cmsUser::isAllowed($this->ctype['name'], 'edit', 'premod_own')
                ) && !$this->is_owner) {
                return array('error_code' => 15);
            }
        }

        // модерация
        $this->is_premoderation = false;
        if(cmsUser::isAllowed($this->ctype['name'], 'edit', 'premod_own', true) || cmsUser::isAllowed($this->ctype['name'], 'edit', 'premod_all', true)){
            $this->is_premoderation = true;
        }
        if (!$this->is_premoderation && !$this->item['date_approved']) {
            $this->is_premoderation = cmsUser::isAllowed($this->ctype['name'], 'add', 'premod', true);
        }
        $this->is_moderator = $this->cms_user->is_admin || cmsCore::getModel('moderation')->userIsContentModerator($this->ctype['name'], $this->cms_user->id);

        if (!$this->item['is_approved'] && !$this->is_moderator && !$this->item['is_draft']) { return array('error_code' => 15); }

        if ($this->item['is_deleted']){

            $allow_restore = (cmsUser::isAllowed($this->ctype['name'], 'restore', 'all') ||
                (cmsUser::isAllowed($this->ctype['name'], 'restore', 'own') && $this->is_owner));

            if (!$this->is_moderator && !$allow_restore){ return array('error_code' => 15); }
        }


        // Определяем наличие полей-свойств
        $props = $this->model->getContentProps($this->ctype['name']);
        $this->ctype['props'] = $props;

        // Если включены личные папки - получаем их список
        $folders_list = array();

        if ($this->ctype['is_folders']){
            $folders_list = $this->model->getContentFolders($this->ctype['id'], $this->item['user_id']);
            $folders_list = array_collection_to_list($folders_list, 'id', 'title');
        }

        // Получаем поля для данного типа контента
        $this->fields = $this->model->orderBy('ordering')->getContentFields($this->ctype['name'], $this->item['id']);

        // Если этот контент можно создавать в группах (сообществах) то получаем список групп
        $groups_list = array();

        if ($this->ctype['is_in_groups'] || $this->ctype['is_in_groups_only']){

            $groups_model = cmsCore::getModel('groups');
            $groups = $groups_model->getUserGroups($this->cms_user->id);

            if ($groups){
                $groups_list = ($this->ctype['is_in_groups_only']) ? array() : array('0'=>'');
                $groups_list = $groups_list + array_collection_to_list($groups, 'id', 'title');
            }

        }

        // Строим форму
        $form = $this->getItemForm($this->ctype, $this->fields, 'edit', array(
            'groups_list' => $groups_list,
            'folders_list' => $folders_list
        ), $this->item['id'], $this->item);

		list($this->ctype, $this->item) = cmsEventsManager::hook('content_edit', array($this->ctype, $this->item));
        list($form, $this->item)  = cmsEventsManager::hook("content_{$this->ctype['name']}_form", array($form, $this->item));

        if ($this->ctype['props']){

            $category_id = (($this->request->has('category_id') && $this->ctype['options']['is_cats_change']) ?
                    $this->request->get('category_id', 0) :
                    $this->item['category_id']);

            $item_props = $this->model->getContentProps($this->ctype['name'], $category_id);
            $item_props_fields = $this->getPropsFields($item_props);
            $this->item['props'] = $this->model->getPropsValues($this->ctype['name'], $this->item['id']);
            foreach($item_props_fields as $field){
                $form->addField('props', $field);
            }
        }

		$is_date_pub_days_allowed = $this->ctype['is_date_range'] && cmsUser::isAllowed($this->ctype['name'], 'pub_long', 'days');
		$this->is_date_pub_ext_allowed = $is_date_pub_days_allowed && cmsUser::isAllowed($this->ctype['name'], 'pub_max_ext');

		if ($this->is_date_pub_ext_allowed){
			$this->item['pub_days'] = 0;
		}

		$add_cats = $this->model->getContentItemCategories($this->ctype['name'], $this->item['id']);

		if ($add_cats){
			foreach($add_cats as $index => $cat_id){
				if ($cat_id == $this->item['category_id']) { unset($add_cats[$index]); break; }
			}
		}

        // Парсим форму и получаем поля записи
        $this->item = array_merge($this->item, $form->parse($this->request, true, $this->item));

        // Проверям правильность заполнения
        $errors = $form->validate($this,  $this->item);

        if (!$errors){
            list($this->item, $errors) = cmsEventsManager::hook('content_validate', array($this->item, $errors));
        }

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

		$is_pub_control = cmsUser::isAllowed($this->ctype['name'], 'pub_on');
		$is_date_pub_allowed = $this->ctype['is_date_range'] && cmsUser::isAllowed($this->ctype['name'], 'pub_late');
		$is_date_pub_end_allowed = $this->ctype['is_date_range'] && cmsUser::isAllowed($this->ctype['name'], 'pub_long', 'any');

        $succes_text = '';

        // форма отправлена к контексте черновика
        $is_draf_submitted = $this->request->has('to_draft');

        if($is_draf_submitted){

            $this->item['is_approved'] = 0;

        } else {

            if($this->item['is_draft']){
                $this->item['is_approved'] = !$this->is_premoderation || $this->is_moderator;
            } else {
                $this->item['is_approved'] = $this->item['is_approved'] && (!$this->is_premoderation || $this->is_moderator);
            }

        }

        if($is_draf_submitted || !$this->item['is_approved']){
            unset($this->item['date_approved']);
        }

        if($this->is_owner){
            $this->item['approved_by'] = null;
        }

        $date_pub_time = strtotime($this->item['date_pub']);
        $date_pub_end_time = strtotime($this->item['date_pub_end']);
        $now_time = time();
        $now_date = strtotime(date('Y-m-d', $now_time));
        $is_pub = true;

        if ($is_date_pub_allowed){
            $time_to_pub = $date_pub_time - $now_time;
            $is_pub = $is_pub && ($time_to_pub < 0);
        }
        if ($is_date_pub_end_allowed && !empty($this->item['date_pub_end'])){
            $days_from_pub = floor(($now_date - $date_pub_end_time)/60/60/24);
            $is_pub = $is_pub && ($days_from_pub < 1);
        } else if ($this->is_date_pub_ext_allowed && !$this->cms_user->is_admin) {
            $days = $this->item['pub_days'];
            $date_pub_end_time = (($date_pub_end_time - $now_time) > 0 ? $date_pub_end_time : $now_time) + 60*60*24*$days;
            $days_from_pub = floor(($now_date - $date_pub_end_time)/60/60/24);
            $is_pub = $is_pub && ($days_from_pub < 1);
            $this->item['date_pub_end'] = date('Y-m-d', $date_pub_end_time);
        }

        unset($this->item['pub_days']);

        if (!$is_pub_control) { unset($this->item['is_pub']); }
        if (!isset($this->item['is_pub']) || !empty($this->item['is_pub'])){
            $this->item['is_pub'] = $is_pub;
            if (!$is_pub){
                $succes_text = LANG_CONTENT_IS_PUB_OFF;
            }
        }

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

        $this->item = cmsEventsManager::hook('content_before_update', $this->item);
        $this->item = cmsEventsManager::hook("content_{$this->ctype['name']}_before_update", $this->item);

        // SEO параметры
        $item_seo = $this->prepareItemSeo($this->item, $this->fields, $this->ctype);
        if(empty($this->ctype['options']['is_manual_title']) && !empty($this->ctype['options']['seo_title_pattern'])){
            $this->item['seo_title'] = string_replace_keys_values_extended($this->ctype['options']['seo_title_pattern'], $item_seo);
        } else {
            $this->item['seo_title'] = empty($this->ctype['options']['is_manual_title']) ? null : $this->item['seo_title'];
        }
        if ($this->ctype['is_auto_keys']){
            if(!empty($this->ctype['options']['seo_keys_pattern'])){
                $this->item['seo_keys'] = string_replace_keys_values_extended($this->ctype['options']['seo_keys_pattern'], $item_seo);
            } else {
                $this->item['seo_keys'] = string_get_meta_keywords($this->item['content']);
            }
        }
        if ($this->ctype['is_auto_desc']){
            if(!empty($this->ctype['options']['seo_desc_pattern'])){
                $this->item['seo_desc'] = string_get_meta_description(string_replace_keys_values_extended($this->ctype['options']['seo_desc_pattern'], $item_seo));
            } else {
                $this->item['seo_desc'] = string_get_meta_description($this->item['content']);
            }
        }

        $this->item = $this->model->updateContentItem($this->ctype, $this->item['id'], $this->item, $this->fields);

        $this->bindItemToParents($this->ctype, $this->item);

        cmsEventsManager::hook('content_after_update', $this->item);
        cmsEventsManager::hook("content_{$this->ctype['name']}_after_update", $this->item);

        if(!$is_draf_submitted){

            if ($this->item['is_approved'] || $this->is_moderator){

                // новая запись, например из черновика
                if(empty($this->item['date_approved'])){
                    cmsEventsManager::hook('content_after_add_approve', array('ctype_name' => $this->ctype['name'], 'item' => $this->item));
                    cmsEventsManager::hook("content_{$this->ctype['name']}_after_add_approve", $this->item);
                }

                cmsEventsManager::hook('content_after_update_approve', array('ctype_name'=>$this->ctype['name'], 'item'=>$this->item));
                cmsEventsManager::hook("content_{$this->ctype['name']}_after_update_approve", $this->item);

            } else {

                $this->item['page_url'] = href_to_abs($this->ctype['name'], $this->item['slug'] . '.html');

                $succes_text = cmsCore::getController('moderation')->requestModeration($this->ctype['name'], $this->item, empty($this->item['date_approved']));

            }

        }

        $this->result = array(
            'success_text' => $succes_text,
            'item_id'      => $this->item['id']
        );

    }

}
