<?php

class Sharing extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';

    /* _id
      chat_id
      fb
      fb_access_token
      twitter
      twitter_access_token
      google+
      google+_access_token
      in_app
      status (pending, accepted, rejected)
     */

    /**
     * this function is used to save sharing detail.
     * @param array $dataArray
     * @param array $otherDataArray
     */
    public function saveSharing($dataArray, $otherDataArray) {
        // Generate new id for sharing
        $sharing_id = new MongoId();

        $dataArray = array_merge($dataArray, array(
            '_id' => $sharing_id,
        ));
        //print_r($dataArray);print_r($otherDataArray);exit;
        // save entry in notification..
        if ($otherDataArray['relationship_id'] != '' && $otherDataArray['phone_no'] != '') {

            // fetch user id using relationship id..
            $User = ClassRegistry::init('User');
            $relationUserDetail = $User->findRelationship($otherDataArray['phone_no'], $otherDataArray['relationship_id']);

            if (count($relationUserDetail) > 0) {
                //print_r($relationUserDetail);
                //echo $otherDataArray['relationship_id']; exit;
                $curr_partner_id = 0;
                $curr_partner_name = '';
                // increase unread notification count and saving in user table to respective user id..
                foreach ($relationUserDetail['User']['relationships'] as $key => $relationship) {
                    
                    if ((string) $relationUserDetail['User']['relationships'][$key]['id'] == $otherDataArray['relationship_id']) {
                        $curr_partner_id = $relationUserDetail['User']['relationships'][$key]['partner_id'];
                        $curr_partner_name = trim($relationUserDetail['User']['relationships'][$key]['partner_name']);
                        break;
                    }
                }
                
                if ($curr_partner_id > 0 && $curr_partner_name != '') {

                    $partnerDetail = $User->find('first', array('conditions' => array('_id' => new MongoId($curr_partner_id))));
                    $userDetail['unread_notifications_count'] = $partnerDetail['User']['unread_notifications_count'] + 1;
                    $userDetail['_id'] = new MongoId($curr_partner_id);

                    // save user related detail..
                    if ($User->save($userDetail)) {
                        // newsfeed will be sent only when user select clickin media also..
                        if (in_array('clickin', $dataArray['media'])) {
                            // set partner id for getting his/her follower list..
                            $otherDataArray['partner_id'] = (string) $relationUserDetail['User']['_id']; //$curr_partner_id;
                            $otherDataArray['partner_name'] = trim($relationUserDetail['User']['name']); //$curr_partner_name;

                            // save in newsfeed table...
                            $newsFeed = ClassRegistry::init('Newsfeeds');
                            $newsFeedArr['user_id'] = $curr_partner_id; //$relationUserDetail['User']['_id'];
                            $newsFeedArr['chat_id'] = $dataArray['chat_id'];
                            //$newsFeedArr['comment'] = $otherDataArray['comment'];
                            $newsFeedArr['read'] = false;
                            $otherDataArray['name'] = $curr_partner_name; //$relationUserDetail['User']['name'];
                            $otherDataArray['sharing_id'] = (string) $sharing_id;

                            if ($newsFeed->saveNewsfeed($newsFeedArr, $otherDataArray)) {

                                $newsfeed_id = $newsFeed->getLastInsertID();

                                // save comment if any in request data..
                                if ($otherDataArray['comment'] != '') {
                                    $newsfeedArray = $newsFeed->find('first', array('conditions' => array('_id' => new MongoId($newsfeed_id))));
                                    if (count($newsfeedArray) > 0) {
                                        $newsfeed_user_id = $newsfeedArray['Newsfeeds']['user_id']; //$relationUserDetail['User']['_id'];
                                        $newsfeed_chat_id = $newsfeedArray['Newsfeeds']['chat_id'];

                                        // get user data...
                                        $paramUser = array(
                                            'fields' => array('_id', 'name', 'user_pic'),
                                            'conditions' => array('_id' => new MongoId($newsfeed_user_id))
                                        );
                                        $resultUser = $User->find('first', $paramUser);

                                        if (count($resultUser) > 0) {
                                            $commentstar = ClassRegistry::init('Commentstar');
                                            // add save comment/share detail here...
                                            $commentArray['chat_id'] = $newsfeed_chat_id;
                                            $commentArray['newsfeed_id'] = $newsfeed_id;
                                            $commentArray['type'] = 'comment';
                                            $commentArray['comment'] = $otherDataArray['comment'];
                                            $commentArray['user_id'] = (string) $resultUser['User']['_id'];
                                            $commentArray['user_name'] = trim($resultUser['User']['name']);
                                            $commentArray['user_pic'] = trim($resultUser['User']['user_pic']);
                                            $commentstar->saveCommentStar($commentArray, 0);
                                        }
                                    }
                                }

                                // make notification message..                            
                                /*$notification_msg = $otherDataArray['type'] == 'Cards' ? $relationUserDetail['User']['name'] . ' and ' .
                                        $curr_partner_name . ' have shared a click to The Feed' : $relationUserDetail['User']['name'] . ' and ' . $curr_partner_name
                                        . ' have shared a click to The Feed';

                                // save notification..
                                $Notification = ClassRegistry::init('Notification');
                                $notificationArr = $Notification->create();
                                $notificationArr['Notification']['_id'] = new MongoId();
                                $notificationArr['Notification']['user_id'] = $curr_partner_id;
                                //$notificationArr['Notification']['notification_msg'] = $relationUserDetail['User']['name'].' shared '. $otherDataArray['type'];
                                $notificationArr['Notification']['notification_msg'] = $notification_msg;
                                $notificationArr['Notification']['type'] = 'share';
                                $notificationArr['Notification']['newsfeed_id'] = $newsfeed_id;
                                $notificationArr['Notification']['chat_id'] = $dataArray['chat_id'];
                                $notificationArr['Notification']['sharing_id'] = (string) $sharing_id;
                                $notificationArr['Notification']['read'] = false;

                                // Saving the new notification for the user
                                if ($Notification->save($notificationArr)) {
                                    // save sharing data in sharing collection..
                                    $this->save($dataArray);
                                    return array('newsfeed_id' => $newsfeed_id);
                                }
                                else
                                    return FALSE;*/
                                // save sharing data in sharing collection..
                                if ($this->save($dataArray)) {
                                    return array('newsfeed_id' => $newsfeed_id);
                                }
                                else
                                    return FALSE;
                            }
                            else
                                return FALSE;
                        }
                        else
                        // save sharing data in sharing collection..
                            return $this->save($dataArray);
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
        else
            return FALSE;
    }

}