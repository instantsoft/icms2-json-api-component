<?php
/******************************************************************************/
//                                                                            //
//                             InstantMedia 2016                              //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class formApiKey extends cmsForm {

    public function init() {

        return array(

            array(
                'type'  => 'fieldset',
                'title' => LANG_BASIC_OPTIONS,
                'childs' => array(

                    new fieldCheckbox('is_pub', array(
                        'title' => LANG_API_KEY_IS_PUB
                    )),

                    new fieldString('api_key', array(
                        'title' => LANG_API_KEY_CODE,
                        'hint'  => LANG_API_KEY_CODE_HINT,
                        'options'=>array(
                            'max_length'=> 32,
                            'show_symbol_count'=>true
                        ),
                        'rules' => array(
                            array('required')
                        )
                    )),

                    new fieldText('description', array(
                        'title' => LANG_DESCRIPTION,
                        'options'=>array(
                            'max_length'=> 100,
                            'show_symbol_count'=>true
                        )
                    )),

                    new fieldText('allow_ips', array(
                        'title' => LANG_API_ALLOW_IPS,
                        'hint'  => LANG_CP_SETTINGS_ALLOW_IPS_HINT
                    ))

                )
            )

        );

    }

}
