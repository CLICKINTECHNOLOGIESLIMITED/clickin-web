<?php

App::import('Component', 'Quickblox');
App::import('Component', 'Pushnotification');

class FetchchatShell extends AppShell {

    /**
     * This shell class uses User, Chat model
     *
     * @var array
     */
    public $uses = array('User', 'Chat');

    /**
     * Quickblox property
     *
     * @access public
     */
    var $Quickblox;

    /**
     * Pushnotification property
     *
     * @access public
     */
    var $Pushnotification;

    /**
     * DeleteChats property
     *
     * @var integer 0
     * @access public
     */
    var $DeleteChats = 0;

    /**
     * Startup for the Quickblox & Pushnotification components initialization 
     * 
     */
    function initialize() {
        $this->Quickblox = & new QuickbloxComponent(new ComponentCollection());
        $this->Pushnotification = & new PushnotificationComponent(new ComponentCollection());
    }

    /**
     * retrieveChatRecordsQB method
     *
     * @return void
     * @access public   
     */
    public function retrieveChatRecordsQB() {
        
        // Fetch the chats from the Quickblox server
        $chats_data = $this->Quickblox->fetchChatHistory();

        // Check if chat items were retrieved
        if (count($chats_data->items) > 0) {
            
            $processed_chats = array();
            // Process each chat entry and insert into collection
            foreach ($chats_data->items as $chat) {
            
                // add implementation about delived message..
                if($chat->type == 7)
                {
                    $chat_id = $chat->_id;
                    $deliverd_chat_id = $chat->deliveredChatID;
                    if($deliverd_chat_id!='')
                    {
                        $delived_chat_data = $this->Chat->find( 'first', array( 'conditions' => array( 'chatId' => $deliverd_chat_id ) ) );
                        if(count($delived_chat_data)>0) {
                            $delived_chat_data['Chat']['isDelivered'] = 'yes';
                            $this->Chat->save($delived_chat_data);
                        }
                    }
                    // Record the chat _id which has been processed
                    $processed_chats[] = $chat_id;
                }
                else
                {
                    $chat_id = $chat->_id;
                    $chat->QB_id = $chat->user_id; // Saving the user id from QuickBlox as QB_id
                    // Remove unecessary fields
                    unset($chat->_id);
                    unset($chat->_parent_id);
                    unset($chat->user_id);
                    unset($chat->created_at);
                    unset($chat->updated_at);

                    // Check if clicks are present
                    if ($chat->clicks !== NULL && (!isset($chat->sharedMessage) || count($chat->sharedMessage) == 0)) {
                        $this->updateClicks(trim($chat->clicks), $chat->userId, $chat->relationshipId, $chat->type);
                    }
                    elseif($chat->type == CHAT_TYPE_CARD && $chat->cards !== NULL && $chat->cards[5] == 'accepted' 
                             && (!isset($chat->sharedMessage) || count($chat->sharedMessage) == 0)) {
                        // Check if cards have been sent and accepted in the chat
                        $this->updateClicks(trim($chat->clicks), $chat->userId, $chat->relationshipId, $chat->type, $chat->cards);
                    }

                    $currentCardStatus = 'playing';
                    if($chat->cards !== NULL) {
                        switch ($chat->cards[5]) {
                            case 'accepted':
                                $currentCardStatus = 'played';
                                break;
                            case 'countered':
                                $currentCardStatus = 'playing';
                                break;
                            case 'rejected':
                                $currentCardStatus = 'played';
                                break;
                            default:
                                $currentCardStatus = 'playing';
                                break;
                        }
                        $chat->cards[9] = $currentCardStatus;
                        
                        // set blank string in case of index does not exist...
                        if(!isset($chat->cards[8])) {
                            $chat->cards[8] = '';
                            // Sorting cards array to format response JSON properly
                            ksort($chat->cards);
                        }
                    }

                    $chat_data = $this->Chat->create();
                    $chat->isDelivered = 'no';
                    $chat_data = $chat;
                    
                    $chatSaveFlag = $this->Chat->save($chat_data);
                    
                    // Insert a new entry into the chats collection
                    if ($chatSaveFlag) {
                        
                        $this->out("Processed Chat Entry : " . $chat_id . "\n");
        
                        // update play status in all related card entries..
                        if($chat->cards !== NULL) {

                            $cardUniqueId = $chat->cards[0];                        
                            $chat_data = $this->Chat->find( 'all', array( 'conditions' => array( 'cards.0' => $cardUniqueId ) ) );
                            if(count($chat_data)>0) {
                                foreach ($chat_data as $cDataVal) {
                                    $cDataVal['Chat']['cards'][9] = $currentCardStatus;
                                    $this->Chat->save($cDataVal);
                                }
                            }
                        }                    

                        // send push notifications..
                        $message = $this->seneNotifications($chat);
                        echo "Processed Push Notification : " . $message . "\n";
                        
                        CakeLog::write('info', "\nProcessed Push Notification : " . $message, array('clickin'));
                        
                        // Record the chat _id which has been processed
                        $processed_chats[] = $chat_id;
                    }
                }
            }
            
            CakeLog::write('info', "\nProcessing delete QB data : " . print_r($processed_chats), array('clickin'));           
            

            // Delete the processed entries from QB
            $delete_records = $this->deleteChatRecordsQB($processed_chats);

            // If entries were deleted, then recursively call function
            if ($delete_records) {
                //$this->retrieveChatRecordsQB();                
            }
        }
        
        die;
    }

