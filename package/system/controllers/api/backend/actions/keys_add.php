<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class actionApiKeysAdd extends cmsAction {

    public function run($id = null) {

        $form = $this->getForm('key');

        if(!isset($id)){
            $key = array(
                'is_pub'  => 1,
                'api_key' => string_random()
            );
        } else {

            $key = $old_key = $this->model->getKey($id);
            if (!$key) { cmsCore::error404(); }

        }

        if ($this->request->has('submit')){

            $key = $form->parse($this->request, true);

            $errors = $form->validate($this,  $key);

            if (!$errors){

                if(isset($id)){

                    $this->model->update('api_keys', $id, $key);

                    if($old_key['api_key'] != $key['api_key']){
                        cmsUser::addSessionMessage(LANG_API_KEY_UPDATE, 'info');
                    }

                } else {
                    $this->model->insert('api_keys', $key);
                }

                cmsUser::addSessionMessage(LANG_API_KEY_SUCCESS, 'success');

                $this->redirectToAction('keys');

            }

            if ($errors){
				cmsUser::addSessionMessage(LANG_FORM_ERRORS, 'error');
            }

        }

        return $this->cms_template->render('backend/key', array(
            'submit_title' => (!isset($id) ? LANG_ADD : LANG_SAVE),
            'title'        => (!isset($id) ? LANG_ADD : LANG_EDIT).' '.mb_strtolower(LANG_API_KEY),
            'key'          => $key,
            'form'         => $form,
            'errors'       => isset($errors) ? $errors : false
        ));

    }

}
