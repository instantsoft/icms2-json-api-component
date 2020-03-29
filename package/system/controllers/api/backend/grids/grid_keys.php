<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		      http://instantmedia.ru/, support@instantmedia.ru            //
//                               written by Fuze                              //
//                                                                            //
/******************************************************************************/

function grid_keys($controller){

    $options = array(
        'is_sortable'   => false,
        'is_filter'     => false,
        'is_pagination' => true,
        'is_draggable'  => false,
        'order_by'      => 'id',
        'order_to'      => 'desc',
        'show_id'       => true
    );

    $columns = array(
        'id' => array(
            'title' => 'id',
            'width' => 30
        ),
        'description' => array(
            'title' => LANG_DESCRIPTION,
            'href' => href_to($controller->root_url, 'keys_edit', array('{id}')),
            'editable' => array(
                'table' => 'api_keys'
            )
        ),
        'api_key' => array(
            'title' => LANG_API_KEY,
            'width' => 200
        ),
        'is_pub' => array(
            'title'       => LANG_ON,
            'flag'        => true,
            'flag_toggle' => href_to($controller->root_url, 'toggle_item', array('{id}', 'api_keys', 'is_pub')),
            'width'       => 80
        ),
    );

    $actions = array(
        array(
            'title' => LANG_EDIT,
            'class' => 'edit',
            'href'  => href_to($controller->root_url, 'keys_edit', array('{id}'))
        ),
        array(
            'title'   => LANG_DELETE,
            'class'   => 'delete',
            'href'    => href_to($controller->root_url, 'keys_delete', array('{id}')),
            'confirm' => LANG_API_DELETE_CONFIRM
        )
    );

    return array(
        'options' => $options,
        'columns' => $columns,
        'actions' => $actions
    );

}
