<?php
/******************************************************************************/
//                                                                            //
//                                 InstantMedia                               //
//	 		                  http://instantmedia.ru/                         //
//                                written by Fuze                             //
//                                                                            //
/******************************************************************************/

class actionApiKeys extends cmsAction {

    public function run() {

        $grid = $this->loadDataGrid('keys');

        if ($this->request->isAjax()) {

            $this->model->setPerPage(30);

            $filter     = [];
            $filter_str = $this->request->get('filter', '');

            if ($filter_str) {
                parse_str($filter_str, $filter);
                $this->model->applyGridFilter($grid, $filter);
            }

            $total   = $this->model->getCount('api_keys');
            $perpage = isset($filter['perpage']) ? $filter['perpage'] : 30;
            $pages   = ceil($total / $perpage);

            $data = $this->model->get('api_keys');

            $this->cms_template->renderGridRowsJSON($grid, $data, $total, $pages);

            $this->halt();
        }

        return $this->cms_template->render('backend/keys', [
            'grid' => $grid
        ]);
    }
}
