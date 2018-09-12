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

        $result = $this->cms_uploader->setAllowedMime($this->allowed_mime)->
                upload($this->request->get('name'), $this->getAllowedExtensions());

        if ($result['success']){
            if (!$this->cms_uploader->isImage($result['path'])){

                files_delete_file($result['path'], 2);

                return array(
                    'error_msg' => LANG_UPLOAD_ERR_MIME
                );

            }
        }

        if (!$result['success']){

            if(!empty($result['path'])){
                files_delete_file($result['path'], 2);
            }

            return array(
                'error_msg' => $result['error']
            );

        }

		$sizes = $this->request->get('presets');
        $file_name = $this->request->get('file_name', '');

		if ($sizes && preg_match('/([a-z0-9_,]+)$/i', $sizes)){
			$sizes = explode(',', $sizes);
		} else {
            $sizes = array_keys((array)$this->model->getPresetsList());
            $sizes[] = 'original';
        }

        $result['paths'] = array();

		if (in_array('original', $sizes, true)){
			$result['paths']['original'] = $result['url'];
		}

		$presets = $this->model->orderByList(array(
            array('by' => 'is_square', 'to' => 'asc'),
            array('by' => 'width', 'to' => 'desc')
        ))->getPresets();

		foreach($presets as $p){

			if (!in_array($p['name'], $sizes, true)){
				continue;
			}

            if($file_name){
                $this->cms_uploader->setFileName($file_name.' '.$p['name']);
            }

			$path = $this->cms_uploader->resizeImage($result['path'], array(
				'width'     => $p['width'],
                'height'    => $p['height'],
                'is_square' => $p['is_square'],
                'quality'   => (($p['is_watermark'] && $p['wm_image']) ? 100 : $p['quality']) // потом уже при наложении ватермарка будет правильное качество
            ));

			if (!$path) { continue; }

			if ($p['is_watermark'] && $p['wm_image']){
				img_add_watermark($path, $p['wm_image']['original'], $p['wm_origin'], $p['wm_margin'], $p['quality']);
			}

			$result['paths'][$p['name']] = $path;

		}

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