    /**
     * deleteChatRecordsQB method
     *
     * @return boolean
     * @access private
     */
    private function deleteChatRecordsQB($chat_ids) {
        if (count($chat_ids) > 0) {
            // Remove the chat records from the QB Custom Object
            $delete_processed_chats = $this->Quickblox->deleteChatRecords($chat_ids);

            // Fetch all entries that were deleted
            $deleted_chats = $delete_processed_chats->SuccessfullyDeleted->ids;

            // Check all entries were deleted with the input array
            $chats_pending = array_diff($chat_ids, $deleted_chats);

            // Increment $DeleteChats property to run only thrice
            $this->DeleteChats++;

            // Some chat entries were not deleted
            // The try for deleting chat records can be run only thrice
            if (count($chats_pending) > 0 && $this->DeleteChats < 3) {
                // Repeat the process of deleting chat entries
                $this->deleteChatRecordsQB($chats_pending);
            }

            return true;
        } else {
            return false;
        }
    }

    /*
     * updateClicks method
     *
     * @param string $clicks Clicks received in the chat
     * @param string $user_id Id of the user who received the clicks
     * @param string $relationship_id Id of the relationship for chat
     * @param int $clicks
     * @param int $user_id
     * @param int $relationship_id
     * @param int $chat_type
     * @param array $chat_cards
     * @return void
     * @access private
     */

