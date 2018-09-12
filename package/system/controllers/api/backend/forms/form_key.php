<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class formApiKey extends cmsForm {

    public function init() {

        $generator = function($item){
            static $items = null;
            if($items === null){
                $api_actions = cmsCore::getFilesList('system/controllers/api/api_actions/', 'api_*.php');
                $actions = cmsCore::getFilesList('system/controllers/api/actions/', 'api_*.php');
                $hooks = cmsCore::getFilesList('system/controllers/api/hooks/', 'api_*.php');
                $files = array_unique(array_merge($hooks, $actions, $api_actions));
                $items = array();
                if ($files) {
                    foreach ($files as $file_name) {
                        $name = str_replace(array('api_', '.php'), '', $file_name);
                        $name = substr_replace($name, '.', strpos($name, '_')).ltrim(strstr($name, '_'), '_');
                        $items[$name] = $name;
                    }
                }
            }
            return $items;
        };

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

                    new fieldText('ip_access', array(
                        'title' => LANG_API_ALLOW_IPS,
                        'hint'  => sprintf(LANG_CP_SETTINGS_ALLOW_IPS_HINT, cmsUser::getIp())
                    )),

                    new fieldListMultiple('key_methods:allow', array(
                        'title'    => LANG_API_ALLOW_METHODS,
                        'default'  => 0,
                        'show_all' => true,
                        'generator' => $generator
                    )),

                    new fieldListMultiple('key_methods:disallow', array(
                        'title'    => LANG_API_DISALLOW_METHODS,
                        'default'  => 0,
                        'generator' => $generator
                    ))

                )
            )

        );

    }

}
