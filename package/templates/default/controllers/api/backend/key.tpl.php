<?php

    $this->addBreadcrumb(LANG_API_KEYS, $this->href_to('keys'));

    $this->addBreadcrumb($title);

    $this->addToolButton(array(
        'class' => 'save',
        'title' => LANG_SAVE,
        'href'  => "javascript:icms.forms.submit()"
    ));
    $this->addToolButton(array(
        'class' => 'cancel',
        'title' => LANG_CANCEL,
        'href'  => $this->href_to('keys')
    ));

    $this->renderForm($form, $key, array(
        'action' => '',
        'submit' => array('title'=>$submit_title),
        'method' => 'post'
    ), $errors);
