<?php

class Newsfeeds extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';

    /* _id
      user_id (_id from Users collection)
      newsfeed_msg
      follower_user_id - Format - {follower1, follower2, follower3} (_id from Users collection)
      chat_id (details for chat entry to be fetched from chats collection)
      created
      modified
      read : 0/1
     */

    /**
     * saveNewsfeed method
     * this function is used to save newsfeed with follower list of that user.
     * @param array $dataArray
     * @param array $otherDataArray
     * @access public
     */
    public function saveNewsfeed($dataArray, $otherDataArray) {
        
        $this->Pushnotification = new PushnotificationComponent(new ComponentCollection());
        
        // Generate new id for newsfeed
        $newsfeed_id = new MongoId();
        $User = ClassRegistry::init('User');

        $followerDataArr = array();
        $followerArr = array();

        $count = 0;
        $rCount = 0;

        // find follower_user_id by user_id
        $followerArray = $User->getAllFollower($dataArray['user_id']);
        if (count($followerArray) > 0 && isset($followerArray['User']['follower'])) {
            foreach ($followerArray['User']['follower'] as $fArr) {
                if($fArr['accepted'] === true) {
                    $followerDataArr[$count]['user_id'] = $fArr['follower_id'];
                    $count++;
                }
            }
            $followerDataArr = array_unique($followerDataArr, SORT_REGULAR);

            foreach ($followerDataArr as $fArr) {
                $followerArr[$rCount]['_id'] = new MongoId();
                $followerArr[$rCount]['user_id'] = $fArr['user_id'];
                $rCount++;
            }
        }

        // find follower_user_id by partner_id
        $partnerFollowerArray = $User->getAllFollower($otherDataArray['partner_id']);
        if (count($partnerFollowerArray) > 0 && isset($partnerFollowerArray['User']['follower'])) {
            foreach ($partnerFollowerArray['User']['follower'] as $fArr) {
                if($fArr['accepted'] === true) {
                    $followerDataArr[$count]['user_id'] = $fArr['follower_id'];
                    $count++;
                }
            }
            $followerDataArr = array_unique($followerDataArr, SORT_REGULAR);

            foreach ($followerDataArr as $fArr) {
                $followerArr[$rCount]['_id'] = new MongoId();
                $followerArr[$rCount]['user_id'] = $fArr['user_id'];
                $rCount++;
            }
        }
        
        // add main user and partner user in followerArr list to show him..
        $followerArr[$rCount]['_id'] = new MongoId();
        $followerArr[$rCount]['user_id'] = $dataArray['user_id'];
        $rCount++;
        $followerArr[$rCount]['_id'] = new MongoId();
        $followerArr[$rCount]['user_id'] = $otherDataArray['partner_id'];
        $rCount++;
        //print_r($dataArray);
        //print_r($otherDataArray);
        //print_r($followerArr);//exit;
        $dataArray = array_merge($dataArray, array(
            '_id' => $newsfeed_id,
            'read' => '1'
        ));
        $dataArray['follower_user_id'] = $followerArr;

        // save entry in notifications..
        $notification_msg = $otherDataArray['type'] == 'Cards' ? trim($otherDataArray['name']).' and '. trim($otherDataArray['partner_name']) . ' just clicked! Check out what happened on The Feed' // ' have just shared something!' 
                : trim($otherDataArray['name']) .' and '. trim($otherDataArray['partner_name']) . ' just clicked! Check out what happened on The Feed'; //  . ' have just shared something!' ; //' have shared a click to The Feed';
        $dataArray['newsfeed_msg'] = $notification_msg; //$otherDataArray['name'] . ' sent ' . $otherDataArray['type'] . '.';
        
        // send notifications to all followers..
        $Notification = ClassRegistry::init('Notification');
        
        if(count($followerArr)>0) {
            $alreadySentToFollowerArr = array();
            $sent = 0;
            foreach($followerArr as $fArr) {                
                if(!in_array($fArr['user_id'], $alreadySentToFollowerArr) && $fArr['user_id'] != $dataArray['user_id'] 
                            && $fArr['user_id'] != $otherDataArray['partner_id']) 
                {
                    // increase unread notification count..
                    $params = array( 
                                    'fields' => array('unread_notifications_count','device_token','device_type','is_enable_push_notification'),
                                    'conditions' => array('_id' => new MongoId($fArr['user_id'])) 
                    );
                    $results = $User->find('first', $params);
                    $follower_data['_id'] = $results['User']['_id'];
                    $follower_data['unread_notifications_count'] = isset($results['User']['unread_notifications_count']) ? 
                                                                            $results['User']['unread_notifications_count'] + 1 : 1;
                    $User->save($follower_data);

                    // send push notifications...
                    /*if(isset($results['User']['is_enable_push_notification']) && $results['User']['is_enable_push_notification'] == 'yes' 
                            || !isset($results['User']['is_enable_push_notification']))
                    {*/
                        $device_type = $results['User']['device_type'];
                        $device_token = $results['User']['device_token'];

                        $payLoadData = array(
                            'Tp' => 'shr', 
                            //'Notfication Text' => $notification_msg,
                            'chat_message' => $notification_msg,
                            "Nid" => (string) $newsfeed_id
                        );
                        if($device_type!= '' && $device_token!= '' && $notification_msg!= '') {
                            $this->Pushnotification->sendMessage($device_type, $device_token, $notification_msg, $payLoadData); 
                        }
                    //}
                    
                    $notificationArr = $Notification->create();
                    $notificationArr['Notification']['_id'] = new MongoId();
                    $notificationArr['Notification']['user_id'] = $fArr['user_id'];
                    $notificationArr['Notification']['notification_msg'] = $notification_msg; //$otherDataArray['name'].' shared '. $otherDataArray['type'];
                    $notificationArr['Notification']['type'] = 'share';
                    $notificationArr['Notification']['newsfeed_id'] = (string) $newsfeed_id;
                    $notificationArr['Notification']['chat_id'] = $dataArray['chat_id'];
                    $notificationArr['Notification']['sharing_id'] = $otherDataArray['sharing_id'];
                    $notificationArr['Notification']['read'] = false;
                    //print_r($notificationArr);
                    $Notification->save($notificationArr);
                    $sent++;
                    $alreadySentToFollowerArr[] = $fArr['user_id'];
                }
            }
            //echo $sent;
            //print_r($alreadySentToFollowerArr);
            //exit;
        }
        
        return $this->save($dataArray);
    }

    /**
     * fetchnewsfeeds method
     * this function is used to fetch newsfeeds by user id and last newsfeed id..
     * @param int $user_id
     * @param int $last_newsfeed_id
     * @return array
     * @access public
     */
    public function fetchnewsfeeds($user_id, $last_newsfeed_id) {

        $conditions = array('follower_user_id.user_id' => $user_id);
        $order = array('_id' => -1);

        // Check for ids greater than the last newsfeed id, if present
        if ($last_newsfeed_id != '') {
            // Set the params to fetch the _id of the newsfeed id in the request
            $newsfeed_params = array(
                'fields' => array('_id'),
                'conditions' => array('_id' => $last_newsfeed_id)
            );

            // Find last newsfeed id's table id
            $last_newsfeed = $this->find('first', $newsfeed_params);

            // Set the condition to fetch records earlier than last newsfeed id
            $conditions['_id']['$lt'] = $last_newsfeed['Newsfeeds']['_id'];
        }

        $params = array(
            'conditions' => $conditions,
            'order' => $order,
            'limit' => 25,
            'page' => 1
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);

        // get latest one id..
        /* if(count($results)>0)
          {
          // to do set read flag true in newsfeed..
          //$maxNewsfeedId = $results[0]['Newsfeeds']['_id'];

          // update all newsfeeds of this user as seen from max id to lower ids..
          /*$conditionArr = array('user_id' => $user_id, "_id" => array('$lt' => $maxNewsfeedId));
          $this->updateAll(
          array('read' => TRUE),
          array($conditionArr)
          );

          // set last seen newsfeed id for that user..
          $params = array('_id' => $user_id,'last_seen_newsfeed_id' => $maxNewsfeedId);
          $User = ClassRegistry::init('User');
          $User->save($params);
          } */
        return $results;
    }

    /**
     * updateCommentStarCount method
     * Update the comment/star count for the newsfeed
     * @param string $newsfeed_id Id of the newsfeed record to update
     * @param int $count Value to increment or decrement
     * @param string $type Comment or Star
     * @return array
     * @access public
     */
    public function updateCommentStarCount($newsfeed_id, $count = 0, $type = 'star') {
        // Check if newsfeed id was provided
        if($newsfeed_id != '') {
            // Check if the newsfeed exists
            $newsfeedDetail = $this->find('first', array('conditions' => array('_id' => new MongoId($newsfeed_id))));
            // Check if newsfeed data found
            if(count($newsfeedDetail) > 0) {
                // Check which entity has been updated
                if($type == 'comment')
                    $newsfeedDetail['Newsfeeds']['comments_count'] = isset($newsfeedDetail['Newsfeeds']['comments_count']) ? 
                            $newsfeedDetail['Newsfeeds']['comments_count'] + $count : 1;
                elseif($type == 'star')
                    $newsfeedDetail['Newsfeeds']['stars_count']  = isset($newsfeedDetail['Newsfeeds']['stars_count']) ? 
                            $newsfeedDetail['Newsfeeds']['stars_count'] + $count : 1;

                // Update newsfeed with new record
                if($this->save($newsfeedDetail)) {
                    return array('success' => true, 'message' => 'Newsfeed udpated successfully');
                }
                else {
                    return array('success' => false, 'message' => 'Newsfeed could not be updated');
                }
            }
            else {
                return array('success' => false, 'message' => 'Newsfeed not found');
            }
        }
        else {
            return array('success' => false, 'message' => 'Newsfeed data not provided');
        }
    }
}
