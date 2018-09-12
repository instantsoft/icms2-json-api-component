<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

class onApiAdminDashboardChart extends cmsAction {

    public function run() {

        return array(
            'id'       => 'api',
            'title'    => LANG_API_CONTROLLER,
            'sections' => array(
                'errors_log' => array(
                    'title'  => LANG_API_STAT_ERRORS,
                    'table'  => 'api_logs',
                    'filter' => array(
                        array(
                            'condition' => 'nn',
                            'value'     => null,
                            'field'     => 'error'
                        )
                    ),
                    'key' => 'date_pub'
                ),
                'success_log' => array(
                    'title'  => LANG_API_STAT_OK,
                    'table'  => 'api_logs',
                    'filter' => array(
                        array(
                            'condition' => 'ni',
                            'value'     => null,
                            'field'     => 'error'
                        )
                    ),
                    'key' => 'date_pub'
                )
            )
        );
    }

}
