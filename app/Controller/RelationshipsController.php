<?php

/**
 * Static content controller.
 *
 * This file will render views from views/pages/
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * newrequest
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
App::uses('AppController', 'Controller');

/**
 * Static content controller
 *
 * Override this controller by placing a copy in controllers directory of an application
 *
 * @package       app.Controller
 * @link http://book.cakephp.org/2.0/en/controllers/pages-controller.html
 */
class RelationshipsController extends AppController {

    /**
     * This controller uses User model
     *
     * @var array
     */
    public $uses = array(/* 'Relationship', */ 'User', 'Notification');

    /**
     * name property
     *
     * @var string 'Relationships'
     * @access public
     */
    public $name = 'Relationships';

    /**
     * components property
     * 
     * @var array
     * @access public
     */
    public $components = array('Shorten', 'Twilio.Twilio', 'Pushnotification');

    /**
     * newrequest method
     *
     * @return void
     * @access public
     */
    public function newrequest() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        $sendInviteSMS = 0;
        $user_exists = 0;
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;
            // when phone no and partner phone no are same..
            case!empty($request_data->phone_no) && !empty($request_data->partner_phone_no) && (trim($request_data->phone_no) == trim($request_data->partner_phone_no)):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Partner phone no should be different to your phone no.';
                break;
            // Request is valid and phone no, user token and partner's phone no. is present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->partner_phone_no):
                // Check if record exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if requested phone no exists in database
                $partner_data = $this->User->findUser($request_data->partner_phone_no);

                // Check if relationship request has already been made to the user
                $check_relationship_request = $this->User->findRelationshipRequest($data[0]['User']['user_token'], $request_data->partner_phone_no);

