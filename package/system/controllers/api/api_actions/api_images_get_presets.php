<?php

class actionImagesApiImagesGetPresets extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public function run(){

		$presets = $this->model->orderByList(array(
            array('by' => 'is_square', 'to' => 'asc'),
            array('by' => 'width', 'to' => 'desc')
        ))->getPresets();

        $this->result['items'] = $presets;
        $this->result['count'] = count($presets);

    }

}
