<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class actionApiKeysEdit extends cmsAction {

	public function run($id = null) {
        return $this->runExternalAction('keys_add', $this->params);
	}

}
