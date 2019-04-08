<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class formApiOptions extends cmsForm {

    public function init() {

        return array(

            array(
                'type'  => 'fieldset',
                'title' => LANG_BASIC_OPTIONS,
                'childs' => array(

                    new fieldCheckbox('log_error', array(
                        'title' => LANG_API_LOG_ERROR
                    )),

                    new fieldCheckbox('log_success', array(
                        'title' => LANG_API_LOG_SUCCESS
                    )),

                    new fieldCheckbox('allow_admin_login', array(
                        'title' => LANG_API_ALLOW_ADMIN_LOGIN
                    ))

                )
            )

        );

    }

}
