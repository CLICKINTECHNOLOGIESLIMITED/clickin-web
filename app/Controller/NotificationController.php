<?php

/**
 * NotificationController class
 *
 * @uses          AppController
 * @package       mongodb
 * @subpackage    mongodb.samples.controllers
 */
class NotificationController extends AppController {

    /**
     * name property
     *
     * @var string 'Users'
     * @access public
     */
    public $name = 'notification';

    /**
     * use data model property
     * @var array
     * @access public 
     */
    var $uses = array('User','Notification');
    
    /**
     * fetchnotifications method
     * this function is used to fetch notification detail by id..
     * @return \CakeResponse
     * @access public
     */
    public function fetchnotifications() 
    {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;
        
            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token):
                
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
                
                        // Check if last notification id is present
                        $last_notification_id = (isset($request_data->last_notification_id)) ? $request_data->last_notification_id : '';
                        
                        // Fetch the notification records, starting from the last notification id, if present
                        $dataArray = $this->Notification->fetchNotifications($data[0]['User']['_id'], $last_notification_id);                        
                        
                        if (count($dataArray)>0) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'User have notification(s).';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'User don\'t have any notification.';
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
            $notificationArray = array();
            $rCount = 0;
            foreach ($dataArray as $notkey => $notVal) {
                $notificationArray[$rCount]['_id'] = $dataArray[$notkey]['Notification']['_id'];
                $notificationArray[$rCount]['user_id'] = $dataArray[$notkey]['Notification']['user_id'];
                $notificationArray[$rCount]['notification_msg'] = $dataArray[$notkey]['Notification']['notification_msg'];
                $notificationArray[$rCount]['type'] = $dataArray[$notkey]['Notification']['type'];
                $notificationArray[$rCount]['read'] = $dataArray[$notkey]['Notification']['read'];
                // followe user detail if exist..
                if(isset($dataArray[$notkey]['Notification']['follower_user_id']))
                {
                    $notificationArray[$rCount]['follower_user_id'] = $dataArray[$notkey]['Notification']['follower_user_id'];
                    $notificationArray[$rCount]['follower_name'] = $dataArray[$notkey]['Notification']['follower_name'];
                    $notificationArray[$rCount]['follower_user_pic'] = $dataArray[$notkey]['Notification']['follower_user_pic'];
                }
                // invite user detail if exist..
                if(isset($dataArray[$notkey]['Notification']['invite_user_id']))
                {
                    $notificationArray[$rCount]['invite_user_id'] = $dataArray[$notkey]['Notification']['invite_user_id'];
                    $notificationArray[$rCount]['invite_name'] = $dataArray[$notkey]['Notification']['invite_name'];
                    $notificationArray[$rCount]['invite_user_pic'] = $dataArray[$notkey]['Notification']['invite_user_pic'];
                }
                // user profile pic updation if exist..
                if(isset($dataArray[$notkey]['Notification']['update_user_id']))
                {
                    $notificationArray[$rCount]['update_user_id'] = $dataArray[$notkey]['Notification']['update_user_id'];
                    $notificationArray[$rCount]['update_name'] = $dataArray[$notkey]['Notification']['update_name'];
                    $notificationArray[$rCount]['update_user_pic'] = $dataArray[$notkey]['Notification']['update_user_pic'];
                }
                
                if($dataArray[$notkey]['Notification']['type'] == 'share')
                {
                    $chat_id = $dataArray[$notkey]['Notification']['chat_id'];
                    $sharing_id = $dataArray[$notkey]['Notification']['sharing_id'];
                    
                    // Find chat detail by chat id...
                    $Chat = ClassRegistry::init('Chat');
                    $chatDetailArr = $Chat->find('first', array('_id' => $chat_id));
                    $notificationArray[$rCount]['Notification']['chatDetail'] = $chatDetailArr;
                    // find sharing detail by sharing id..
                    $Sharing = ClassRegistry::init('Sharing');
                    $sharingDetailArr = $Sharing->find('first', array('_id' => $sharing_id));
                    $notificationArray[$rCount]['Notification']['sharingDetail'] = $sharingDetailArr;
                }
                
                // add newsfeed id if exist..
                $notificationArray[$rCount]['newsfeed_id'] = isset($dataArray[$notkey]['Notification']['newsfeed_id']) ? 
                                                                $dataArray[$notkey]['Notification']['newsfeed_id'] : '';
                
                $notificationArray[$rCount]['created']=date('Y-m-d h:i:s', $dataArray[$notkey]['Notification']['created']->sec);
                $notificationArray[$rCount]['modified'] = date('Y-m-d h:i:s', $dataArray[$notkey]['Notification']['modified']->sec);
                $rCount++;
            }
            $out['notificationArray'] = $notificationArray;
        }
        
        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

}