<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		                  http://instantmedia.ru/                         //
//                                written by Fuze                             //
//                                                                            //
/******************************************************************************/

class modelApi extends cmsModel {

    public function getKey($id) {

        if (is_numeric($id)) {
            $field = 'id';
        } else {
            $field = 'api_key';
        }

        $key = $this->filterEqual($field, $id)->getItem('api_keys');

        if ($key) {
            $key['key_methods'] = cmsModel::yamlToArray($key['key_methods']);
        }

        return $key;
    }

    public function deleteKey($id) {

        $this->delete('api_keys', $id);
        $this->delete('api_logs', $id, 'key_id');

        return true;
    }

    public function log($data) {

        $this->insert('api_logs', $data);

        return false;
    }

}
