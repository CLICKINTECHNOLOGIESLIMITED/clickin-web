<?php

class Notification extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';

    /*  id
      user_id (_id from Users collection)
      notification_msg
      created
      type : invite {
      invite_user_id, (_id from Users collection)
      invite_name,
      invite_user_pic
      }
      or
      follower {
      follower_user_id, (_id from Users collection)
      follower_name,
      follower_user_pic
      }
      or
      share {
      chat_id
      sharings_id (_id from Sharings collection)
      }
      read (true or false)
     */

    /**
     * fetchnotifications method
     * this function is used to fetch notifications by user id and last notification id..
     * @param int $user_id
     * @param int $last_notification_id
     * @return array
     * @access public
     */
    public function fetchnotifications($user_id, $last_notification_id) {

        $conditions = array('user_id' => $user_id);
        $order = array('_id' => -1);

        // Check for ids greater than the last notification id, if present
        if ($last_notification_id != '') {
            // Set the params to fetch the _id of the notification id in the request
            $notification_params = array(
                'fields' => array('_id'),
                'conditions' => array('_id' => $last_notification_id)
            );

            // Find last notification id's table id
            $last_notification = $this->find('first', $notification_params);

            // Set the condition to fetch records earlier than last notification id
            $conditions['_id']['$lt'] = $last_notification['Notification']['_id'];
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
        if (count($results) > 0) {
            // to do set read flag true in notification..
            $maxNotificationId = $results[0]['Notification']['_id'];
            $resultNotifications = $results;
            // update all notifications of this user as seen from max id to lower ids..
            foreach ($resultNotifications as $key => $result) {
                $resultNotifications[$key]['Notification']['read'] = true;
                $this->save($resultNotifications[$key]);
            }
            
            /*$conditionArr = array('user_id' => $user_id, "_id" => array('$lt' => $maxNotificationId));
            $this->updateAll(
                    array('read' => TRUE), array($conditionArr)
            );*/
            
            // set last seen notification id for that user..
            $params = array('_id' => $user_id, 'last_seen_notification_id' => $maxNotificationId, 'unread_notifications_count' => 0);
            $User = ClassRegistry::init('User');
            $User->save($params);
        }
        return $results;
    }

    /**
     * saveNotification method
     * this function is used to save notification when user make relationship public/private/accepted/rejected or
     * accept/reject users who are following him.
     * 
     * @param object $request_data
     * @param array $data
     * @param string $type - relationvisibility, relationstatus, followstatus
     * @access public
     */
    public function saveNotification($request_data, $data, $type) {
        $User = ClassRegistry::init('User');
        if ($type == 'relationvisibility' || $type == 'relationstatus' || $type == 'relationdelete') {
            $params = array(
                'fields' => array('unread_notifications_count'),
                'conditions' => array(
                    'relationships.partner_id' => $data[0]['User']['_id'],
                    'relationships.id' => new MongoId($request_data->relationship_id)
            ));
            $results = $User->find('first', $params);

            $results['User']['unread_notifications_count'] += 1;
            // Creating a new record for Partner's data in Users collection
            if ($User->save($results)) {
                if ($type == 'relationvisibility') {
                    $visibility = $request_data->public == 'true' ? 'public' : 'private';
                    $notification_msg = ($request_data->public == 'true') ? trim($data[0]['User']['name']) . " made your relationship visible on their profile" :
                            trim($data[0]['User']['name']) . " has hidden your relationship on their profile";

                    //$data[0]['User']['name'] . ' changed relationship '. $visibility . ' with you.';
                } else if ($type == 'relationstatus') {
                    $status = ($request_data->accepted == 'true') ? 'accepted' : 'rejected';
                    $notification_msg = ($status == 'accepted') ? trim($data[0]['User']['name']) . " is now Clickin' with you!" :
                            "Oops! Sorry - " . trim($data[0]['User']['name']) . " has rejected your request";
                    //$data[0]['User']['name'] . ' has ' . $status . ' to click with you.';
                } else if ($type == 'relationdelete') {
                    $notification_msg = trim($data[0]['User']['name']) . ' has ended their relationship with you.';
                }
            }
        } else if ($type == 'followstatus') {
            // Find records matching followings and other data in following user..
            $params = array(
                'fields' => array('_id', 'unread_notifications_count'),
                'conditions' => array('following._id' => new MongoId($request_data->follow_id), 'following.followee_id' => $data[0]['User']['_id'])
            );
            $results = $User->find('first', $params);

            $results['User']['unread_notifications_count'] += 1;
            // Creating a new record for Partner's data in Users collection
            if ($User->save($results)) {
                $status = ($request_data->accepted == 'true') ? 'accepted' : 'rejected';
                $notification_msg = ($status == 'accepted') ? "You are now following " . trim($data[0]['User']['name']) :
                        "Sorry! Looks like " . trim($data[0]['User']['name']) . " wants to keep things private";
                //$notification_msg = $data[0]['User']['name'] . ' '.$status.' your following request.';
            }
        }

        if (count($results) > 0) {
            // Create a new entry for the notification to be shown to the new user
            $notificationArr = $this->create();
            $notificationArr['Notification']['user_id'] = $results['User']['_id'];
            $notificationArr['Notification']['notification_msg'] = $notification_msg;
            $notificationArr['Notification']['type'] = $type;
            $notificationArr['Notification']['read'] = false;
            $notificationArr['Notification']['follower_user_id'] = $data[0]['User']['_id'];
            $notificationArr['Notification']['follower_name'] = trim($data[0]['User']['name']);
            $notificationArr['Notification']['follower_user_pic'] = trim($data[0]['User']['user_pic']);

            // Saving the new notification for the user
            return $this->save($notificationArr);
        }
        else
            return FALSE;
    }

}
