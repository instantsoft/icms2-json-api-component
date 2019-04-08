<?php

class actionMessagesApiMessagesGetNotices extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $request_params = array(
        'page' => array(
            'default' => 1,
            'rules'   => array(
                array('digits')
            )
        ),
        'perpage' => array(
            'default' => 15,
            'rules'   => array(
                array('digits')
            )
        ),
    );

    public function run(){

        $page    = $this->request->get('page');
        $perpage = $this->request->get('perpage');

        $total = $this->model->getNoticesCount($this->cms_user->id);

        $this->model->limitPage($page, $perpage);

        $notices = $this->model->getNotices($this->cms_user->id);

        $this->result['paging'] = array(
            'page'     => $page,
            'per_page' => $perpage
        );
        $this->result['count'] = $total;
        $this->result['items'] = $notices;

    }

}
