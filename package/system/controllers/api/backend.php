<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class backendApi extends cmsBackend {

    public $useDefaultOptionsAction = true;

    public function actionIndex(){
        $this->redirectToAction('options');
    }

    public function getBackendMenu(){
        return array(
            array(
                'title' => LANG_OPTIONS,
                'url'   => href_to($this->root_url, 'options')
            ),
            array(
                'title' => LANG_API_KEYS,
                'url'   => href_to($this->root_url, 'keys')
            )
        );
    }

}