    private function updateClicks($clicks, $user_id, $relationship_id, $chat_type, $chat_cards = NULL) {
        
        if(abs($clicks) > 0 && $clicks!='') {
            
            // Fetch the partner's data
            $partner_data = $this->User->find(
                    'first', array(
                'fields' => array('relationships'),
                'conditions' => array(
                    'relationships.id' => new MongoId($relationship_id),
                    'relationships.partner_id' => $user_id // Find the user who's partner id matches the user's id
                )
                    )
            );

            // Prepare new array to update relationships
            $new_partner_data['_id'] = $partner_data['User']['_id'];

            // Loop through all relationships of the user
            foreach ($partner_data['User']['relationships'] as $key => $relationship) {
                // Check which relationship contains the relationship id given
                if ($relationship['id'] == new MongoId($relationship_id)) {
                    // Update the clicks for that relationship
                        if (abs($clicks) > 0) {
                    $relationship['user_clicks'] = $relationship['user_clicks'] + $clicks;
                }
                    }
                // Set all the relationships in the array to be saved
                $new_partner_data['relationships'][$key] = $relationship;
            }

            // Save the user's modified data
            if($this->User->save($new_partner_data))
            {
                // Fetch the chat initiated user's data
                $results = $this->User->find('first', array(
                    'fields' => array('_id', 'relationships'),
                    'conditions' => array( '_id' => new MongoId($user_id), 'relationships.partner_id' => $partner_data['User']['_id'] )
                ));

                if (count($results) > 0) {
                    foreach ($results["User"]["relationships"] as $urKey => $urVal) {
                        // match relationship id to relationship id of results array..
                        if ($partner_data['User']['_id'] == (string) $results["User"]["relationships"][$urKey]['partner_id']) {
                            // deduct clicks from account of initiated user..
                            if (abs($clicks) > 0) {
                                $results["User"]["relationships"][$urKey]["clicks"] += $clicks;
                            }
                        }
                    }
                    $this->User->save($results);
                }
            }        
        }

        // deduct clicks due to cards for initiated user's account.. 
        if ($chat_type == CHAT_TYPE_CARD && $chat_cards !== NULL && $chat_cards[5] == 'accepted') {
            $initiated_user_id = $chat_cards[6];
            $deductedClicks = $chat_cards[4];

            // Fetch the chat initiated user's data
            $results = $this->User->find('first', array(
                'fields' => array('_id', 'relationships'),
                'conditions' => array('relationships.id' => new MongoId($relationship_id), '_id' => new MongoId($initiated_user_id))
            ));
            if (count($results) > 0) {
                foreach ($results["User"]["relationships"] as $urKey => $urVal) {
                    // match relationship id to relationship id of results array..
                    if ($relationship_id == (string) $results["User"]["relationships"][$urKey]['id']) {
                        // deduct clicks from account of initiated user..
                        if ($deductedClicks > 0) {
                            $results["User"]["relationships"][$urKey]["user_clicks"] -= $deductedClicks;
                            $results["User"]["relationships"][$urKey]["clicks"] += $deductedClicks;
                        }
                    }
                }
                if($this->User->save($results))
                {
                    // Fetch the chat initiated user's partner data
                    $results = $this->User->find('first', array(
                        'fields' => array('_id', 'relationships'),
                        'conditions' => array( 'relationships.id' => new MongoId($relationship_id),'relationships.partner_id' => $initiated_user_id )
                    ));
                    if (count($results) > 0) {
                        foreach ($results["User"]["relationships"] as $urKey => $urVal) {
                            // match relationship id to relationship id of results array..
                            if ($initiated_user_id == (string) $results["User"]["relationships"][$urKey]['partner_id']) {
                                // deduct clicks from account of initiated user..
                                if ($deductedClicks > 0) {
                                    $results["User"]["relationships"][$urKey]["user_clicks"] += $deductedClicks;
                                    $results["User"]["relationships"][$urKey]["clicks"] -= $deductedClicks;
                                }
                            }
                        }
                        $this->User->save($results);
                    }
                }
            }
        }
    }
    
