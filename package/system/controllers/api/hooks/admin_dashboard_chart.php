<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		                  http://instantmedia.ru/                         //
//                                written by Fuze                             //
//                                                                            //
/******************************************************************************/

class onApiAdminDashboardChart extends cmsAction {

    public function run() {

        $sections = [
            'success_log' => [
                'title'  => LANG_API_STAT_OK,
                'table'  => 'api_logs',
                'filter' => array(
                    array(
                        'condition' => 'ni',
                        'value'     => '',
                        'field'     => 'error'
                    )
                ),
                'key' => 'date_pub'
            ]
        ];

        $errors_methods = $this->model->
                selectOnly('method')->
                filterNotNull('error')->
                groupBy('method')->
                get('api_logs', function($i, $model){
                    return $i['method'];
                }, false);

        if($errors_methods){

            $sections['errors_log'] = [
                'title'  => LANG_API_STAT_ERRORS,
                'hint'   => LANG_API_STAT_ERRORS_HINT,
                'table'  => 'api_logs',
                'style' => [
                    'bg_color' => 'rgba(248, 108, 107, 0.1)',
                    'border_color' => 'rgba(248, 108, 107, 1)'
                ],
                'filter' => array(
                    array(
                        'condition' => 'nn',
                        'value'     => '',
                        'field'     => 'error'
                    )
                ),
                'key' => 'date_pub'
            ];

            foreach ($errors_methods as $errors_method) {

                $hint = (!$errors_method ? LANG_API_STAT_ERRORS_HINT1 : $errors_method);

                $sections['errors_log:'.str_replace('.', '_', $errors_method)] = [
                    'hint' => $hint,
                    'table'  => 'api_logs',
                    'filter' => array(
                        array(
                            'condition' => (!$errors_method ? 'ni' : 'eq'),
                            'value'     => (!$errors_method ? '' : $errors_method),
                            'field'     => 'method'
                        ),
                        array(
                            'condition' => 'nn',
                            'value'     => '',
                            'field'     => 'error'
                        )
                    ),
                    'style' => $this->getStyles($hint),
                    'key' => 'date_pub'
                ];
            }
        }

        return array(
            'id'       => 'api',
            'title'    => LANG_API_CONTROLLER,
            'sections' => $sections
        );
    }

    private function getStyles($title) {

        $bg_color = substr(dechex(crc32($title)), 0, 6);

        $r = hexdec( substr($bg_color, 0, 2) );
        $g = hexdec( substr($bg_color, 2, 2) );
        $b = hexdec( substr($bg_color, 4, 2) );

        return [
            'bg_color' => "rgba({$r}, {$g}, {$b}, 0.1)",
            'border_color' => "rgba({$r}, {$g}, {$b}, 1)"
        ];

    }

}
