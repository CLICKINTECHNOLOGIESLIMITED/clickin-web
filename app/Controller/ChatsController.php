<?php

/**
 * ChatsController class
 *
 * @uses          AppController
 * @package       mongodb
 * @subpackage    mongodb.samples.controllers
 */
class ChatsController extends AppController {

    /**
     * use data model property
     * @var array
     * @access public 
     */
    var $uses = array('Newsfeeds', 'User', 'Notification', 'Chat', 'Sharing', 'Card', 'Category');

    /**
     * name property
     *
     * @var string 'Chats'
     * @access public
     */
    public $name = 'Chats';

    /**
     * components property
     * 
     * @var array
     * @access public
     */
    public $components = array('Quickblox','Facebook','Twitter','Googleplus','Pushnotification');

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
                    $deliverd_chat_id = $chat->deliveredChatID;
                    if($deliverd_chat_id!='')
                    {
                        $delived_chat_data = $this->Chat->find( 'first', array( 'conditions' => array( 'chatId' => $deliverd_chat_id ) ) );
                        if(count($delived_chat_data)>0) {
                            $delived_chat_data['Chat']['isDelivered'] = 'yes';
                            $this->Chat->save($delived_chat_data);
                        }
                    }
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
                    
                    // Insert a new entry into the chats collection
                    if ($this->Chat->save($chat_data)) {
                        echo "Processed Chat Entry : " . $chat_id . "\n";

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

                        // Record the chat _id which has been processed
                        $processed_chats[] = $chat_id;
                    }
                }
            }

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

            // Some chat entries were not deleted
            if (count($chats_pending) > 0) {
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
        
        if($clicks > 0 && $clicks!='') {
            
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
                    if ($clicks > 0) {
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
                            if ($clicks > 0) {
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
            $subStrLen = 17;
            $payLoadData = array();
            switch ($chat->type) {
                case '1':   // text                
                    $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent {$chat->clicks} click to you." : 
                                                            trim($results["User"]["name"]) . ' sent a message to you.';

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ? 'clk' : 'chat', 
                                //'Notfication Text' => $message,
                                "chat_message" => ($chat->clicks != null) ? trim($results["User"]["name"]) . " has sent you a click." : 
                                    ($chat->message != '' ? ( strlen($chat->message) > $subStrLen ? ": ".substr($chat->message, 0, $subStrLen)."..." : ": ". $chat->message ) : '')
                            );

                    break;
                case '2':   // image
                    $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent {$chat->clicks} click to you." : 
                                                            trim($results["User"]["name"]) . ' shared a photo.';

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ? 'clk' : 'media', 
                                /*'Notfication Text' => ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent {$chat->clicks} click to you." : 
                                                            trim($results["User"]["name"]) . ' sent a media file to you.',*/
                                "chat_message" => ($chat->clicks != null) ? trim($results["User"]["name"]) . " has sent you a click." : 
                                                            trim($results["User"]["name"]) . ' has sent you a photo.'
                            );

                    break;
                case '3':   // audio
                    $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent {$chat->clicks} click to you." : 
                                                            trim($results["User"]["name"]) . ' shared a audio.';

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ? 'clk' : 'media',  
                                /*'Notfication Text' => ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent {$chat->clicks} click to you." : 
                                                            trim($results["User"]["name"]) . ' sent a audio file to you.',*/
                                "chat_message" => ($chat->clicks != null) ? trim($results["User"]["name"]) . " has sent you a click." : 
                                                            trim($results["User"]["name"]) . ' has sent you a audio.'
                            );

                    break;
                case '4':   // video
                    $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent {$chat->clicks} click to you." : 
                                                            trim($results["User"]["name"]) . ' shared a video.';

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ? 'clk' : 'media', 
                                /*'Notfication Text' => ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent {$chat->clicks} click to you." : 
                                                            trim($results["User"]["name"]) . ' sent a video file to you.',*/
                                "chat_message" => ($chat->clicks != null) ? trim($results["User"]["name"]) . " has sent you a click." : 
                                                            trim($results["User"]["name"]) . ' has sent you a video.'
                            );

                    break;
                case '5':   // cards
                    if($chat->cards !== NULL) {
                        $message = trim($results["User"]["name"]) . ' played a trade card.';
                        $chat_message = '';
                        switch ($chat->cards[5]) {
                            case 'accepted':
                                $chat_message = trim($results["User"]["name"]) . ' has accepted your offer.';
                                break;
                            case 'countered':
                                $chat_message = trim($results["User"]["name"]) . ' has countered your offer.';
                                break;
                            case 'rejected':
                                $chat_message = trim($results["User"]["name"]) . ' has rejected your offer.';
                                break;
                            default:
                                $chat_message = trim($results["User"]["name"]) . ' has played a tarde card.';
                                break;
                        }
                        $payLoadData = array(
                                    'Tp' => 'card', 
                                    //'Notfication Text' => trim($results["User"]["name"]) . ' sent a trade card to you.',
                                    "chat_message" => $chat_message
                                );

                    }
                    break;
                case '7':   // share
                    $message = ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent a request for share a click of you." : 
                                                            trim($results["User"]["name"]) . ' sent a request for share chat of you.';

                    $payLoadData = array(
                                'Tp' => ($chat->clicks != null) ? 'clk' : 'media', 
                                /*'Notfication Text' => ($chat->clicks != null) ? trim($results["User"]["name"]) . " sent a request for share a click of you." : 
                                                            trim($results["User"]["name"]) . ' sent a request for share chat of you.',*/
                                "chat_message" => ($chat->clicks != null) ? trim($results["User"]["name"]) . " has sent a request for share a click of you." : 
                                                            trim($results["User"]["name"]) . ' sent a request for share chat of you.'
                            );

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

                /*App::uses('CakeEmail', 'Network/Email');
                $Email = new CakeEmail();            
                $Email->from(array('me@clickin.com' => 'My Site'));
                $Email->to('saurabh.singh@sourcefuse.com');
                $Email->subject('crone data');
                $Email->emailFormat('html');
                $messageEmail .= "clicks : $clicks :: user_clicks : $user_clicks :: relationshipId :" .  $chat->relationshipId.' :: '.$results['User']['phone_no'];
                $Email->send($messageEmail);*/

                return $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);        
            }
            else
                return 'No message found for send.';
        /*}
        else
            return 'No message found for send.';*/
    }        
    /*
     * fetchchatrecords method
     *
     * @return void
     * @access public
     */

    public function fetchchatrecords() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and phone no, user_token and relationship_id are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->relationship_id):
                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check user is verified
                    if ($data[0]['User']['verified'] !== true) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } else { // Credentials are valid
                        // Check if last chat id is present
                        $last_chat_id = (isset($request_data->last_chat_id)) ? $request_data->last_chat_id : '';

                        // Fetch the chat records, starting from the last chat id, if present
                        $chat_history = $this->Chat->fetchChats($request_data->relationship_id, $last_chat_id);

                        if (count($chat_history) > 0) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Chat history fetched';
                        } else {
                            $success = false;
                            $status = SUCCESS;
                            $message = 'No chat history found';
                        }
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'User not registered.';
                }
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // User Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User token cannot be blank.';
                break;
            // Relationship Id blank in request
            case!empty($request_data) && empty($request_data->relationship_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Relationship Id cannot be blank.';
                break;
            // Parameters not found in request
            case empty($request_data):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
        }

        $out = array(
            "success" => $success,
            "message" => $message
        );

        if ($success) {
            $out['chats'] = $chat_history;
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * this function is used as web service to share chats/newsfeeds.
     * @return \CakeResponse
     */
    public function share() {
        
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        //echo '<pre>';print_r($request_data);
        //exit;
        $returnNewsfeedId = '';
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;
            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->relationship_id) 
                            && !empty($request_data->chat_id) && !empty($request_data->media):

                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check uuid entered is valid
                    if ($data[0]['User']['verified'] === false) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } elseif ($request_data->user_token != $data[0]['User']['user_token']) { // User Token is not valid
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is invalid';
                    } else {

                        $dataArray = $chatDetailArr = array();
                        // save request data..                        
                        $chatDetailArr = $this->Chat->find('first', array(
                                            'fields' => array('_id', 'type', 'clicks', 'relationshipId'), 
                                            'conditions' => array('chatId' => $request_data->chat_id)
                        ));
                        $dataArray['chat_id'] = $chatDetailArr['Chat']['_id'];
                        
                        if(isset($request_data->accepted) && $request_data->accepted == 'yes' || empty($request_data->accepted) 
                                || !isset($request_data->accepted))
                        {
                            //facebook,twitter,googleplus,clickin
                            $dataArray['media'] = explode(',', $request_data->media);
                            $dataArray['fb_access_token'] = isset($request_data->fb_access_token)?$request_data->fb_access_token:'';
                            $dataArray['twitter_access_token'] = isset($request_data->twitter_access_token)?$request_data->twitter_access_token:'';
                            $dataArray['twitter_access_token_secret'] = isset($request_data->twitter_access_token_secret)?$request_data->twitter_access_token_secret:'';
                            $dataArray['googleplus_access_token'] = isset($request_data->googleplus_access_token)?$request_data->googleplus_access_token:'';
                            // status (pending, accepted, rejected)
                            $dataArray['status'] = 'pending';

                            //print_r($dataArray);exit;
                            $chatTypeArr = array('1' => 'Text', '2' => 'Image', '3' => 'Audio', '4' => 'Video', '5' => 'Cards', '6' => 'Location');
                            $otherDataArray = array(
                                'phone_no' => $request_data->phone_no,
                                'relationship_id' => $request_data->relationship_id,
                                'type' => $chatTypeArr[$chatDetailArr['Chat']['type']],
                                'comment' => isset($request_data->comment) ? $request_data->comment : ''
                            );

                            $sharingResponse = $this->Sharing->saveSharing($dataArray, $otherDataArray);

                            if ($sharingResponse) {

                                $sharingId = $this->Sharing->getLastInsertID();

                                // set accepted true if sharing will be done..
                                $sharedChatDetailArr = $this->Chat->find('all', array(
                                                    'fields' => array('_id', 'type', 'sharedMessage'), 
                                                    'conditions' => array('sharedMessage.0' => $request_data->chat_id) // , 'type' => '7'
                                ));                            
                                if(count($sharedChatDetailArr)>0) 
                                {
                                    foreach ($sharedChatDetailArr as &$sMsg) {
                                        $sMsg['Chat']['sharedMessage'][3] = $request_data->accepted;
                                        $this->Chat->save($sMsg);
                                    }
                                }
                                
                                $returnMsg = '';
                                // using facebook component..
                                if($dataArray['fb_access_token']!='')
                                    $returnMsg = $this->Facebook->facebookshare($sharingId); 
                                // using twitter component..
                                if($dataArray['twitter_access_token']!='')
                                    $returnMsg = $this->Twitter->twittershare($sharingId);
                                // using google plus component..
                                if($dataArray['googleplus_access_token']!='')
                                    $this->Googleplus->gplusshare($sharingId);

                                // returning newsfeed id if response have array of newsfeed id..
                                if(is_array($sharingResponse) && isset($sharingResponse['newsfeed_id']))
                                    $returnNewsfeedId = $sharingResponse['newsfeed_id'];
                                
                                // send PN..
                                $pnType = '';
                                $messagePN = 'Your post is on The Feed for your followers to see';
                                if($chatDetailArr['Chat']['clicks'] != null) {
                                    $pnType = 'clk';
                                } else {
                                    switch ($chatDetailArr['Chat']['type']) {
                                        case '1': $pnType = 'chat';break;
                                        case '2': $pnType = 'media'; break;
                                        case '3': $pnType = 'media'; break;
                                        case '4': $pnType = 'media'; break;
                                        case '6': $pnType = 'media'; break;
                                        case '5': $pnType = 'card';break;
                                        default: break;
                                    }
                                }
                                $payLoadData = array(
                                    'Tp' => $pnType, 
                                    //'Notfication Text' => $messagePN,
                                    "chat_message" => $messagePN
                                );
                                
                                // getting clicks and user_clicks..
                                $clicks = 0;
                                $user_clicks = 0;
                                $relation_deleted = 'no';
                                //$messageEmail = "";
                                if(count($data[0]["User"]["relationships"])>0) {
                                    foreach($data[0]["User"]["relationships"] as $urKey => $urVal)
                                    {
                                        if($chatDetailArr['Chat']['relationshipId'] == (string) $data[0]["User"]["relationships"][$urKey]['id'])
                                        {
                                            $clicks = $data[0]["User"]["relationships"][$urKey]['clicks'];
                                            $user_clicks = $data[0]["User"]["relationships"][$urKey]['user_clicks'];
                                            $relation_deleted = isset($data[0]["User"]["relationships"][$urKey]['deleted']) ? 
                                                                    $data[0]["User"]["relationships"][$urKey]['deleted'] : 'no';
                                            break;
                                        }
                                    }
                                } 
                                $payLoadData += array(
                                                        "Rid"  => $chatDetailArr['Chat']['relationshipId'], 
                                                        "phn" => $data[0]['User']['phone_no'], 
                                                        "pid" => (string) $data[0]['User']['_id'], 
                                                        "pname" => $data[0]['User']['name'], 
                                                        //"partner_pic" => $data[0]['User']['user_pic'], 
                                                        "pQBid" => $data[0]['User']['QB_id'], 
                                                        "clk"  => $clicks,
                                                        "uclk" => $user_clicks
                                                    );

                                // Fetch the partner's data
                                $partner_data = $this->User->find(
                                        'first', array(
                                                    'fields' => array('device_token','device_type','name'),
                                                    'conditions' => array(
                                                        'relationships.id' => new MongoId($chatDetailArr['Chat']['relationshipId']),
                                                        'relationships.partner_id' => $data[0]['User']['_id']
                                                    )
                                        )
                                );
                                
                                $device_type = $partner_data['User']['device_type'];
                                $device_token = $partner_data['User']['device_token'];
                                    
                                if($device_type!= '' && $device_token!= '' && $messagePN!= '' && (!isset($relation_deleted) || $relation_deleted != 'yes')) {
                                    $this->Pushnotification->sendMessage($device_type, $device_token, $messagePN, $payLoadData);        
                                }

                                $success = true;
                                $status = SUCCESS;
                                $message = (!isset($returnMsg['exception'])) ? 'Newsfeed has been saved.' : $returnMsg['exception'];
                            } else {
                                $success = false;
                                $status = ERROR;
                                $message = 'There was a problem in processing your request';
                            }
                        }
                        else if($request_data->accepted == 'no')
                        {
                            // set accepted true if sharing will be done..
                            $sharedChatDetailArr = $this->Chat->find('all', array(
                                                'fields' => array('_id', 'type', 'sharedMessage'), 
                                                'conditions' => array('sharedMessage.0' => $request_data->chat_id) // , 'type' => '7'
                            ));                            
                            if(count($sharedChatDetailArr)>0) 
                            {
                                foreach ($sharedChatDetailArr as &$sMsg) {
                                    $sMsg['Chat']['sharedMessage'][3] = $request_data->accepted;
                                    $this->Chat->save($sMsg);
                                }
                            }
                            
                            // send PN..
                            $pnType = '';
                            $messagePN = trim($data[0]['User']['name']).' has kept your post private';
                            if($chatDetailArr['Chat']['clicks'] != null) {
                                $pnType = 'clk';
                            } else {
                                switch ($chatDetailArr['Chat']['type']) {
                                    case '1': $pnType = 'chat';break;
                                    case '2': $pnType = 'media'; break;
                                    case '3': $pnType = 'media'; break;
                                    case '4': $pnType = 'media'; break;
                                    case '6': $pnType = 'media'; break;
                                    case '5': $pnType = 'card';break;
                                    default: break;
                                }
                            }
                            $payLoadData = array(
                                'Tp' => $pnType, 
                                //'Notfication Text' => $messagePN,
                                "chat_message" => $messagePN
                            );

                            // getting clicks and user_clicks..
                            $clicks = 0;
                            $user_clicks = 0;
                            $relation_deleted = 'no';
                            //$messageEmail = "";
                            if(count($data[0]["User"]["relationships"])>0) {
                                foreach($data[0]["User"]["relationships"] as $urKey => $urVal)
                                {
                                    if($chatDetailArr['Chat']['relationshipId'] == (string) $data[0]["User"]["relationships"][$urKey]['id'])
                                    {
                                        $clicks = $data[0]["User"]["relationships"][$urKey]['clicks'];
                                        $user_clicks = $data[0]["User"]["relationships"][$urKey]['user_clicks'];
                                        $relation_deleted = isset($data[0]["User"]["relationships"][$urKey]['deleted']) ? 
                                                                $data[0]["User"]["relationships"][$urKey]['deleted'] : 'no';
                                        break;
                                    }
                                }
                            } 
                            $payLoadData += array(
                                                    "Rid"  => $chatDetailArr['Chat']['relationshipId'], 
                                                    "phn" => $data[0]['User']['phone_no'], 
                                                    "pid" => (string) $data[0]['User']['_id'], 
                                                    "pname" => $data[0]['User']['name'], 
                                                    //"partner_pic" => $data[0]['User']['user_pic'], 
                                                    "pQBid" => $data[0]['User']['QB_id'], 
                                                    "clk"  => $clicks,
                                                    "uclk" => $user_clicks
                                                );

                            // Fetch the partner's data
                            $partner_data = $this->User->find(
                                    'first', array(
                                                'fields' => array('device_token','device_type','name'),
                                                'conditions' => array(
                                                    'relationships.id' => new MongoId($chatDetailArr['Chat']['relationshipId']),
                                                    'relationships.partner_id' => $data[0]['User']['_id']
                                                )
                                    )
                            );

                            $device_type = $partner_data['User']['device_type'];
                            $device_token = $partner_data['User']['device_token'];

                            if($device_type!= '' && $device_token!= '' && $messagePN!= '' && (!isset($relation_deleted) || $relation_deleted != 'yes')) {
                                $this->Pushnotification->sendMessage($device_type, $device_token, $messagePN, $payLoadData);        
                            }
                            
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Sharing has been declined successfully.';
                        }
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'Phone no. not registered.';
                }
                break;
            // User Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User Token cannot be blank.';
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // relationship id blank in request
            case!empty($request_data) && empty($request_data->relationship_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Relationship id cannot be blank.';
                break;
            // chat id blank in request
            case!empty($request_data) && empty($request_data->chat_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Chat id cannot be blank.';
                break;
            // media blank in request
            case!empty($request_data) && empty($request_data->media):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Media cannot be blank.';
                break;
            // Parameters not found in request
            case empty($request_data):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
        }

        $out = array(
            "success" => $success,
            "message" => $message,
            "newsfeed_id" => $returnNewsfeedId
        );

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * savecards method
     * this function is used to save card..
     * 
     * @return \CakeResponse
     */
    public function savecards() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        //echo '<pre>';print_r($request_data);
        //exit;
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;
            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->title):

                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check uuid entered is valid
                    if ($data[0]['User']['verified'] === false) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } elseif ($request_data->user_token != $data[0]['User']['user_token']) { // User Token is not valid
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is invalid';
                    } else {
                    
                        // add save cards detail here...
                        $dataArray['user_id'] = $data[0]['User']['_id'];
                        $dataArray['title'] =  $request_data->title;
                        $dataArray['category'] = (isset($request_data->category) && $request_data->category!='') ? explode(',',$request_data->category):array('Custom');
                        
                        // save cards..
                        if ($this->Card->saveCard($dataArray)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Card has been saved.';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'There was a problem in processing your request';
                        }                      
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'Phone no. not registered.';
                }
                break;
            // User Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User Token cannot be blank.';
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // title blank in request
            case!empty($request_data) && empty($request_data->title):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Title cannot be blank.';
                break;
        }

        $out = array(
            "success" => $success,
            "message" => $message
        );

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }
    
    /**
     * fetchcards method
     * this function is used to fetch cards details...
     * 
     * @return \CakeResponse
     */
    public function fetchcards() {
        
        $success = FALSE;
        switch (true) {
            // When request is not made using GET method
            case!$this->request->is('get') :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Check the user token in headers is set
            case $this->request->header('User-Token') === null :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User token is required.';
                break;
            // Check the phone no. in headers is set
            case $this->request->header('Phone-No') === null :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. is required.';
                break;
            // Request is valid and phone no. and user token are present
            case $this->request->header('User-Token') !== null && $this->request->header('Phone-No') !== null:
                // Fetch the logged in user's user token and phone no. from header
                $request_user_token = $this->request->header('User-Token');
                $request_phone_no = $this->request->header('Phone-No');

                // Check if phone no exists
                $data = $this->User->findUser($request_phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check user is verified
                    if ($data[0]['User']['verified'] !== TRUE) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } elseif ($request_user_token != $data[0]['User']['user_token']) { // Wrong password
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is invalid';
                    } else { // Credentials are valid
                        
                        $categoryArr = $this->Category->find('all', array(
                                                    'conditions' => array('active' => 'yes'),
                                                    'order' => array('order' => 1) ) );
                        $cardArr = $this->Card->fetchCards($data[0]['User']['_id']);
                                                
                        $success = true;
                        $status = SUCCESS;
                        $message = 'Card details found.';
                    }
                }
                // Return false if record not found..
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'User not registered.';
                }
                break;
        }

        $out = array(
            "success" => $success,
            "message" => $message
        );

        // Send phone nos list if success
        if ($success) {
            $out['categories'] = (isset($categoryArr)) ? $categoryArr : array();
            $out['cards'] = (isset($cardArr)) ? $cardArr : array();
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }    
    
    /**
     * getunreadmessagecount method
     * this function is used to get unread messages count of a relationship.
     * 
     * @return \CakeResponse
     * @access public
     */
    public function getunreadmessagecount()
    {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        $chatCountArray = array();
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and phone no, user_token and relationship_id are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token): // && !empty($request_data->relationship_id):
                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check user is verified
                    if ($data[0]['User']['verified'] !== true) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } else { // Credentials are valid
                        
                        $relationship_id = isset($request_data->relationship_id) ? $request_data->relationship_id :'';
                        // Fetch the chat records, starting from the last chat id, if present
                        $chatCountArray = $this->Chat->fetchUnreadMessageCount($data, $relationship_id );

                        if (count($chatCountArray) > 0) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Chat message(s) found.';
                        } else {
                            $success = false;
                            $status = SUCCESS;
                            $message = 'chat message(s) not found.';
                        }
                        
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'User not registered.';
                }
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // User Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User token cannot be blank.';
                break;
            /*// Relationship Id blank in request
            case!empty($request_data) && empty($request_data->relationship_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Relationship Id cannot be blank.';
                break;*/
            // Parameters not found in request
            case empty($request_data):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
        }

        $out = array(
            "success" => $success,
            "message" => $message,
            "chatCountArray" => $chatCountArray
        );
            
        
        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }
    
    /**
     * resetunreadmessagecount method
     * this function is used to reset unread message count to zero..
     * 
     * @return \CakeResponse
     */
    public function resetunreadmessagecount()
    {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and phone no, user_token and relationship_id are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->relationship_id) 
                && !empty($request_data->last_chat_id):
                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check user is verified
                    if ($data[0]['User']['verified'] !== true) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } else { // Credentials are valid
                        
                        if ($this->Chat->resetUnreadMessageCount($data, $request_data->relationship_id , $request_data->last_chat_id)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Unread message count successfully reset.';
                        } else {
                            $success = false;
                            $status = SUCCESS;
                            $message = 'Unread message count did not successfully reset.';
                        }                        
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'User not registered.';
                }
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // User Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User token cannot be blank.';
                break;
            // Relationship Id blank in request
            case!empty($request_data) && empty($request_data->relationship_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Relationship Id cannot be blank.';
                break;
            // Last Chat Id blank in request
            case!empty($request_data) && empty($request_data->last_chat_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Last Chat Id cannot be blank.';
                break;
            // Parameters not found in request
            case empty($request_data):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
        }

        $out = array(
            "success" => $success,
            "message" => $message
        );
            
        
        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * resetbadgecount method
     * this function is used to reset badge count to zero..
     * 
     * @return \CakeResponse
     */
    public function resetbadgecount()
    {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and phone no, user_token are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token):
                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check user is verified
                    if ($data[0]['User']['verified'] !== true) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } else { // Credentials are valid
                        
                        if ($this->Chat->resetBadgeCount($data)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Badge count successfully reset.';
                        } else {
                            $success = false;
                            $status = SUCCESS;
                            $message = 'Badge count did not successfully reset.';
                        }                        
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'User not registered.';
                }
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // User Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User token cannot be blank.';
                break;
            // Parameters not found in request
            case empty($request_data):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
        }

        $out = array(
            "success" => $success,
            "message" => $message
        );
                    
        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * this function is used for accept or reject sharing of chat on social media..
     * @return \CakeResponse
     */
    public function sharingaction() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        //echo '<pre>';print_r($request_data);
        //exit;
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;

            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->newsfeed_id) && !empty($request_data->phone_no) && !empty($request_data->user_token) 
                        && !empty($request_data->newsfeed_status):

                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check uuid entered is valid
                    if ($data[0]['User']['verified'] === false) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } elseif ($request_data->user_token != $data[0]['User']['user_token']) { // User Token is not valid
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is invalid';
                    } else {

                        // sharing on social media and clickin..
                        if ($request_data->newsfeed_status == 'accepted') {
                            $params = array('conditions' => array('_id' => new MongoId($request_data->newsfeed_id)));
                            $newsfeedDetailArr = $this->Newsfeeds->find('first', $params);

                            if (count($newsfeedDetailArr) > 0) {
                                $mediaArr = $newsfeedDetailArr['Newsfeeds']['media'];
                                // if array have facebook as value
                                if (in_array('facebook', $mediaArr)) {
                                    $this->facebookshare();
                                }
                                // if array have twitter as value
                                if (in_array('twitter', $mediaArr)) {
                                    $this->twittershare();
                                }
                                // if array have googleplus as value
                                if (in_array('googleplus', $mediaArr)) {
                                    $this->gplusshare();
                                }
                            }
                        }

                        // accept or reject sharing of newsfeed..
                        $dataArray = array('_id' => new MongoId($request_data->newsfeed_id), 'status' => $request_data->newsfeed_status);

                        if ($this->Newsfeeds->save($dataArray)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Newsfeed has been shared.';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'Newsfeed has not been shared.';
                        }
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'Phone no. not registered.';
                }

                break;

            // User Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User Token cannot be blank.';
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // user id blank in request
            case!empty($request_data) && empty($request_data->newsfeed_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'newsfeed id cannot be blank.';
                break;
            // newsfeed status blank in request
            case!empty($request_data) && empty($request_data->newsfeed_status):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'newsfeed status cannot be blank.';
                break;
            // Parameters not found in request
            case empty($request_data):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
        }

        $out = array(
            "success" => $success,
            "message" => $message
        );

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }
    
    public function dshare()
    {
        $sharingId = "52f8df35038c70cb4d000000";
        $this->Googleplus->gplusshare($sharingId);exit;
    }
    
    /*public function s3upload()
    {
        $contents = $this->CakeS3->listBucketContents();
        $response = $this->CakeS3->putObject('/home/saurabhsingh/Desktop/join.me.lnk', 'join.me.lnk', $this->CakeS3->permission('public_read_write'));
        print_r($response['url']);
        $response = $this->CakeS3->putObject('/home/saurabhsingh/Desktop/webservices', 'webservices', $this->CakeS3->permission('public_read_write'));
        print_r($response['url']);
        exit;
    }*/
}