    /**
     * seneNotifications method
     * this function is used to send push notifications..
     * 
     * @param object $chat
     * @access public
     */
    private function seneNotifications($chat)
    {
        // Fetch the chat initiated user's data
        $results = $this->User->find('first', array(
            'fields' => array('_id', 'name','QB_id','phone_no','user_pic','relationships'),
            'conditions' => array( '_id' => new MongoId($chat->userId))
        ));

        // Fetch the partner's data
        $partner_data = $this->User->find(
                'first', array(
                            'fields' => array('device_token','device_type','name','is_enable_push_notification'),
                            'conditions' => array(
                                'relationships.id' => new MongoId($chat->relationshipId),
                                'relationships.partner_id' => $chat->userId // Find the user who's partner id matches the user's id
                            )
                )
        );
        
        /*if(isset($partner_data['User']['is_enable_push_notification']) && $partner_data['User']['is_enable_push_notification'] == 'yes' 
                || !isset($partner_data['User']['is_enable_push_notification']))
        {*/
                
            $device_type = $partner_data['User']['device_type'];
            $device_token = $partner_data['User']['device_token'];

            $message = '';
            $subStrLen = 12;
            $payLoadData = array();
            switch ($chat->type) {
                case '1':   // text
                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {
                            $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }                        
                    }
                    else
                        $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                                        trim($results["User"]["name"]) . ' has sent you a message';

                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {
                            $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }
                    }
                    else
                        $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                            ($chat->message != '' ? ( strlen($chat->message) > $subStrLen ? trim($results["User"]["name"]) . ": ".substr($chat->message, 0, $subStrLen)."..." 
                                : trim($results["User"]["name"]) . ": ". $chat->message ) : '');                

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ? 'clk' : 'chat', 
                                //'Notfication Text' => $message,
                                "chat_message" => $chat_message
                            );
                    break;
                case '2':   // image
                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {                            
                            $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }
                    }
                    else
                        $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                                                            trim($results["User"]["name"]) . ' has sent you a photo';

                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {
                            $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }
                    }
                    else
                        $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                                                            trim($results["User"]["name"]) . ' has sent you a photo';

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ?  'clk' : 'media', 
                                //'Notfication Text' => $message,
                                "chat_message" => $chat_message
                            );
                    break;
                case '3':   // audio
                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {
                            $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }
                    }
                    else
                        $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                                                            trim($results["User"]["name"]) . ' has sent you an audio note';

                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {
                            $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }
                    }
                    else
                        $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                                                            trim($results["User"]["name"]) . ' has sent you an audio note';

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ? 'clk' : 'media',  
                                //'Notfication Text' => $message,
                                "chat_message" => $chat_message
                            );                
                    break;
                case '4':   // video
                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {
                            $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }
                    }
                    else
                        $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                                                            trim($results["User"]["name"]) . ' has sent you a video';

                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {
                            $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }
                    }
                    else
                        $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                                                            trim($results["User"]["name"]) . ' has sent you a video';

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ?  'clk' : 'media',  
                                //'Notfication Text' => $message,
                                "chat_message" => $chat_message
                            );                
                    break;
                case '6':   // location
                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {
                            $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }
                    }
                    else
                        $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                                                            trim($results["User"]["name"]) . ' has shared their location';

                    if($chat->sharedMessage !== NULL) {
                        if($chat->sharedMessage[3] == "null") {
                            $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                        }
                    }
                    else
                        $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent you a Click!" : 
                                                            trim($results["User"]["name"]) . ' has shared their location';

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ? 'clk' : 'media',  
                                //'Notfication Text' => $message,
                                "chat_message" => $chat_message
                            );                
                    break;
                case '5':   // cards
                    if($chat->cards !== NULL && ($chat->sharedMessage === NULL || !isset($chat->sharedMessage))) {
                        $chat_message = '';
                        switch ($chat->cards[5]) {
                            case 'accepted':
                                $chat_message = trim($results["User"]["name"]) . ' has accepted your offer';
                                $message = trim($results["User"]["name"]) . ' has accepted your offer'; 
                                break;
                            case 'countered':
                                $chat_message = trim($results["User"]["name"]) . ' just countered your offer';
                                $message = trim($results["User"]["name"]) . ' just countered your offer'; 
                                break;
                            case 'rejected':
                                $chat_message = 'Oops Try Again! ' . trim($results["User"]["name"]) . ' rejected your offer!';
                                $message = 'Oops Try Again! ' . trim($results["User"]["name"]) . ' rejected your offer!'; 
                                break;
                            default:
                                //$chat_message = trim($results["User"]["name"]) . ' has played a card!';
                                //$message = trim($results["User"]["name"]) . ' has played a card!'; 
                                $chat_message = trim($results["User"]["name"]) . ' is offering you clicks for something. Go see what !';
                                $message = trim($results["User"]["name"]) . ' is offering you clicks for something. Go see what !'; 
                                break;
                        }
                        $payLoadData = array(
                                    'Tp' => 'card', 
                                    //'Notfication Text' => $message, //$results["User"]["name"] . ' sent a trade card to you.',
                                    "chat_message" => $chat_message
                                );
                    } else {
                        if($chat->sharedMessage !== NULL) 
                        {
                            if($chat->sharedMessage[3] == "null") {
                            
                                $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                                trim($results["User"]["name"]) . ' wants to post to The Feed';
                                $chat_message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " wants to post to The Feed" : 
                                                            trim($results["User"]["name"]) . ' wants to post to The Feed';
                            }
                            $payLoadData = array(
                                    'Tp' => 'card', 
                                    //'Notfication Text' => $message, //$results["User"]["name"] . ' sent a trade card to you.',
                                    "chat_message" => $chat_message
                                );
                        }
                    }
                    break;
                default:
                    break;
            }

            // getting clicks and user_clicks..
            $clicks = 0;
            $user_clicks = 0;
            $relation_deleted = 'no';
            //$messageEmail = "";
            if(count($results["User"]["relationships"])>0) {
                foreach($results["User"]["relationships"] as $urKey => $urVal)
                {
                    //$messageEmail .= $chat->relationshipId. " :: ".$results["User"]["relationships"][$urKey]['id']." <br>";
                    if($chat->relationshipId == (string) $results["User"]["relationships"][$urKey]['id'])
                    {
                        $clicks = $results["User"]["relationships"][$urKey]['clicks'];
                        $user_clicks = $results["User"]["relationships"][$urKey]['user_clicks'];
                        $relation_deleted = isset($results["User"]["relationships"][$urKey]['deleted']) ? $results["User"]["relationships"][$urKey]['deleted'] : 'no';
                        //$messageEmail .= "$clicks :: $user_clicks <br>";
                        break;
                    }
                }
            } 
            //$messageEmail .= "$clicks :: $user_clicks <br>";
            $payLoadData += array(
                                    "Rid"  => $chat->relationshipId, 
                                    "phn" => $results['User']['phone_no'], 
                                    "pid" => (string) $results['User']['_id'], 
                                    "pname" => $results['User']['name'], 
                                    //"partner_pic" => $results['User']['user_pic'], 
                                    "pQBid" => $results['User']['QB_id'], 
                                    "clk"  => $clicks,
                                    "uclk" => $user_clicks
                                );
            
            if($device_type!= '' && $device_token!= '' && $message!= '' && (!isset($relation_deleted) || $relation_deleted != 'yes')) {

                $messageEmail = '';
                App::uses('CakeEmail', 'Network/Email');
                $Email = new CakeEmail();            
                $Email->from(array('me@clickin.com' => 'My Site'));
                $Email->to('saurabh.singh@sourcefuse.com');
                $Email->subject('crone data');
                $Email->emailFormat('html');
                $messageEmail .= "device_type : ".$device_type." device_token: ".$device_token." relationshipId :" .  $chat->relationshipId.' :: '.$results['User']['phone_no'] . " relation_deleted :: ". $relation_deleted ." :: PLoad Data :: " . serialize($payLoadData);
                $Email->send($messageEmail);
                
                //CakeLog::write('info', "\n PN Status before : " . $messageEmail, array('clickin'));
                
                $returnStr = $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);
                
                CakeLog::write('info', "\n PN Status : " . $returnStr, array('clickin'));
                
                return   $returnStr;      
            }
            else
                return 'No message found for send.';
        /*}
        else
            return 'No message found for send.';*/
    }    
}

?>
