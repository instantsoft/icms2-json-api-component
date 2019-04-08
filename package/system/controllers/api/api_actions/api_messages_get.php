<?php

class actionMessagesApiMessagesGet extends cmsAction {

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
        'message_id' => array(
            'default' => 0,
            'rules'   => array(
                array('digits')
            )
        )
    );

    private $contact;

    public function validateApiRequest() {

        $this->contact = $this->model->getContact($this->cms_user->id, $this->request->get('contact_id'));

        if (!$this->contact){
            return array(
                'error_code'     => 100,
                'error_msg'      => '',
                'request_params' => array(
                    'contact_id' => ERR_VALIDATE_INVALID
                )
            );
        }

        return false;

    }

    public function run(){

        $message_id = $this->request->get('message_id');

        // если передан id сообщения, то показываем более ранние от него
        if($message_id){
            $this->model->filterLt('id', $message_id);
        }

        // чтобы не считать общее кол-во, получаем на один больше
        $messages = $this->model->limit($this->options['limit']+1)->getMessages($this->cms_user->id, $this->contact['id']);

        if(count($messages) > $this->options['limit']){
            $has_older = true; array_shift($messages);
        } else {
            $has_older = false;
        }

        $this->result = array(
            'is_me_ignored'    => $this->model->isContactIgnored($this->contact['id'], $this->cms_user->id),
            'is_private'       => !$this->cms_user->isPrivacyAllowed($this->contact, 'messages_pm'),
            'contact'          => $this->contact,
            'has_older'        => $has_older,
            'older_message_id' => $message_id,
            'items'            => $messages,
            'user_id'          => $this->cms_user->id
        );

    }

}
