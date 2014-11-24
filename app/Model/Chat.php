<?php

class Chat extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';

    /**
     * fetchChats method
     * @param string $relationship_id Relationship id to be fetched chats for in the database
     * @param string $last_chat_id Last chat id from where to start fetching chats, if present
     * @return array
     * @access public
     */
    public function fetchChats($relationship_id, $last_chat_id = '') {
        $conditions = array('relationshipId' => $relationship_id);
        $order = array('_id' => -1);

        // Check for ids greater than the last chat id, if present
        if ($last_chat_id != '') {
            // Set the params to fetch the _id of the chat id in the request
            $chat_params = array(
                'fields' => array('_id', 'chatId'),
                'conditions' => array('chatId' => $last_chat_id)
            );

            // Find last chat id's table id
            $last_chat = $this->find('first', $chat_params);

            // Set the condition to fetch records earlier than last chat id
            $conditions['_id']['$lt'] = $last_chat['Chat']['_id'];
            //$order = array('_id' => 1);
        }

        $params = array(
            'fields' => array('_id', 'chatId', 'QB_id', 'clicks', 'content', 'senderUserToken', 'message', 'relationshipId', 'isDelivered',
                                'userId', 'sentOn', 'type', 'cards', 'video_thumb', 'imageRatio', 'location_coordinates','sharedMessage'),
            'conditions' => $conditions,
            'order' => $order,
            'limit' => 20,
            'page' => 1
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);

        // Modifying the time format in each chat record in result
        //array_walk_recursive($results, array($this, 'changeTimeFormat'));
        foreach ($results as $key => $value) {            
            
            $params = array(
                'fields' => array('_id', 'QB_id'),
                'conditions' => array(
                                        'relationships.id' => new MongoId($results[$key]['Chat']['relationshipId']), 
                                        'relationships.partner_id' => $results[$key]['Chat']['userId'])
            );

            // Find records matching phone no.
            $User = ClassRegistry::init('User');
            $relationshipResults = $User->find('first', $params);
            $results[$key]['Chat']['receiverQB_id'] = $relationshipResults['User']['QB_id'];        
        }
        // Reversing the results fetched to show recent chats first
        $reversed_results = array_reverse($results);

        return $reversed_results;
    }

    /**
     * fetchUnreadMessageCount method
     * @param array $data
     * @param int $relationship_id
     * @return array
     * @access public
     */
    public function fetchUnreadMessageCount($data, $relationship_id = '' ) {
        $order = array('created' => 1);
        $userWiseUnreadMessageCountArr = array();
            
        if($relationship_id != '')
        {
            $conditions = array('relationshipId' => $relationship_id);
            // Find last chat id from user's sub collection relationships.
            $last_chat_id = '';
            $sender_user_id = '';
            $chatCount = 0;
            $created_mongo_id = '';
            if(count($data)>0 && isset($data[0]["User"]["relationships"]))
            {
                foreach($data[0]["User"]["relationships"] as $urKey => $urVal)
                {
                    if($relationship_id == (string) $data[0]["User"]["relationships"][$urKey]['id'])
                    {
                        $last_chat_id = isset($data[0]["User"]["relationships"][$urKey]['last_chat_id']) ? $data[0]["User"]["relationships"][$urKey]['last_chat_id'] : '';
                        $sender_user_id = isset($data[0]["User"]["relationships"][$urKey]['partner_id']) ? $data[0]["User"]["relationships"][$urKey]['partner_id'] : '';
                        break;
                    }
                }
            }
            
            // Set the condition to fetch records earlier than last chat id
            if($last_chat_id !='' && $sender_user_id != '')
            {
                // get mongo id..
                $chatList = $this->find('first', array('conditions' => array('chatId' => $last_chat_id)));
                $created_mongo_id = $chatList['Chat']['_id'];
                
                $conditions['_id']['$gt'] = $created_mongo_id;
                $conditions['userId'] = $sender_user_id;

                $params = array(
                    'fields' => array('_id', 'chatId','relationshipId','userId'),
                    'conditions' => $conditions,
                    'order' => $order,
                );
                // Find records matching phone no.
                $chatCount = $this->find('count', $params);
                
                $userWiseUnreadMessageCountArr[] = array(
                    'relationshipId' => $relationship_id,
                    'sender_user_id' => $sender_user_id,
                    'unreadChatCount' => $chatCount
                );
            }
            else
            {
                $userWiseUnreadMessageCountArr[] = array(
                    'relationshipId' => $relationship_id,
                    'sender_user_id' => $sender_user_id,
                    'unreadChatCount' => 0
                );
            }
        }
        else            
        {
            if(count($data)>0 && isset($data[0]["User"]["relationships"]))
            {
                foreach($data[0]["User"]["relationships"] as $urKey => $urVal)
                {
                    // Find last chat id from user's sub collection relationships.
                    $last_chat_id = ''; $sender_user_id = ''; $chatCount = 0;
                    $relationship_id = (string) $data[0]["User"]["relationships"][$urKey]['id'];
                    $last_chat_id = isset($data[0]["User"]["relationships"][$urKey]['last_chat_id']) ? $data[0]["User"]["relationships"][$urKey]['last_chat_id'] : '';
                    $sender_user_id = isset($data[0]["User"]["relationships"][$urKey]['partner_id']) ? $data[0]["User"]["relationships"][$urKey]['partner_id'] : '';
                    //echo $last_chat_id.'-'. $sender_user_id.'---'.$relationship_id.'===\n';
                    // Set the condition to fetch records earlier than last chat id
                    if($last_chat_id !='' && $sender_user_id != '' && $relationship_id != '')
                    {
                        $conditions = array('relationshipId' => $relationship_id);
                        // get mongo id..
                        $chatList = $this->find('first', array('conditions' => array('chatId' => $last_chat_id)));
                        $created_mongo_id = $chatList['Chat']['_id'];
                        //print_r($chatList);exit;
                        
                        $conditions['_id']['$gt'] = $created_mongo_id;
                        $conditions['userId'] = $sender_user_id;
                        
                        $params = array(
                            'fields' => array('_id', 'chatId','relationshipId','userId'),
                            'conditions' => $conditions,
                            'order' => $order,
                        );
                        // Find records matching phone no.
                        $chatCount = $this->find('count', $params);
                        $userWiseUnreadMessageCountArr[] = array(
                            'relationshipId' => $relationship_id,
                            'sender_user_id' => $sender_user_id,
                            'unreadChatCount' => $chatCount
                        );
                    }
                    else
                    {
                        $userWiseUnreadMessageCountArr[] = array(
                            'relationshipId' => $relationship_id,
                            'sender_user_id' => $sender_user_id,
                            'unreadChatCount' => 0
                        );
                    }
                }
            }
        }
        return $userWiseUnreadMessageCountArr;
    }
    
    /**
     * resetUnreadMessageCount method
     * @param array $data
     * @param int $relationship_id
     * @param int $last_chat_id
     * @return array
     * @access public
     */
    public function resetUnreadMessageCount($data, $relationship_id, $last_chat_id)
    {
        $User = ClassRegistry::init('User');
        if(count($data)>0 && isset($data[0]["User"]["relationships"]))
        {
            foreach($data[0]["User"]["relationships"] as $urKey => $urVal)
            {
                if($relationship_id == (string) $data[0]["User"]["relationships"][$urKey]['id'])
                    $data[0]["User"]["relationships"][$urKey]['last_chat_id'] = $last_chat_id;
            }
        }
        $userData = $data[0];
        return $User->save($userData);
    }
    
    /**
     * resetBadgeCount method
     * @param array $data
     * @param int $relationship_id
     * @param int $last_chat_id
     * @return array
     * @access public
     */
    public function resetBadgeCount($data)
    {
        $User = ClassRegistry::init('User');
        if(count($data)>0)
            $data[0]["User"]["badge_count"] = 0;
        $userData = $data[0];
        return $User->save($userData);
    }
    
    /**
     * changeTimeFormat method
     * @param string $value Value of the current key
     * @param string $key The current index of the array
     * @return void
     * @access private
     */
    private function changeTimeFormat(&$value, $key) {
        // Check if the current index is sentOn
        if ($key == 'sentOn') {
            // Convert the timestamp into hour:minute AM/PM
            $value = date('h:i A', strtotime($value));
        }
    }

}
