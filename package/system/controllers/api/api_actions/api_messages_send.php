<?php

class actionMessagesApiMessagesSend extends cmsAction {

    public $lock_explicit_call = true;

    public $result;

    public $auth_required = true;

    public $check_sig = true;

    public $request_params = array(
        'contact_id' => array(
            'default' => 0,
            'rules'   => array(
                array('required'),
                array('digits')
            )
        ),
        'content' => array(
            'default' => '',
            'rules'   => array(
                array('required'),
                array('max_length', 65535)
            )
        ),
        'csrf_token' => array(
            'default' => '',
            'rules'   => array(
                array('required')
            )
        )
    );

    private $message, $contact_id;

    public function validateApiRequest() {

        if (!cmsForm::validateCSRFToken($this->request->get('csrf_token'))){
            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => array(
                    'csrf_token' => LANG_API_ERROR_CSRF_TOKEN
                )
            );
        }

        $this->contact_id = $this->request->get('contact_id');

        $is_contact_exists = $this->model->isContactExists($this->cms_user->id, $this->contact_id);

        if ($is_contact_exists){
            $this->model->updateContactsDateLastMsg($this->cms_user->id, $this->contact_id, false);
        }

        if (!$is_contact_exists){
            $this->model->addContact($this->cms_user->id, $this->contact_id);
        }

        $contact = $this->model->getContact($this->cms_user->id, $this->contact_id);

        // Контакт не в игноре у отправителя?
        if ($contact['is_ignored']){
            return array(
                'error_msg' => LANG_PM_CONTACT_IS_IGNORED
            );
        }

        // Отправитель не в игноре у контакта?
        if ($this->model->isContactIgnored($this->contact_id, $this->cms_user->id)){
            return array(
                'error_msg' => LANG_PM_YOU_ARE_IGNORED
            );
        }

        // Контакт принимает сообщения от этого пользователя?
        if (!$this->cms_user->isPrivacyAllowed($contact, 'messages_pm')){
            return array(
                'error_msg' => LANG_PM_CONTACT_IS_PRIVATE
            );
        }

        $this->message = cmsEventsManager::hook('html_filter', $this->request->get('content'));

		if (!$this->message) {
            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => array(
                    'content' => ERR_VALIDATE_REQUIRED
                )
            );
		}

        return false;

    }

    public function run(){

        $this->setSender($this->cms_user->id)->addRecipient($this->contact_id);

        $message_id = $this->sendMessage($this->message);

        //
        // Отправляем уведомление на почту
        //
        $user_to = cmsCore::getModel('users')->getUser($this->contact_id);

        if (!$user_to['is_online']) {

            if($this->model->getNewMessagesCount($user_to['id']) == 1){
                $this->sendNoticeEmail('messages_new', array(
                    'user_url'      => href_to_abs('users', $this->cms_user->id),
                    'user_nickname' => $this->cms_user->nickname,
                    'message'       => strip_tags($this->message)
                ));
            }

        }

        $message = $this->model->getMessage($message_id);

        $this->result = array(
            'message' => $message,
            'date'    => date($this->cms_config->date_format, time()),
            'user_id' => $this->cms_user->id
        );

    }

}
