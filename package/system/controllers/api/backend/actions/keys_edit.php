<?php
/******************************************************************************/
//                                                                            //
//                             InstantMedia 2016                              //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class actionApiKeysEdit extends cmsAction {

	public function run($id=null){
        return $this->runAction('keys_add', $this->params);
	}

}
