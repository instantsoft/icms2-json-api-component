<?php

class actionImagesApiImagesUpload extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $request_params = array(
        'name' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('sysname'),
                array('max_length', 40)
            )
        ),
        'presets' => array(
            'default' => '',
            'rules'   => array(
                array('regexp', '/^([a-z0-9\_,]+)$/i')
            )
        ),
    );

    public function validateApiRequest() {

        $name = $this->request->get('name');

        // устанавливаем разрешенные типы изображений
        $this->cms_uploader->setAllowedMime($this->allowed_mime);

        cmsEventsManager::hook('images_before_upload', array($name, $this->cms_uploader), null, $this->request);

        // Непосредственно загружаем
        $result = $this->cms_uploader->upload($name);

        // Начинаем работу с изображением
        if ($result['success']){

            try {
                $image = new cmsImages($result['path']);
            } catch (Exception $exc) {
                $result['success'] = false;
                $result['error']   = LANG_UPLOAD_ERR_MIME;
            }

        }

        // Не получилось, удаляем исходник, показываем ошибку
        if (!$result['success']){
            if(!empty($result['path'])){
                files_delete_file($result['path'], 2);
            }
            return array(
                'error_msg' => $result['error']
            );
        }

        // Переданные пресеты
		$sizes = $this->request->get('presets');

		if (!empty($sizes)){
			$sizes = explode(',', $sizes);
		} else {
            $sizes = array_keys((array)$this->model->getPresetsList());
            $sizes[] = 'original';
        }

        // Результирующий массив изображений после конвертации
        $result['paths'] = [];

        // Дополняем оригиналом, если нужно
		if (in_array('original', $sizes, true)){
			$result['paths']['original'] = array(
				'path' => $result['url'],
                'url'  => $this->cms_config->upload_host_abs . '/' . $result['url']
            );
		}

        // Получаем пресеты
		$presets = $this->model->orderByList(array(
            ['by' => 'is_square', 'to' => 'asc'],
            ['by' => 'width', 'to' => 'desc']
        ))->getPresets();

        list($result, $presets, $sizes) = cmsEventsManager::hook('images_after_upload', array($result, $presets, $sizes), null, $this->request);

        // Создаём изображения по пресетам
		foreach($presets as $p){

			if (!in_array($p['name'], $sizes, true)){
				continue;
			}

            $resized_path = $image->resizeByPreset($p);

            if (!$resized_path) { continue; }

            $result['paths'][$p['name']] = [
				'path' => $resized_path,
                'url'  => $this->cms_config->upload_host_abs . '/' . $resized_path
            ];

		}

        list($result, $presets, $sizes) = cmsEventsManager::hook('images_after_resize', array($result, $presets, $sizes), null, $this->request);

		if (!in_array('original', $sizes, true)){
			files_delete_file($result['path'], 2);
		}

        unset($result['path']);

        if(!$result['paths']){
            return array(
                'error_msg' => LANG_UPLOAD_ERR_NO_FILE
            );
        }

        $this->result['items'] = $result['paths'];
        $this->result['host']  = $this->cms_config->upload_host_abs . '/';
        $this->result['count'] = count($result['paths']);

        return false;

    }

    public function run(){}

}
