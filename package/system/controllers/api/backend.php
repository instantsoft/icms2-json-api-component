<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		                  http://instantmedia.ru/                         //
//                                written by Fuze                             //
//                                                                            //
/******************************************************************************/

class backendApi extends cmsBackend {

    public $useDefaultOptionsAction = true;

    public function actionIndex() {
        $this->redirectToAction('options');
    }

    public function getBackendMenu() {

        return [
            [
                'title' => LANG_OPTIONS,
                'url'   => href_to($this->root_url, 'options')
            ],
            [
                'title' => LANG_API_KEYS,
                'url'   => href_to($this->root_url, 'keys')
            ]
        ];
    }

}
