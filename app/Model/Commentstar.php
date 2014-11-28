<?php

App::import('Component', 'Pushnotification');

class Commentstar extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';
    var $useTable = 'comments_stars';
   
    /*  _id
        chat_id (Mongo Chat Id)
        newsfeed_id
        type (comment or star)
        comment (comment text)
        user_id
        user_name
        user_pic
        created_on
    */
    
    /**
     * saveCommentStar method
     * this function is used to save comment/star detail and update chat & newsfeed collections for comment_count / star_count..
     * 
     * @param array $dataArray
     * @param array $willSendNotifications
     * @access public
     */
    public function saveCommentStar($dataArray, $willSendNotifications = 1)
    {
        $this->Pushnotification = new PushnotificationComponent(new ComponentCollection());
        // Generate new id for sharing
        $insert_id = new MongoId();  
        
        $dataArray = array_merge($dataArray, array(
            '_id' => $insert_id,
        ));
        //print_r($dataArray);exit;
       
        // save data in commentstar collection..
        if($this->save($dataArray))
        {
            $Chat = ClassRegistry::init('Chat');
            $Newsfeeds = ClassRegistry::init('Newsfeeds');
            
            // update chat collection for comment_count / star_count..
            $chatDetail = $Chat->find('first', array('conditions' => array('_id' => new MongoId($dataArray['chat_id']))));
            // increase comment / star count.
            if($dataArray['type'] == 'comment')
                $chatDetail['Chat']['comments_count'] = isset($chatDetail['Chat']['comments_count']) ? $chatDetail['Chat']['comments_count'] + 1 : 1;
            elseif($dataArray['type'] == 'star')
                $chatDetail['Chat']['stars_count'] = isset($chatDetail['Chat']['stars_count']) ? $chatDetail['Chat']['stars_count'] + 1 : 1;
            
            $chatDetail['Chat']['_id'] = new MongoId($dataArray['chat_id']);
            
            // save chat related detail..
            if($Chat->save($chatDetail))
            {
                // update newsfeed collection for comment_count / star_count..
                $newsfeedDetail = $Newsfeeds->find('first', array('conditions' => array('_id' => new MongoId($dataArray['newsfeed_id']))));

                // increase comment / star count.
                if($dataArray['type'] == 'comment')
                    $newsfeedDetail['Newsfeeds']['comments_count'] = isset($newsfeedDetail['Newsfeeds']['comments_count']) ? 
                            $newsfeedDetail['Newsfeeds']['comments_count'] + 1 : 1;
                elseif($dataArray['type'] == 'star')
                    $newsfeedDetail['Newsfeeds']['stars_count']  = isset($newsfeedDetail['Newsfeeds']['stars_count']) ? 
                            $newsfeedDetail['Newsfeeds']['stars_count'] + 1 : 1;

                $newsfeedDetail['Newsfeeds']['_id'] = new MongoId($dataArray['newsfeed_id']);

                // update newsfeed details..
                if($Newsfeeds->save($newsfeedDetail)) {
                    
                    $chat_sender_id = $chatDetail['Chat']['userId'];
                    $chat_relationship_id = $chatDetail['Chat']['relationshipId'];
                    
                    $User = ClassRegistry::init('User');
                    // Find records matching relationship for user..
                    $params = array(
                            'fields' => array('relationships','name'),
                            'conditions' => array('_id' => new MongoId($chat_sender_id),'relationships.id' => new MongoId($chat_relationship_id))
                    );
                    $results = $User->find('first', $params);
                    $relationship_user_id = 0;
                    $relationship_user_name = '';
                    if(count($results)>0)
                    {
                        foreach($results["User"]["relationships"] as $urKey => $urVal)
                        {
                            if($chat_relationship_id == (string) $results["User"]["relationships"][$urKey]['id'])
                            {
                                $relationship_user_id = $results["User"]["relationships"][$urKey]['partner_id'];
                                $relationship_user_name = $results["User"]["relationships"][$urKey]['partner_name'];
                                break;
                            }
                        }
                    }
                    // get chat sender name..
                    $chat_sender_name = $results["User"]['name'];                    
                    
                    $shared_by_user_id = $newsfeedDetail['Newsfeeds']['user_id'];
                    
                    $Notification = ClassRegistry::init('Notification');
                    
                    // making notification messages for sender user and his releted users..
                    $notification_msg_to_related_user = ''; 
                    $notification_msg_to_sender_user = ''; 
                    
                    if($willSendNotifications == 1)
                    {
                        $message = '';
                        $payLoadData = array(); 

                        $isCommentOrStarText = ($dataArray['type'] == 'comment') ? 'commented' : 'starred';
                        switch ($chatDetail['Chat']['type']) {
                            case '1':   // text                

                                // checking that sharing user is same as that person who sent chat to related user.
                                /*if($chat_sender_id == $shared_by_user_id)
                                {
                                    // to sender
                                    if($dataArray['user_id'] != $chat_sender_id) {
                                        $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click." : trim($dataArray['user_name']) . " $isCommentOrStarText on your chat.";
                                    }

                                    // to related user
                                    if($dataArray['user_id'] != $relationship_user_id) {
                                        $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on click shared by {$chat_sender_name}." : trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on chat shared by {$chat_sender_name}.";                                
                                    }
                                }
                                // checking that if sharing user is with relation of chat sender user..
                                else if($relationship_user_id == $shared_by_user_id)
                                {                            
                                    // to sender
                                    if($dataArray['user_id'] != $chat_sender_id) {
                                        $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click shared by {$relationship_user_name}." : trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your chat shared by {$relationship_user_name}.";
                                    }
                                    // to related user
                                    if($dataArray['user_id'] != $relationship_user_id) {
                                        $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                       " $isCommentOrStarText on your click." : trim($dataArray['user_name']) . " $isCommentOrStarText on your chat.";
                                    }
                                }*/
                                $notification_msg_to_sender_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                $notification_msg_to_related_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                break;
                            case '2':   // image

                                // checking that sharing user is same as that person who sent chat to related user.
                                /*if($chat_sender_id == $shared_by_user_id)
                                {
                                    // to sender
                                    if($dataArray['user_id'] != $chat_sender_id) {
                                        $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click shared by you." : trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on your picture shared by you.";
                                    }

                                    // to related user
                                    if($dataArray['user_id'] != $relationship_user_id) {
                                        $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on click shared by {$chat_sender_name}." : trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on picture shared by {$chat_sender_name}.";                                
                                    }
                                }
                                // checking that if sharing user is with relation of chat sender user..
                                else if($relationship_user_id == $shared_by_user_id)
                                {                            
                                    // to sender
                                    if($dataArray['user_id'] != $chat_sender_id) {
                                        $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click shared by {$relationship_user_name}." : trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your picture shared by {$relationship_user_name}.";
                                    }
                                    // to related user
                                    if($dataArray['user_id'] != $relationship_user_id) {
                                        $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on click shared by you." : trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on picture shared by you.";
                                    }
                                }*/
                                $notification_msg_to_sender_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                $notification_msg_to_related_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                break;
                            case '3':   // audio

                                // checking that sharing user is same as that person who sent chat to related user.
                                /*if($chat_sender_id == $shared_by_user_id)
                                {
                                    // to sender
                                    if($dataArray['user_id'] != $chat_sender_id) {
                                        $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click shared by you." : trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on your audio shared by you.";
                                    }

                                    // to related user
                                    if($dataArray['user_id'] != $relationship_user_id) {
                                        $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on click shared by {$chat_sender_name}." : trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on audio shared by {$chat_sender_name}.";                                
                                    }
                                }
                                // checking that if sharing user is with relation of chat sender user..
                                else if($relationship_user_id == $shared_by_user_id)
                                {                            
                                    // to sender
                                    if($dataArray['user_id'] != $chat_sender_id) {
                                        $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click shared by {$relationship_user_name}." : trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your audio shared by {$relationship_user_name}.";
                                    }
                                    // to related user
                                    if($dataArray['user_id'] != $relationship_user_id) {
                                        $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click." : trim($dataArray['user_name']) . " $isCommentOrStarText on your audio.";
                                    }
                                }*/
                                $notification_msg_to_sender_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                $notification_msg_to_related_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                break;
                            case '4':   // video

                                //echo $chat_sender_id . '==' . $shared_by_user_id . '==='.$relationship_user_id . '---'.$dataArray['user_id'];exit;
                                // checking that sharing user is same as that person who sent chat to related user.
                                /*if($chat_sender_id == $shared_by_user_id)
                                {
                                    // to sender
                                    if($dataArray['user_id'] != $chat_sender_id) {
                                        $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click." : trim($dataArray['user_name']) . " $isCommentOrStarText on your video.";				    
                                    }

                                    // to related user
                                    if($dataArray['user_id'] != $relationship_user_id) {
                                        $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on click shared by {$chat_sender_name}." : trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on video shared by {$chat_sender_name}.";                                
                                    }
                                }
                                // checking that if sharing user is with relation of chat sender user..
                                else if($relationship_user_id == $shared_by_user_id)
                                {                            
                                    // to sender
                                    if($dataArray['user_id'] != $chat_sender_id) {
                                        $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click shared by {$relationship_user_name}." : trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your video shared by {$relationship_user_name}.";
                                    }
                                    // to related user
                                    if($dataArray['user_id'] != $relationship_user_id) {
                                        $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your click." : trim($dataArray['user_name']) . " $isCommentOrStarText on your video.";                                    
                                    }
                                }*/
                                $notification_msg_to_sender_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                $notification_msg_to_related_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                break;
                            case '5':   // cards

                                if($chatDetail['Chat']['cards'] !== NULL && $chatDetail['Chat']['cards'][5] == 'accepted')
                                {    
                                    // checking that sharing user is same as that person who sent chat to related user.
                                    /*if($chat_sender_id == $shared_by_user_id)
                                    {
                                        // to sender
                                        if($dataArray['user_id'] != $chat_sender_id) {
                                            $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on your click." : trim($dataArray['user_name']) . " $isCommentOrStarText on your trade card.";
                                        }

                                        // to related user
                                        if($dataArray['user_id'] != $relationship_user_id) {
                                            $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on click shared by {$chat_sender_name}." : trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on trade card shared by {$chat_sender_name}.";                                
                                        }
                                    }
                                    // checking that if sharing user is with relation of chat sender user..
                                    else if($relationship_user_id == $shared_by_user_id)
                                    {                            
                                        // to sender
                                        if($dataArray['user_id'] != $chat_sender_id) {
                                            $notification_msg_to_sender_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on your click shared by {$relationship_user_name}." : trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on your trade card shared by {$relationship_user_name}.";
                                        }
                                        // to related user
                                        if($dataArray['user_id'] != $relationship_user_id) {
                                            $notification_msg_to_related_user = ($chatDetail['Chat']['clicks'] != null) ? trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on your click." : trim($dataArray['user_name']) . " $isCommentOrStarText on your trade card.";
                                        }
                                    }*/
                                    $notification_msg_to_sender_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                    $notification_msg_to_related_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                            " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                }                            
                                break;
                            case '6':   // location    
                                $notification_msg_to_sender_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                                $notification_msg_to_related_user = ($isCommentOrStarText == 'commented') ? trim($dataArray['user_name']) . 
                                        " $isCommentOrStarText on your post" : trim($dataArray['user_name']) . " $isCommentOrStarText your post";
                            default:
                                break;
                        }

                        // send mailing for debugging..
                        /*App::uses('CakeEmail', 'Network/Email');
                        $Email = new CakeEmail();            
                        $Email->from(array('me@clickin.com' => 'Clickin'));
                        $Email->to('saurabh.singh@sourcefuse.com');
                        $Email->subject('crone data');
                        $Email->emailFormat('html');
                        $messageEmail = "Newsfeed_id :: ".$dataArray['newsfeed_id']." :: chat_sender_id :" . $chat_sender_id
                                ." :: loggedin : data_user_id :" . $dataArray['user_id'] ."  :: relationship_user_id : $relationship_user_id " 
                                ." :: notification_msg_to_sender_user : $notification_msg_to_sender_user";
                        $Email->send($messageEmail);*/                    
                        
                        // Saving the new notification for the chat sender's relation user..
                        if($relationship_user_id!=0 && $notification_msg_to_related_user!='' && trim($dataArray['user_id']) != trim($relationship_user_id))
                        {
                            // increase unread notification count..
                            $params = array( 
                                    'fields' => array('unread_notifications_count','device_token','device_type','is_enable_push_notification'),
                                    'conditions' => array('_id' => new MongoId($relationship_user_id)) 
                            );
                            $results = $User->find('first', $params);
                            $relationship_user_data['_id'] = $results['User']['_id'];
                            $relationship_user_data['unread_notifications_count'] = isset($results['User']['unread_notifications_count']) ? 
                                                                                    $results['User']['unread_notifications_count'] + 1 : 1;
                            $User->save($relationship_user_data);

                            // send push notifications...
                            /*if(isset($results['User']['is_enable_push_notification']) && $results['User']['is_enable_push_notification'] == 'yes' 
                                    || !isset($results['User']['is_enable_push_notification']))
                            {*/
                                $device_type = $results['User']['device_type'];
                                $device_token = $results['User']['device_token'];

                                $payLoadData = array(
                                    'Tp' => $dataArray['type'] == 'comment' ? 'cmt' : 'str', 
                                    //'Notfication Text' => $notification_msg_to_related_user,
                                    'chat_message' => $notification_msg_to_related_user,
                                    "Nid" => $dataArray['newsfeed_id']
                                );
                                if($device_type!= '' && $device_token!= '' && $notification_msg_to_related_user!= '') {
                                    $this->Pushnotification->sendMessage($device_type, $device_token, $notification_msg_to_related_user, $payLoadData); 
                                }
                            //}
                            // send notification..
                            $notificationArr = $Notification->create();
                            $notificationArr['Notification']['_id'] = new MongoId();
                            $notificationArr['Notification']['user_id'] = $relationship_user_id;
                            $notificationArr['Notification']['notification_msg'] = $notification_msg_to_related_user;
                            $notificationArr['Notification']['newsfeed_id'] = $dataArray['newsfeed_id'];
                            $notificationArr['Notification']['type'] = $dataArray['type'];
                            $notificationArr['Notification']['chat_id'] = $newsfeedDetail['Newsfeeds']['chat_id'];
                            $notificationArr['Notification']['read'] = false;
                            if($notification_msg_to_sender_user!='')
                                $Notification->save($notificationArr);
                            else
                                return $Notification->save($notificationArr);;
                        }
                        
                        /*App::uses('CakeEmail', 'Network/Email');
                        $Email = new CakeEmail();            
                        $Email->from(array('me@clickin.com' => 'Clickin'));
                        $Email->to('saurabh.singh@sourcefuse.com');
                        $Email->subject('crone data');
                        $Email->emailFormat('html');
                        $messageEmail = "notification_msg_to_sender_user :: ".$notification_msg_to_sender_user;
                        $Email->send($messageEmail);*/
                        
                        // Saving the new notification for the chat sender user..
                        if($chat_sender_id != '' && $notification_msg_to_sender_user!='' && trim($dataArray['user_id']) != trim($chat_sender_id))
                        {
                            // increase unread notification count..
                            $params = array( 
                                    'fields' => array('unread_notifications_count','device_token','device_type','is_enable_push_notification'),
                                    'conditions' => array('_id' => new MongoId($chat_sender_id)) 
                            );
                            $results = $User->find('first', $params);
                            $chat_sender_data['_id'] = $results['User']['_id'];
                            $chat_sender_data['unread_notifications_count'] = isset($results['User']['unread_notifications_count']) ? 
                                                                                    $results['User']['unread_notifications_count'] + 1 : 1;
                            $User->save($chat_sender_data);

                            // send push notifications...
                            /*if(isset($results['User']['is_enable_push_notification']) && $results['User']['is_enable_push_notification'] == 'yes' 
                                    || !isset($results['User']['is_enable_push_notification']))
                            {*/
                                $device_type = $results['User']['device_type'];
                                $device_token = $results['User']['device_token'];

                                $payLoadData = array(
                                    'Tp' => $dataArray['type'] == 'comment' ? 'cmt' : 'str', 
                                    //'Notfication Text' => $notification_msg_to_sender_user,
                                    'chat_message' => $notification_msg_to_sender_user,
                                    "Nid" => $dataArray['newsfeed_id']
                                );
                                if($device_type!= '' && $device_token!= '' && $notification_msg_to_sender_user!= '') {
                                    $this->Pushnotification->sendMessage($device_type, $device_token, $notification_msg_to_sender_user, $payLoadData); 
                                }
                            //}
                            
                            // send notification..
                            $notificationArr = $Notification->create();
                            $notificationArr['Notification']['_id'] = new MongoId();
                            $notificationArr['Notification']['user_id'] = $chat_sender_id;
                            $notificationArr['Notification']['notification_msg'] = $notification_msg_to_sender_user;
                            $notificationArr['Notification']['newsfeed_id'] = $dataArray['newsfeed_id'];
                            $notificationArr['Notification']['type'] = $dataArray['type'];
                            $notificationArr['Notification']['chat_id'] = $newsfeedDetail['Newsfeeds']['chat_id'];
                            $notificationArr['Notification']['read'] = false;
                            return $Notification->save($notificationArr); 
                        }

                        /*App::uses('CakeEmail', 'Network/Email');
                        $Email = new CakeEmail();            
                        $Email->from(array('me@clickin.com' => 'Clickin'));
                        $Email->to('saurabh.singh@sourcefuse.com');
                        $Email->subject('crone data');
                        $Email->emailFormat('html');
                        $messageEmail = "notification_msg_to_related_user :: ".$notification_msg_to_related_user;
                        $Email->send($messageEmail);*/
                        
                        // send Notifications and Push notifications to followers of newsfeed..
                        /*$followerUsers = $newsfeedDetail['Newsfeeds']['follower_user_id'];
                        if(count($followerUsers)>0)
                        {
                            foreach ($followerUsers as $fUserArr) {

                                // increase unread notification count..
                                $params = array( 
                                                'fields' => array('unread_notifications_count','device_token','device_type','is_enable_push_notification'),
                                                'conditions' => array('_id' => new MongoId($fUserArr['user_id'])) 
                                );
                                $results = $User->find('first', $params);
                                $follower_data['_id'] = $results['User']['_id'];
                                $follower_data['unread_notifications_count'] = isset($results['User']['unread_notifications_count']) ? 
                                                                                        $results['User']['unread_notifications_count'] + 1 : 1;
                                $User->save($follower_data);

                                // send push notifications...
                                if(isset($results['User']['is_enable_push_notification']) && $results['User']['is_enable_push_notification'] == 'yes' 
                                        || !isset($results['User']['is_enable_push_notification']))
                                {
                                    $device_type = $results['User']['device_type'];
                                    $device_token = $results['User']['device_token'];

                                    $payLoadData = array(
                                        'Tp' => $dataArray['type'] == 'comment' ? 'cmt' : 'str', 
                                        //'Notfication Text' => $notification_msg_to_sender_user,
                                        'chat_message' => $notification_msg_to_sender_user,
                                        "Nid" => $dataArray['newsfeed_id']
                                    );
                                    if($device_type!= '' && $device_token!= '' && $notification_msg_to_sender_user!= '') {
                                        $this->Pushnotification->sendMessage($device_type, $device_token, $notification_msg_to_sender_user, $payLoadData); 
                                    }
                                }
                         
                                // send notification..
                                $notificationArr = $Notification->create();
                                $notificationArr['Notification']['_id'] = new MongoId();
                                $notificationArr['Notification']['user_id'] = $fUserArr['user_id'];
                                $notificationArr['Notification']['notification_msg'] = $notification_msg_to_sender_user;
                                $notificationArr['Notification']['newsfeed_id'] = $dataArray['newsfeed_id'];
                                $notificationArr['Notification']['type'] = $dataArray['type'];
                                $notificationArr['Notification']['chat_id'] = $newsfeedDetail['Newsfeeds']['chat_id'];
                                $notificationArr['Notification']['read'] = false;
                                $this->Notification->save($notificationArr);                
                            }
                        }*/
                    }
                    else
                        return TRUE;
                }
                else
                    return FALSE;
            }
            else
                return FALSE;            
        }
        else
            return FALSE;
    }
    
    /**
     * fetchcommentstars method
     * this function is used to fetch comment / star by newsfeed id, type and last id..
     * @param int $newsfeed_id
     * @param int $type -> comment or star
     * @param int $last_id
     * @return array
     * @access public
     */
    public function fetchcommentstars($newsfeed_id, $type, $last_id) {

        $conditions = array('newsfeed_id' => $newsfeed_id, 'type' => $type);
        $order = array('_id' => 1);

        // Check for ids greater than the last newsfeed id, if present
        if ($last_id != '') {
            // Set the params to fetch the _id of the newsfeed id in the request
            $record_params = array(
                'fields' => array('_id'),
                'conditions' => array('_id' => new MongoId($last_id))
            );

            // Find last newsfeed id's table id
            $last_record = $this->find('first', $record_params);

            // Set the condition to fetch records earlier than last newsfeed id
            $conditions['_id']['$lt'] = $last_record['Commentstar']['_id'];
        }

        $params = array(
            'conditions' => $conditions,
            'order' => $order,
            'limit' => 25,
            'page' => 1
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);

        return $results;
    }
}