                // Create new request if user is valid
                if (count($data) != 0) {
                    // Check if user token is valid
                    if ($data[0]['User']['user_token'] != $request_data->user_token) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is not valid';
                    } elseif (count($check_relationship_request) != 0) {

                        $alreadyHaveDeletedRelation = 0;
                        $alreadyHaveRelationRejected = 0;
                        $existing_relationship_id = 0;
                        // get relation deleted or not..
                        if (isset($check_relationship_request[0]['User']['relationships'])) {
                            foreach ($check_relationship_request[0]['User']['relationships'] as $key => $relationship) {
                                if ($check_relationship_request[0]['User']['relationships'][$key]['phone_no'] == $request_data->partner_phone_no) {
                                    if (isset($check_relationship_request[0]['User']['relationships'][$key]['deleted']) &&
                                            $check_relationship_request[0]['User']['relationships'][$key]['deleted'] == 'yes') {
                                        $alreadyHaveDeletedRelation = 1;
                                        $existing_relationship_id = (string) $check_relationship_request[0]['User']['relationships'][$key]['id'];
                                    } else {
                                        if (isset($check_relationship_request[0]['User']['relationships'][$key]['accepted'])) {
                                            $alreadyHaveRelationRejected = $check_relationship_request[0]['User']['relationships'][$key]['accepted'] === false ? 1 : 0;
                                            $existing_relationship_id = (string) $check_relationship_request[0]['User']['relationships'][$key]['id'];
                                        }
                                        else
                                            $alreadyHaveDeletedRelation = 0;
                                    }
                                    break;
                                }
                            }
                        }
                        // check if relationship already in db with deleted = yes
                        if ($alreadyHaveDeletedRelation == 1 || $alreadyHaveRelationRejected == 1) {
                            $user_exists = 1;
                            // Update the notification count for the partner, in case partner is already registered
                            $new_partner_data['_id'] = $partner_data[0]['User']['_id'];
                            $new_partner_data['unread_notifications_count'] = $partner_data[0]['User']['unread_notifications_count'] + 1;
                            $this->User->save($new_partner_data);

                            // Fetch the new partner's details
                            $new_partner = $this->User->findUser($request_data->partner_phone_no);
                            // Create a new entry for the notification to be shown to the new user
                            $new_partner_notification = $this->Notification->create();
                            $new_partner_notification['Notification']['user_id'] = $new_partner[0]['User']['_id'];
                            $new_partner_notification['Notification']['notification_msg'] = "Clickin' request from " . trim($data[0]['User']['name']);
                            $new_partner_notification['Notification']['type'] = 'relationrequest'; // invite
                            $new_partner_notification['Notification']['read'] = false;
                            $new_partner_notification['Notification']['invite_user_id'] = $data[0]['User']['_id'];
                            $new_partner_notification['Notification']['invite_name'] = trim($data[0]['User']['name']);
                            $new_partner_notification['Notification']['invite_user_pic'] = $data[0]['User']['user_pic'];
                            // Saving the new notification for the user
                            $this->Notification->save($new_partner_notification);

                            // update relationship data of both user's collection.
                            // get relationship_id
                            $request_data->relationship_id = $existing_relationship_id;
                            $request_data->deleted = 'no';
                            if ($this->User->saveRelationshipData($request_data, $data, 1)) {

                                // send push notification to partner on iphone/android as per device token and type..
                                /* if(isset($new_partner[0]['User']['is_enable_push_notification']) && $new_partner[0]['User']['is_enable_push_notification'] == 'yes' 
                                  || !isset($new_partner[0]['User']['is_enable_push_notification']))
                                  { */
                                if (isset($new_partner[0]['User']['device_type']) && isset($new_partner[0]['User']['device_token'])) {
                                    $device_type = $new_partner[0]['User']['device_type'];
                                    $device_token = $new_partner[0]['User']['device_token'];
                                    $message = "Clickin' request from " . trim($data[0]['User']['name']);
                                    $payLoadData = array(
                                        'Tp' => "CR",
                                        'Rid' => $existing_relationship_id,
                                        //'Notfication Text' => trim($data[0]['User']['name']) . " wants to Click with you!",
                                        'chat_message' => trim($data[0]['User']['name']) . " wants to Click with you!"
                                    );
                                    $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);
                                }
                                //}

                                $success = true;
                                $status = SUCCESS;
                                $message = 'Request sent to partner';
                            } else {
                                $success = false;
                                $status = ERROR;
                                $message = 'There was a problem in processing your request';
                            }
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'Request has already been made to the user';
                        }
                    } else {

                        // Partner with the provided phone no. does not exist in DB
                        if (count($partner_data) == 0) {
                            
                            // Create a new entry for partner with empty device token and name in Users collection
                            //$new_partner_data = $this->User->create();
                            //$new_partner_data['User']['phone_no'] = $request_data->partner_phone_no;
                            // Setting unread notifications count as 1, since this will be the first notification to the user after creation
                            //$new_partner_data['User']['unread_notifications_count'] = 1;
                            //$new_partner_data['User']['verified'] = false;
                            // Creating a new record for Partner's data in Users collection
                            //$this->User->save($new_partner_data);
                            // Set the flag to send SMS to the partner
                            //$sendInviteSMS = 1;
                        } else {
                            $user_exists = 1;
                            // Update the notification count for the partner, in case partner is already registered
                            $new_partner_data['_id'] = $partner_data[0]['User']['_id'];
                            $new_partner_data['unread_notifications_count'] = $partner_data[0]['User']['unread_notifications_count'] + 1;
                            $this->User->save($new_partner_data);
                        }

                        // Fetch the new partner's details
                        $new_partner = $this->User->findUser($request_data->partner_phone_no);

                        // Create a new entry for the notification to be shown to the new user
                        $new_partner_notification = $this->Notification->create();
                        $new_partner_notification['Notification']['user_id'] = $new_partner[0]['User']['_id'];
                        $new_partner_notification['Notification']['notification_msg'] = "Clickin' request from " . trim($data[0]['User']['name']);
                        $new_partner_notification['Notification']['type'] = 'relationrequest'; // invite
                        $new_partner_notification['Notification']['read'] = false;
                        $new_partner_notification['Notification']['invite_user_id'] = $data[0]['User']['_id'];
                        $new_partner_notification['Notification']['invite_name'] = trim($data[0]['User']['name']);
                        $new_partner_notification['Notification']['invite_user_pic'] = $data[0]['User']['user_pic'];

                        // Saving the new notification for the user
                        $this->Notification->save($new_partner_notification);

                        // Generate new id for relationship
                        $relationship_id = new MongoId();

                        #####
                        // TODO :: The insertion of the relationship data into the requested user document will be done after Acceptance
                        // Create data for new relationship request for requested user
                        $relationship_data_partner = array(
                            'id' => $relationship_id,
                            'phone_no' => $data[0]['User']['phone_no'],
                            'partner_id' => $data[0]['User']['_id'],
                            'partner_name' => trim($data[0]['User']['name']),
                            'partner_pic' => isset($data[0]['User']['user_pic']) ? $data[0]['User']['user_pic'] : "",
                            'partner_QB_id' => isset($data[0]['User']['QB_id']) ? $data[0]['User']['QB_id'] : "",
                            'clicks' => '100',
                            'accepted' => true, // false // TODO :: By default invite accepted for demo purpose
                            'public' => true, // false //  TODO ::Make the relationship public by default
                            'deleted' => 'no',
                            'last_chat_id' => '',
                            'is_new_partner' => 'yes'
                        );

                        // Save the new relationship array into the partner / requested user's relationship array
                        $partner_relationship_data['_id'] = $new_partner[0]['User']['_id'];
                        $partner_relationship_data['$push']['relationships'] = $relationship_data_partner;

                        // Insert new relationship data into the partner's details
                        $this->User->save($partner_relationship_data);
                        #####
                        // Create data for new relationship request for requesting user
                        $relationship_data = array(
                            'id' => $relationship_id,
                            'phone_no' => $request_data->partner_phone_no,
                            'partner_id' => $new_partner[0]['User']['_id'],
                            'partner_name' => trim($new_partner[0]['User']['name']),
                            'partner_pic' => isset($new_partner[0]['User']['user_pic']) ? $new_partner[0]['User']['user_pic'] : '',
                            'partner_QB_id' => isset($new_partner[0]['User']['QB_id']) ? $new_partner[0]['User']['QB_id'] : '',
                            'clicks' => '100',
                            'request_initiator' => true, // Checking if user initiated the request
                            'accepted' => true, // false // TODO :: By default invite accepted for demo purpose
                            'public' => true, // false //  TODO ::Make the relationship public by default
                            'deleted' => 'no',
                            'last_chat_id' => '',
                            'is_new_partner' => 'yes'
                        );

                        // Insert a new entry into the relationships collection
                        $user_relationship_data['_id'] = $data[0]['User']['_id'];
                        $user_relationship_data['$push']['relationships'] = $relationship_data;

                        if ($this->User->save($user_relationship_data)) {
                            // Check if SMS needs to be sent, once the data has been saved
                            /* if ($sendInviteSMS) {
                              // Send SMS to the partner regarding the invitation
                              $invitationSMS = $this->sendInvitationSMS($request_data->partner_phone_no, $data[0]['User']['name']);
                              } */

                            // send push notification to partner on iphone/android as per device token and type..
                            /* if(isset($new_partner[0]['User']['is_enable_push_notification']) && $new_partner[0]['User']['is_enable_push_notification'] == 'yes' 
                              || !isset($new_partner[0]['User']['is_enable_push_notification']))
                              { */
                            if (isset($new_partner[0]['User']['device_type']) && isset($new_partner[0]['User']['device_token'])) {
                                $device_type = $new_partner[0]['User']['device_type'];
                                $device_token = $new_partner[0]['User']['device_token'];
                                $message = "Clickin' request from " . trim($data[0]['User']['name']);
                                $payLoadData = array(
                                    'Tp' => "CR",
                                    'Rid' => $relationship_id,
                                    //'Notfication Text' => trim($data[0]['User']['name']) . " wants to Click with you!",
                                    'chat_message' => trim($data[0]['User']['name']) . " wants to Click with you!"
                                );
                                $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);
                            }
                            //}

                            $success = true;
                            $status = SUCCESS;
                            $message = 'Request sent to partner';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'There was a problem in processing your request';
                        }
                    }
                }
                // Return false if record does not exists
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'Phone no. not registered yet.';
                }
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // Device Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User Token cannot be blank.';
                break;
            //  Partner Phone no. blank in request
            case!empty($request_data) && empty($request_data->partner_phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Partner Phone no/ cannot be blank.';
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
            "user_exists" => $user_exists
        );

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * getrelationships method
     *
     * @return void
     * @access public
     */
    public function getrelationships() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        $follower = 0;
        $following = 0;
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;
            // Request is valid and phone no and user token is present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token):
                // Check if record exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check relationship data if record exists
                if (count($data) != 0) {
                    // Check if user token is valid
                    if ($data[0]['User']['user_token'] != $request_data->user_token) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is not valid';
                    } else {
                        // Get relationships for the user which have been accepted by partner
                        $user_data = $this->User->findRelationshipsByType($request_data->user_token, $request_data->phone_no, TRUE);
                        // Get relationships for the user which is pending on partner
                        $user_data_pending = $this->User->findRelationshipsByType($request_data->user_token, $request_data->phone_no, NULL);

                        // Check if there are accepted relationships
                        if (count($user_data) > 0 && isset($user_data[0]['User']['relationships'])) {
                            // Assign all accepted relationships to relationships array
                            $relationship_data = $user_data[0]['User']['relationships'];
                            // Check if there are pending relationships
                            if (count($user_data_pending) > 0 && isset($user_data_pending[0]['User']['relationships']))
                            // Merge the pending relationships with the relationships array
                                $relationship_data = array_merge($user_data[0]['User']['relationships'], $user_data_pending[0]['User']['relationships']);
                        }
                        else {
                            // If there are no accepted relationships, assign pending relationships to relationships array
                            $relationship_data = $user_data_pending[0]['User']['relationships'];
                        }

                        //$relationship_data = $user_data[0]['User']['relationships'];
                        //if(count($user_data_pending)>0)
                        //    $relationship_data = array_merge ( $user_data[0]['User']['relationships'], $user_data_pending[0]['User']['relationships']);
                        
                        // Fetch details of the searched phone no.
                        $user_details = $this->User->fetchUserProfile($request_data->phone_no);
                        $follower = $user_details['User']['follower'];
                        $following = $user_details['User']['following'];

                        if (count($relationship_data) != 0) {
                            // rearrange array values..
                            $relationship_data = array_values($relationship_data);
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Relationships data found';
                        } else {
                            $relationship_data = array();
                            $success = count($user_details) > 0 ? true : false;
                            $status = SUCCESS;
                            $message = 'No relationships found';
                        }
                    }
                }
                // Return false if record does not exists
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'Phone no. not registered yet.';
                }
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // Device Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User Token cannot be blank.';
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
            $out['user_pic'] = $data[0]['User']['user_pic'];
            $out['relationships'] = $relationship_data;
            $out['follower'] = $follower;
            $out['following'] = $following;
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * followuser method
     *
     * @return void
     * @access public
     */
    public function followuser() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;
            // Request is valid and phone no, user token and followee's phone no. is present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->followee_phone_no):
                // Check if record exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if requested phone no exists in database
                $followee_data = $this->User->findUser($request_data->followee_phone_no);

                // Check if user is already following the followee
                $check_following = $this->User->findFollowing($data[0]['User']['user_token'], $request_data->followee_phone_no);

                // Create new follow if user is valid
                if (count($data) != 0) {
                    // Check if user token is valid
                    if ($data[0]['User']['user_token'] != $request_data->user_token) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is not valid';
                    } elseif (count($check_following) != 0) {

                        $is_already_following = 0;
                        // check accepted value for followee in follower user's subcollection...
                        foreach ($check_following[0]['User']['following'] as $key => $following) {
                            // Check if the user does not have clicks
                            if ($check_following[0]['User']['following'][$key]['phone_no'] == $request_data->followee_phone_no &&
                                    $check_following[0]['User']['following'][$key]['accepted'] !== false) {
                                $is_already_following = 1;
                                break;
                            }
                        }

                        // if user already sent follow request or following...
                        if ($is_already_following == 1) {
                            $success = false;
                            $status = ERROR;
                            $message = 'User already being followed';
                        } else {
                            $followee_added = $this->resendfollowentry($followee_data, $request_data->followee_phone_no, $data[0]['User']);
                            if ($followee_added) {
                                $success = true;
                                $status = SUCCESS;
                                $message = 'Successfully following';
                            } else {
                                $success = false;
                                $status = ERROR;
                                $message = 'There was a problem in processing your request';
                            }
                        }
                    } else {

                        // Add the new followee entry into the user's record
                        $followee_added = $this->createfollowentry($followee_data, $request_data->followee_phone_no, $data[0]['User']);

                        // Insert a new entry into the following's collection
                        $user_followee_data['_id'] = $data[0]['User']['_id'];
                        $user_followee_data['$push']['following'] = $followee_added;

                        if ($this->User->save($user_followee_data)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Successfully following';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'There was a problem in processing your request';
                        }
                    }
                }
                // Return false if record does not exists
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'Phone no. not registered yet.';
                }
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // Device Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User Token cannot be blank.';
                break;
            //  Partner Phone no. blank in request
            case!empty($request_data) && empty($request_data->followee_phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Followee\'s Phone no. cannot be blank.';
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
     * inviteandfollowuser method
     *
     * @return void
     * @access public
     */
    public function inviteandfollowusers() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;
            // Request is valid and phone no, user token and followee's phone no. is present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->followee_phone_nos):
                // Check if record exists
                $data = $this->User->findUser($request_data->phone_no);

                // Create new follow if user is valid
                if (count($data) != 0) {
                    // Check if user token is valid
                    if ($data[0]['User']['user_token'] != $request_data->user_token) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is not valid';
                    } else {
                        // Check if requested phone nos. exist in database, also whether the user is following them
                        $check_existing_following = $this->User->checkPhoneNosRegistered($request_data->followee_phone_nos, $request_data->phone_no);

                        $user_followee_arr = array();

                        // Loop through all the results
                        foreach ($check_existing_following as $phone_no) {
                            // If the user is not already following the requested user, create a follow entry
                            if (!isset($phone_no['following'])) {
                                $followee_data = array();
                                // Check if requested phone_no already exists
                                if ($phone_no['exists']) {
                                    // Fetch the followee data
                                    $followee_data = $this->User->findUser($phone_no['phone_no']);
                                }

                                // Add the new followee entry into the user's record
                                $followee_added = $this->createfollowentry($followee_data, $phone_no['phone_no'], $data[0]['User']);

                                // Insert a new entry into the following's collection
                                $user_followee_arr[] = $followee_added;
                            }
                        }

                        $user_following_data['_id'] = $data[0]['User']['_id'];
                        $user_following_data['$push']['following']['$each'] = $user_followee_arr;

                        if ($this->User->save($user_following_data)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Successfully following users';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'There was a problem in processing your request';
                        }
                    }
                }
                // Return false if record does not exists
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'Phone no. not registered yet.';
                }
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // Device Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User Token cannot be blank.';
                break;
            //  Partner Phone no. blank in request
            case!empty($request_data) && empty($request_data->followee_phone_nos):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Followee\'s Phone nos. cannot be blank.';
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
     * createfollowentry method
     * @param array $followee_data Data regarding the user whom to follow
     * @param string $requested_followee_phone_no Phone no. of the user to follow
     * @param array $follower_user_data Data of the user following
     * @return array
     * @access private
     */
    private function createfollowentry($followee_data, $requested_followee_phone_no, $follower_user_data) {
        $sendInviteSMS = 0;
        // Check if followee with the provided phone no. does not exist in DB
        if (count($followee_data) == 0) {
            // Create a new entry for followee with empty device token and name in Users collection
            $new_followee_data = $this->User->create();
            $new_followee_data['User']['phone_no'] = $requested_followee_phone_no;
            // Setting unread notifications count as 1, since this will be the first notification to the user after creation
            $new_followee_data['User']['unread_notifications_count'] = 1;
            // Creating a new record for Followee's data in Users collection
            $this->User->save($new_followee_data);

            // Set the flag to send SMS to the partner
            $sendInviteSMS = 1;
        } else {
            // Update the notification count for the partner, in case partner is already registered
            $new_followee_data['_id'] = $followee_data[0]['User']['_id'];
            $new_followee_data['unread_notifications_count'] = $followee_data[0]['User']['unread_notifications_count'] + 1;
            $this->User->save($new_followee_data);
        }

        // Fetch the new followee's details
        $new_followee = $this->User->findUser($requested_followee_phone_no);

        // Create a new entry for the notification to be shown to the new user
        $new_followee_notification = $this->Notification->create();
        $new_followee_notification['Notification']['user_id'] = $new_followee[0]['User']['_id'];
        $new_followee_notification['Notification']['notification_msg'] = trim($follower_user_data['name']) . ' wants to follow you';
        $new_followee_notification['Notification']['type'] = 'followrequest'; // follow
        $new_followee_notification['Notification']['read'] = false;
        $new_followee_notification['Notification']['follower_user_id'] = $follower_user_data['_id'];
        $new_followee_notification['Notification']['follower_name'] = trim($follower_user_data['name']);
        $new_followee_notification['Notification']['follower_user_pic'] = $follower_user_data['user_pic'];

        // Saving the new notification for the user
        $this->Notification->save($new_followee_notification);

        // send push notification to followee user from follower..
        /* if(isset($new_followee[0]['User']['is_enable_push_notification']) && $new_followee[0]['User']['is_enable_push_notification'] == 'yes' 
          || !isset($new_followee[0]['User']['is_enable_push_notification']))
          { */
        $device_type = $new_followee[0]['User']['device_type'];
        $device_token = $new_followee[0]['User']['device_token'];
        $message = trim($follower_user_data['name']) . " wants to follow you";
        $payLoadData = array(
            'Tp' => "FR",
            'Fid' => $follower_user_data['_id'],
            //'Notfication Text' => trim($follower_user_data['name']) . " wants to follow you",
            'chat_message' => trim($follower_user_data['name']) . " wants to follow you"
        );
        if ($device_type != '' && $device_token != '' && $message != '') {
            $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);
        }
        //}

        $rel_id = new MongoId();
        // Create data for follower user
        $follower_data = array(
            '_id' => $rel_id,
            'phone_no' => $follower_user_data['phone_no'],
            'follower_id' => $follower_user_data['_id'],
            'follower_name' => trim($follower_user_data['name']),
            'follower_pic' => $follower_user_data['user_pic'],
            'accepted' => NULL
        );

        // Insert a new entry into the follower's collection
        $followee_follower_data['_id'] = $new_followee[0]['User']['_id'];
        $followee_follower_data['$push']['follower'] = $follower_data;

        $this->User->save($followee_follower_data);

        // Check if SMS needs to be sent, after details have been saved
        if ($sendInviteSMS) {
            // Send SMS to the partner regarding the invitation
            $invitationSMS = $this->sendInvitationSMS($requested_followee_phone_no, $follower_user_data['name']);
        }

        // Create data for following user
        $followee_data = array(
            '_id' => $rel_id,
            'phone_no' => $requested_followee_phone_no,
            'followee_id' => $new_followee[0]['User']['_id'],
            'followee_name' => trim($new_followee[0]['User']['name']),
            'followee_pic' => $new_followee[0]['User']['user_pic'],
            'accepted' => NULL
        );

        return $followee_data;
    }

    /**
     * resendfollowentry method
     * @param array $followee_data Data regarding the user whom to follow
     * @param string $requested_followee_phone_no Phone no. of the user to follow
     * @param array $follower_user_data Data of the user following
     * @return array
     * @access private
     */
    private function resendfollowentry($followee_data, $requested_followee_phone_no, $follower_user_data) {
        $sendInviteSMS = 0;
        // Check if followee with the provided phone no. does not exist in DB
        if (count($followee_data) == 0) {
            // Create a new entry for followee with empty device token and name in Users collection
            $new_followee_data = $this->User->create();
            $new_followee_data['User']['phone_no'] = $requested_followee_phone_no;
            // Setting unread notifications count as 1, since this will be the first notification to the user after creation
            $new_followee_data['User']['unread_notifications_count'] = 1;
            // Creating a new record for Followee's data in Users collection
            $this->User->save($new_followee_data);

            // Set the flag to send SMS to the partner
            $sendInviteSMS = 1;
        } else {
            // Update the notification count for the partner, in case partner is already registered
            $new_followee_data['_id'] = $followee_data[0]['User']['_id'];
            $new_followee_data['unread_notifications_count'] = $followee_data[0]['User']['unread_notifications_count'] + 1;
            $this->User->save($new_followee_data);
        }
        // Fetch the new followee's details
        $new_followee = $this->User->findUser($requested_followee_phone_no);

        // Create a new entry for the notification to be shown to the new user
        $new_followee_notification = $this->Notification->create();
        $new_followee_notification['Notification']['user_id'] = $new_followee[0]['User']['_id'];
        $new_followee_notification['Notification']['notification_msg'] = trim($follower_user_data['name']) . ' wants to follow you';
        $new_followee_notification['Notification']['type'] = 'followrequest'; // follow
        $new_followee_notification['Notification']['read'] = false;
        $new_followee_notification['Notification']['follower_user_id'] = $follower_user_data['_id'];
        $new_followee_notification['Notification']['follower_name'] = trim($follower_user_data['name']);
        $new_followee_notification['Notification']['follower_user_pic'] = $follower_user_data['user_pic'];

        // Saving the new notification for the user
        $this->Notification->save($new_followee_notification);

        // send push notification to followee user from follower..
        /* if(isset($new_followee[0]['User']['is_enable_push_notification']) && $new_followee[0]['User']['is_enable_push_notification'] == 'yes' 
          || !isset($new_followee[0]['User']['is_enable_push_notification']))
          { */
        $device_type = $new_followee[0]['User']['device_type'];
        $device_token = $new_followee[0]['User']['device_token'];
        $message = trim($follower_user_data['name']) . " wants to follow you";
        $payLoadData = array(
            'Tp' => "FR",
            'Fid' => $follower_user_data['_id'],
            //'Notfication Text' => trim($follower_user_data['name']) . " wants to follow you",
            'chat_message' => trim($follower_user_data['name']) . " wants to follow you"
        );
        if ($device_type != '' && $device_token != '' && $message != '') {
            $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);
        }
        //}
        // Check if SMS needs to be sent, after details have been saved
        if ($sendInviteSMS) {
            // Send SMS to the partner regarding the invitation
            $invitationSMS = $this->sendInvitationSMS($requested_followee_phone_no, $follower_user_data['name']);
        }

        $followeeData = $new_followee[0];
        if (count($followeeData) > 0) {
            foreach ($followeeData["User"]["follower"] as $urKey => $urVal) {
                if ((string) $followeeData["User"]["follower"][$urKey]['follower_id'] == $follower_user_data['_id'])
                    $followeeData["User"]["follower"][$urKey]["accepted"] = NULL;
            }
        }
        if ($this->User->save($followeeData)) {
            // Find records matching follower and other data for user..
            $params = array(
                'fields' => array('_id', 'following'),
                'conditions' => array('_id' => new MongoId($follower_user_data['_id']))
            );
            $results = $this->User->find('first', $params);
            if (count($results) > 0) {
                foreach ($results["User"]["following"] as $urKey => $urVal) {
                    if ((string) $new_followee[0]['User']['_id'] == (string) $results["User"]["following"][$urKey]['followee_id'])
                        $results["User"]["following"][$urKey]["accepted"] = NULL;
                }
            }
            return $this->User->save($results);
        }
        else
            return FALSE;
    }

    /**
     * sendVcodeSMS method
     * @param $phone_no Phone no. to which SMS will be sent
     * @return boolean
     * @access private
     */
    private function sendInvitationSMS($phone_no, $inviterName) {
        $url = $this->shortenUrl('http://www.sourcefuse.com'); // TODO :: Format the url to track the user request

        $message = "$inviterName has invited you to join Clickin. Please click on the following link: $url";
        $from = Configure::read('Twilio.number');
        //$phone_no = '+918699177539'; // TODO :: Hard coding the phone no. for trial account

        return $this->Twilio->sms($from, $phone_no, $message); // TODO :: Add email reporting, in case SMS to this no. fails
    }

    /**
     * shortenUrl method
     * @param string $url Url to be shortened
     * @return string
     * @access private
     */
    private function shortenUrl($url) {
        if (!empty($url)) {
            // Call the shorten function of the Shorten Component to create a short url
            // Using tinyurl service to shorten urls, since it's free
            $shortUrl = $this->Shorten->shorten($url, 'tinyurl');

            // Check if the url was successfully shortened
            if ($shortUrl != 'error')
                return $shortUrl;
            else
                return false;
        }
        else {
            return false;
        }
    }

    /**
     * changevisibility method
     * this function is used as web service to make relationship public or private for any user...
     * @return \CakeResponse
     * access public
     */
    public function changevisibility() {
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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->relationship_id) && isset($request_data->public):

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

                        if ($this->User->saveRelationshipData($request_data, $data)) {

                            // save notification..
                            $this->Notification->saveNotification($request_data, $data, 'relationvisibility');

                            // send push notification to partner on iphone/android as per device token and type..
                            $params = array(
                                'fields' => array('device_token', 'device_type', 'name', 'is_enable_push_notification'),
                                'conditions' => array(
                                    'relationships.partner_id' => $data[0]['User']['_id'],
                                    'relationships.id' => new MongoId($request_data->relationship_id)
                            ));
                            $partner_data = $this->User->find('first', $params);

                            $visibility = $request_data->public == 'true' ? 'public' : 'private';
                            $message = trim($data[0]['User']['name']) . ' changed relationship ' . $visibility . ' with you.';

                            /* if(isset($partner_data['User']['is_enable_push_notification']) && $partner_data['User']['is_enable_push_notification'] == 'yes' 
                              || !isset($partner_data['User']['is_enable_push_notification']))
                              { */
                            $device_type = $partner_data['User']['device_type'];
                            $device_token = $partner_data['User']['device_token'];
                            $payLoadData = array(
                                'Tp' => "RV",
                                /* 'Notfication Text' => ($request_data->public == 'true') ? trim($data[0]['User']['name']) . " has made your relationship public" :
                                  trim($data[0]['User']['name']) . " has made your relationship private", */
                                'chat_message' => ($request_data->public == 'true') ? trim($data[0]['User']['name']) . " made your relationship visible on their profile" :
                                        trim($data[0]['User']['name']) . " has hidden your relationship on their profile"
                            );
                            $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);
                            //}

                            $success = true;
                            $status = SUCCESS;
                            $message = ($request_data->public == 'true') ? 'Relationship successfully made public.' : 'Relationship successfully made private.';
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
            // relationship id blank in request
            case!empty($request_data) && empty($request_data->relationship_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Relationship id cannot be blank.';
                break;
            // public value blank in request
            case!empty($request_data) && empty($request_data->public):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Public value cannot be blank.';
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
     * this function is used as web service to make relationship stutus accepted/rejected or null for any user...
     * @return \CakeResponse
     */
    public function updatestatus() {
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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->relationship_id) && isset($request_data->accepted):

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

                        if ($this->User->saveRelationshipData($request_data, $data)) {

                            // save notification..
                            $this->Notification->saveNotification($request_data, $data, 'relationstatus');

                            // send push notification to partner on iphone/android as per device token and type..
                            $params = array(
                                'fields' => array('device_token', 'device_type', 'is_enable_push_notification'),
                                'conditions' => array(
                                    'relationships.partner_id' => $data[0]['User']['_id'],
                                    'relationships.id' => new MongoId($request_data->relationship_id)
                            ));
                            $partner_data = $this->User->find('first', $params);

                            $status = ($request_data->accepted == 'true') ? 'accepted' : 'rejected';
                            $message = trim($data[0]['User']['name']) . ' ' . $status . ' relationship with you.';

                            /* if(isset($partner_data['User']['is_enable_push_notification']) && $partner_data['User']['is_enable_push_notification'] == 'yes' 
                              || !isset($partner_data['User']['is_enable_push_notification']))
                              { */
                            $device_type = $partner_data['User']['device_type'];
                            $device_token = $partner_data['User']['device_token'];


                            $payLoadData = array(
                                'Tp' => ($request_data->accepted == 'true') ? "CRA" : "CRR",
                                /* 'Notfication Text' => ($request_data->accepted == 'true') ? trim($data[0]['User']['name']) . " is now Clickin' with you!" :
                                  "Oops! Sorry - " . trim($data[0]['User']['name']) . " has rejected your request", */
                                'chat_message' => ($request_data->accepted == 'true') ? trim($data[0]['User']['name']) . " is now Clickin' with you!" :
                                        "Oops! Sorry - " . trim($data[0]['User']['name']) . " has rejected your request"
                            );
                            // getting clicks and user_clicks..
                            $clicks = 100;
                            $user_clicks = 100;
                            $data = $this->User->findUser($request_data->phone_no);
                            if (count($data[0]["User"]["relationships"]) > 0) {
                                foreach ($data[0]["User"]["relationships"] as $urKey => $urVal) {
                                    if ($request_data->relationship_id == (string) $data[0]["User"]["relationships"][$urKey]['id']) {
                                        $clicks = $data[0]["User"]["relationships"][$urKey]['clicks'];
                                        $user_clicks = $data[0]["User"]["relationships"][$urKey]['user_clicks'];
                                        break;
                                    }
                                }
                            }
                            // set additional values in payload data array when user accepted request...
                            if ($request_data->accepted == 'true') {
                                $payLoadData += array(
                                    "Rid" => $request_data->relationship_id,
                                    "phn" => $data[0]['User']['phone_no'],
                                    "pid" => (string) $data[0]['User']['_id'],
                                    "pname" => trim($data[0]['User']['name']),
                                    //"ppic" => $data[0]['User']['user_pic'],
                                    "pQBid" => $data[0]['User']['QB_id'],
                                    "clk" => $clicks,
                                    "uclk" => $user_clicks
                                );
                            }
                            $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);
                            //}

                            $success = true;
                            $status = SUCCESS;
                            $message = ($request_data->accepted == 'true') ? 'Relationship successfully accepted.' : 'Relationship successfully rejected.';
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
            // relationship id blank in request
            case!empty($request_data) && empty($request_data->relationship_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Relationship id cannot be blank.';
                break;
            // accepted value blank in request
            case!empty($request_data) && empty($request_data->accepted):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Accepted value cannot be blank.';
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
        return $this->response($status, $out);
    }

    /**
     * fetchusersbyname method
     * this function is used to fetch users by name..
     * 
     * @return \CakeResponse
     */
    public function fetchusersbyname() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        $dataArray = array();
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;

            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->name):

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

                        // Fetch user records by name..
                        $dataArray = $this->User->fetchUsersByName($request_data->name, $data);

                        if (count($dataArray) > 0) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Search result have found users with same name.';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'Search result have no user(s) with same name.';
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
            // Name blank in request
            case!empty($request_data) && empty($request_data->name):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Name cannot be blank.';
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
            "users" => $dataArray
        );

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * deleterelationship method
     * this function is used to soft delete relationship of two users..
     * 
     * @return \CakeResponse
     * @access public
     */
    public function deleterelationship() {
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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->relationship_id):

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

                        $request_data->deleted = 'yes';
                        if ($this->User->saveRelationshipData($request_data, $data)) {

                            // save notification..
                            $this->Notification->saveNotification($request_data, $data, 'relationdelete');

                            // send push notification to partner on iphone/android as per device token and type..
                            $params = array(
                                'fields' => array('device_token', 'device_type', 'name', 'is_enable_push_notification'),
                                'conditions' => array(
                                    'relationships.partner_id' => $data[0]['User']['_id'],
                                    'relationships.id' => new MongoId($request_data->relationship_id)
                            ));
                            $partner_data = $this->User->find('first', $params);

                            $message = trim($data[0]['User']['name']) . ' has ended their relationship with you.';

                            /* if(isset($partner_data['User']['is_enable_push_notification']) && $partner_data['User']['is_enable_push_notification'] == 'yes' 
                              || !isset($partner_data['User']['is_enable_push_notification']))
                              { */
                            $device_type = $partner_data['User']['device_type'];
                            $device_token = $partner_data['User']['device_token'];
                            $payLoadData = array(
                                'Tp' => "RD",
                                //'Notfication Text' => trim($data[0]['User']['name']) . " has called things off ! Sorry !",
                                'chat_message' => trim($data[0]['User']['name']) . " has called things off ! Sorry !"
                            );
                            $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);
                            //}

                            $success = true;
                            $status = SUCCESS;
                            $message = 'Relationship successfully deleted.';
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
            // relationship id blank in request
            case!empty($request_data) && empty($request_data->relationship_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Relationship id cannot be blank.';
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
     * resetnewuserandpartnerflag method
     * this function is used for reset flags for new user and/or new partner..
     * 
     * @return \CakeResponse
     * @access public
     */
    public function resetnewuserandpartnerflag() {
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
                    } elseif ($request_data->is_new_partner == '' && $request_data->is_new_clickin_user == '') {
                        $success = false;
                        $status = BAD_REQUEST;
                        $message = 'Request cannot be empty.';
                    } elseif ($request_data->is_new_partner != '' && $request_data->relationship_id == '') {
                        $success = false;
                        $status = BAD_REQUEST;
                        $message = 'Relationship id cannot be blank.';
                    } elseif ($request_data->relationship_id != '' && $request_data->is_new_partner == '') {
                        $success = false;
                        $status = BAD_REQUEST;
                        $message = 'Is new partner cannot be blank.';
                    } else {

                        if ($request_data->is_new_clickin_user != '') {
                            if (count($data) > 0) {
                                $data[0]["User"]["is_new_clickin_user"] = $request_data->is_new_clickin_user;
                                $userData = $data[0];
                                if ($this->User->save($userData)) {
                                    $success = true;
                                    $status = SUCCESS;
                                    $message = 'Flag successfully reset.';
                                } else {
                                    $success = false;
                                    $status = ERROR;
                                    $message = 'There was a problem in processing your request';
                                }
                            }
                        }
                        if ($request_data->is_new_partner != '') {
                            if ($this->User->updateRelationshipDataOfPartnerById($request_data, $data)) {
                                $success = true;
                                $status = SUCCESS;
                                $message = 'Flag successfully reset.';
                            } else {
                                $success = false;
                                $status = ERROR;
                                $message = 'There was a problem in processing your request';
                            }
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

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

}
