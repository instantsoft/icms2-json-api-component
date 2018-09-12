<?php

class actionGeoApiGeoGet extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $request_params = array(
        'type' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('in_array', array('regions', 'cities', 'countries'))
            )
        ),
        'parent_id' => array(
            'default' => '',
            'rules'   => array(
                array('number')
            )
        )
    );

    public function run(){

        $type = $this->request->get('type', '');
        $parent_id = $this->request->get('parent_id', 0);

        $data = array();

        switch ( $type ){

            case 'regions': $items = $this->model->getRegions( $parent_id );
                            $select_text = LANG_GEO_SELECT_REGION;
                            break;

            case 'cities':  $items = $this->model->getCities( $parent_id );
                            $select_text = LANG_GEO_SELECT_CITY;
                            break;

            case 'countries':  $items = $this->model->getCountries();
                            $select_text = LANG_GEO_SELECT_COUNTRY;
                            break;

        }

        if ($items){

            $items = array_unique($items);

            $items = array('0' => $select_text) + $items;

            foreach ($items as $id => $name){
                $data[] = array(
                    'id'   => $id,
                    'name' => $name
                );
            }

        }

        $this->result['count'] = count($items);
        $this->result['items'] = $items;

    }

}
