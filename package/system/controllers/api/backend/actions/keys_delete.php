<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class actionApiKeysDelete extends cmsAction {

	public function run($id = null) {

        $key = $this->model->getKey($id);
        if (!$key) { cmsCore::error404(); }

        $this->model->deleteKey($id);

        cmsUser::addSessionMessage(LANG_API_KEY_DELETE, 'success');

        $this->redirectToAction('keys');

	}

}
