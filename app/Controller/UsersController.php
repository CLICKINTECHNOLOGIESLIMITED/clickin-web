<?php

/**
 * UsersController class
 *
 * @uses          AppController
 * @package       mongodb
 * @subpackage    mongodb.samples.controllers
 */
class UsersController extends AppController {

    /**
     * name property
     *
     * @var string 'Users'
     * @access public
     */
    public $name = 'Users';

    /**
     * This controller uses User model
     *
     * @var array
     */
    public $uses = array('User', 'Notification');

    /**
     * components property
     * 
     * @var array
     * @access public
     */
    public $components = array('Quickblox', 'Twilio.Twilio', 'Facebook', 'Sms', 'Pushnotification', 'CakeS3.CakeS3' => array(
            's3Key' => AMAZON_S3_KEY,
            's3Secret' => AMAZON_S3_SECRET_KEY,
            'bucket' => BUCKET_NAME,
            'endpoint' => END_POINT
    ));

    /**
     * createuser method
     *
     * @return void
     * @access public
     */
    public function createuser() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                CakeLog::write('info', "\nbad request : phone no : " . $request_data->phone_no, array('clickin'));
                $message = 'Wrong request method.';
                break;
            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->phone_no): // !empty($request_data->name) &&  && !empty($request_data->device_token)
                // Check if record with same phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Checking environment variable, to determine application environment
                $env = getenv('CAKE_ENV');

                // Set a new vcode for production only
                // Placing this code before checking valid user, since the user can be invited before registering
                if (!$env || $env == 'production') {
                    // Generate a new vcode
                    $vcode = rand(1000, 9999);
                } else {
                    $vcode = 1234;
                }

                // Insert new record if record does not exist
                if (count($data) == 0) {

                    if (isset($request_data->device_token) && $request_data->device_token != '') {
                        // unset same device token from other user's data..
                        $this->unsetSameDeviceToken($request_data->device_token);
                    } else {
                        $request_data->device_token = ''; // Storing device token
                    }

                    $this->User->create();
                    // Append vcode and verified flag to request data
                    $request_data->vcode = $vcode;
                    $request_data->verified = false;
                    $request_data->user_token = $this->generateUUID();                // Random string for user's uuid
                    $request_data->unread_notifications_count = 0;                    // Setting the counter at 0 when user created
                    // If Partner_no is posted then add
                    if(isset($request_data->partner_no)) {
                        $request_data->partner_no = "8585998092";        // Setting the number of partner entered
                    }
                    
                    if ($this->User->save($request_data)) {
                        // Send vcode sms on production environment only
                        if (!$env || $env == 'production') {
                            // Send VCode through SMS, after user has been created
                            $vcodeSMS = $this->sendVcodeSMS($request_data->phone_no, $vcode);
                        }

                        $success = true;
                        $status = SUCCESS;
                        $message = 'User Created';
                    } else {
                        $success = false;
                        $status = ERROR;
                        CakeLog::write('info', "\nphone no " . $request_data->phone_no . " did not added.", array('clickin'));
                        $message = 'There was a problem in processing your request';
                    }
                }
                // Check if user is registering with same phone no. from another device
                // Update device token if user not verified yet
                //&& $data[0]['User']['device_token'] != $request_data->device_token 
                elseif (count($data) != 0 && $data[0]['User']['verified'] === false && $data[0]['User']['phone_no'] == $request_data->phone_no) {

                    // Update user record and update device token
                    if (isset($request_data->device_token) && $request_data->device_token != '') {
                        // unset same device token from other user's data..
                        $this->unsetSameDeviceToken($request_data->device_token);
                    } else {
                        $request_data->device_token = ''; // Storing device token
                    }

                    //$data[0]['User']['name'] = $request_data->name;
                    $data[0]['User']['vcode'] = $vcode;
                    $data[0]['User']['user_token'] = $this->generateUUID(); // Random string for user's uuid

                    $this->User->clear();
                    if ($this->User->save($data[0]['User'])) {
                        // Send vcode sms on production environment only
                        if (!$env || $env == 'production') {
                            // Send VCode through SMS, after user has been created
                            $vcodeSMS = $this->sendVcodeSMS($request_data->phone_no, $vcode);
                        }

                        // save user's other details in other user's relationships..
                        $userList = $this->User->findUserRelationshipsByNo($request_data->phone_no);
                        if (count($userList) > 0)
                            $this->User->updateRelationshipDataOfPartner($request_data, $userList);

                        // save user's details in other user's follower and following details..
                        $userFollowerList = $this->User->findUserFollowerFollowingsByNo($request_data->phone_no, array('follower', 'following'));
                        if (count($userFollowerList) > 0)
                            $this->User->updateFollowerFollowingDataOfPartner($request_data, $userFollowerList);

                        $success = true;
                        $status = SUCCESS;
                        $message = 'User Found. Device token updated.';
                    } else {
                        $success = false;
                        $status = ERROR;
                        CakeLog::write('info', "\n phone no : " . $request_data->phone_no . " did not save.", array('clickin'));
                        $message = 'There was a problem in processing your request';
                    }
                }
                // Return false if record already exists
                /*
                else {
                    $success = false;
                    $status = ERROR;
                    CakeLog::write('info', "\ncreateuser : record already exists : User phone no : " . $request_data->phone_no, array('clickin'));
                    $message = 'User with same phone no. already exists.';
                }*/
                // Return True if existing user is trying to get in 
                // Send him the Vcode via SMS
                else {
                    // Sets the Vcode & verified status to false   
                    // Update user record and set new vcode
                    $user_data = array(
                        '_id' => $data[0]['User']['_id'],
                        'verified' => false,
                        'vcode' => $vcode
                    );

                    if ($this->User->save($user_data)) {
                      // Send vcode sms on production environment only
                      if (!$env || $env == 'production') {
                          // Send VCode through SMS, after user has been created
                          $vcodeSMS = $this->sendVcodeSMS($request_data->phone_no, $vcode);
                      }                    
                    }

                    $success = true;
                    $status = SUCCESS;
                    $message = 'User Found';                       
                }      
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                CakeLog::write('info', "\nphone no not in request : ", array('clickin'));
                $message = 'Phone no. cannot be blank.';
                break;
            /* / Name blank in request
              case!empty($request_data) && empty($request_data->name):
              $success = false;
              $status = BAD_REQUEST;
              $message = 'Name cannot be blank.';
              break;
              // Device Token blank in request
              case!empty($request_data) && empty($request_data->device_token):
              $success = false;
              $status = BAD_REQUEST;
              CakeLog::write('info', "\ncreateuser : bad request : User device token is missing : ", array('clickin'));
              $message = 'Device Token cannot be blank.';
              break; */
            // Parameters not found in request
            case empty($request_data):
                $success = false;
                $status = BAD_REQUEST;
                CakeLog::write('info', "\nbad request other : ", array('clickin'));
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
     * generateUUID method
     *
     * @return string
     * @access private
     */
    private function generateUUID() {
        return substr(str_shuffle(md5(time())), 0, 20);
    }

    /**
     * sendVcodeSMS method
     *
     * @return string
     * @access private
     */
    private function sendVcodeSMS($phone_no, $vcode) {
        $message = Configure::read('WEBSMS_TEMPLATE') . " $vcode";
        return $this->Sms->sendSms($phone_no, $message);
    }

    /**
     * verifycode method
     *
     * @return void
     * @access public
     */
    public function verifycode() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and phone no and vcode are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->vcode) && !empty($request_data->device_type): //  && !empty($request_data->device_token)
                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check vcode entered is valid
                    /*
                    if ($data[0]['User']['verified'] === TRUE) {
                        $success = false;
                        $status = ERROR;
                        $message = 'User already verified';
                    } else*/ if ($request_data->vcode != $data[0]['User']['vcode']) { // Vcode is not valid
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'Verification Code is invalid';
                    } /* elseif ($request_data->device_token != $data[0]['User']['device_token']) { // Device Token is not same as in database
                      $success = false;
                      $status = UNAUTHORISED;
                      $message = 'Device Token is invalid';
                      } */ else { // Vcode is valid
                        // Update user record and set verified as true
                        $user_data = array(
                            '_id' => $data[0]['User']['_id'],
                            'badge_count' => 0,
                            'verified' => true
                                //'vcode' => ''
                        );

                        if (isset($request_data->device_type))
                            $user_data = array_merge($user_data, array('device_type' => $request_data->device_type));
                        if (isset($request_data->device_token)) {
                            $user_data = array_merge($user_data, array('device_token' => $request_data->device_token));
                            // unset same device token from other user's data..
                            $this->unsetSameDeviceToken($request_data->device_token);
                        }

                        $this->User->clear();
                        // Create user on QuickBlox and fetch the details
                        /* $QB_details = $this->Quickblox->createQBUser($data[0]['User']['_id'], $data[0]['User']['user_token'], $data[0]['User']['phone_no']);

                          // Check if QuickBlox ID was returned
                          if ($QB_details->user->id !== NULL) {
                          // Add the QuickBlox id into the User details array
                          $user_data['QB_id'] = $QB_details->user->id;
                         */
                        // Update only verified and vcode column in table / collection
                        if ($this->User->save($user_data)) {

                            // set qb id in relationship data of this user..
                            /* $request_data->partner_QB_id = $user_data['QB_id'];
                              // save user's other details in other user's relationships..
                              $userList = $this->User->findUserRelationshipsByNo($request_data->phone_no);
                              if(count($userList)>0)
                              $this->User->updateRelationshipDataOfPartner($request_data, $userList); */

                            $success = true;
                            $status = SUCCESS;
                            $message = 'User Verified';
                            $user_token = $data[0]['User']['user_token'];
                            $user_id = $data[0]['User']['_id'];
                            $partner_no = $data[0]['User']['partner_no'];
                            $qb_id = $user_data['QB_id'];
                        } else {
                            $success = false;
                            $status = ERROR;
                            CakeLog::write('info', "\nrecord did not save : phone no : " . $request_data->phone_no, array('clickin'));
                            $message = 'There was a problem in verifying the user';
                        }
                        /* } else {
                          $success = false;
                          $status = ERROR;
                          $message = 'There was a problem in verifying the user';
                          } */
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    CakeLog::write('info', "\nrecord not exists : phone no : " . $request_data->phone_no . " vcode : " . $request_data->vcode, array('clickin'));
                    $message = 'Phone no. not registered.';
                }
                break;
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            // Verification Code blank in request
            case!empty($request_data) && empty($request_data->vcode):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Verification Code cannot be blank.';
                break;
            /* / Device Token blank in request
              case!empty($request_data) && empty($request_data->device_token):
              $success = false;
              $status = BAD_REQUEST;
              $message = 'Device Token cannot be blank.';
              break; */
            // Device Type blank in request
            case!empty($request_data) && empty($request_data->device_type):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Device Type cannot be blank.';
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
            $out['user_token'] = $user_token;
            $out['QB_id'] = $qb_id;
            $out['partner_no'] = $partner_no;
            $out['user_id'] = $user_id;
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * resendvcode method
     *
     * @return void
     * @access public
     */
    public function resendvcode() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and phone no and vcode are present
            case!empty($request_data) && !empty($request_data->phone_no) : // && !empty($request_data->device_token)
                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check user is already verified
                    if ($data[0]['User']['verified'] === TRUE) {
                        $success = false;
                        $status = ERROR;
                        $message = 'User already verified';
                    } /* elseif (!empty($request_data->device_token) && $request_data->device_token!='' && $request_data->device_token != $data[0]['User']['device_token']) { // Device Token is not same as in database
                      $success = false;
                      $status = UNAUTHORISED;
                      $message = 'Device Token is invalid';
                      CakeLog::write('info', "\n dt invalid.. " . $request_data->phone_no, array('clickin'));
                      } */ else {
                        // Checking environment variable, to determine application environment
                        $env = getenv('CAKE_ENV');
                        CakeLog::write('info', "\n vcode resend sms flow... " . $request_data->phone_no, array('clickin'));
                        // Set a new vcode for production only
                        if (!$env || $env == 'production') {
                            // Generate a new vcode
                            $vcode = rand(1000, 9999);
                        } else {
                            $vcode = 4321;
                        }

                        // Update user record and set new vcode
                        $user_data = array(
                            '_id' => $data[0]['User']['_id'],
                            'verified' => false,
                            'vcode' => $vcode
                        );

                        if ($this->User->save($user_data)) {
                            // Send vcode sms on production environment only
                            if (!$env || $env == 'production') {
                                // Send new VCode through SMS, after vcode has been reset for the user
                                $vcodeSMS = $this->sendVcodeSMS($data[0]['User']['phone_no'], $vcode);
                            }

                            $success = true;
                            $status = SUCCESS;
                            $message = 'Vcode resent';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'There was a problem in resending the code';
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
            // Phone no. blank in request
            case!empty($request_data) && empty($request_data->phone_no):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. cannot be blank.';
                break;
            /* / Device Token blank in request
              case!empty($request_data) && empty($request_data->device_token):
              $success = false;
              $status = BAD_REQUEST;
              $message = 'Device Token cannot be blank.';
              break; */
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
     * insertemail method
     *
     * @return void
     * @access public
     */
    public function insertemail() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and email, password, phone_no and user_token are present
            case!empty($request_data) && !empty($request_data->email) && !empty($request_data->password) && !empty($request_data->user_token) && !empty($request_data->phone_no):
                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check uuid entered is valid
                    /* if ($data[0]['User']['verified'] === false) {
                      $success = false;
                      $status = UNAUTHORISED;
                      $message = 'User not verified';
                      } else */
                    if ($request_data->user_token != $data[0]['User']['user_token']) { // User Token is not valid
                        CakeLog::write('info', "\nUser token data : cur: " . $request_data->user_token . " app: " . $data[0]['User']['user_token'], array('clickin'));

                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is invalid';
                    } /* elseif ($this->User->checkEmailExists($request_data->email)) {
                      $success = false;
                      $status = ERROR;
                      $message = 'Email already exists';
                      } */ else { // User Token is valid
                        // Update user record and set email and password
                        $user_data = array(
                            '_id' => $data[0]['User']['_id'],
                            'email' => trim($request_data->email),
                            'password' => md5(trim($request_data->password)),
                            'verified' => true,
                            'vcode' => '',
                            'badge_count' => 0,
                            'is_new_clickin_user' => 'yes',
                            'is_active' => 'yes'
                        );


                        $QB_details = $this->Quickblox->createQBUser($data[0]['User']['_id'], $data[0]['User']['user_token'], $data[0]['User']['phone_no']);

                        // Check if QuickBlox ID was returned
                        if ($QB_details->user->id !== NULL) {
                            // Add the QuickBlox id into the User details array
                            $user_data['QB_id'] = $QB_details->user->id;
                            CakeLog::write('info', "\nUser qb id : " . $user_data['QB_id'], array('clickin'));

                            if ($this->User->save($user_data)) {

                                // set qb id in relationship data of this user..
                                $request_data->partner_QB_id = $user_data['QB_id'];
                                // save user's other details in other user's relationships..
                                $userList = $this->User->findUserRelationshipsByNo($request_data->phone_no);
                                if (count($userList) > 0)
                                    $this->User->updateRelationshipDataOfPartner($request_data, $userList);

                                // add following entry of default user.
                                $this->addFollowingUser($data[0]['User']['phone_no']);

                                $success = true;
                                $status = SUCCESS;
                                $message = 'Email updated';
                            } else {
                                $success = false;
                                $status = ERROR;
                                CakeLog::write('info', "\nphone no : " . $request_data->phone_no . " did not add.", array('clickin'));
                                $message = 'There was a problem in verifying the user';
                            }
                        } else {
                            $success = false;
                            $status = ERROR;
                            CakeLog::write('info', "\nphone no : " . $request_data->phone_no . " did not add on quickbox.", array('clickin'));
                            $message = 'There was a problem in verifying the user.';
                        }
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    CakeLog::write('info', "\nphone no : " . $request_data->phone_no . " did not add.", array('clickin'));
                    $message = 'Phone no. not registered.';
                }
                break;
            // Email blank in request
            case!empty($request_data) && empty($request_data->email):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Email cannot be blank.';
                break;
            // Password blank in request
            case!empty($request_data) && empty($request_data->password):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Password cannot be blank.';
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

    /**
     * changepassword method
     *
     * @return void
     * @access public
     */
    public function changepassword() {

        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and old password, new passwors, phone_no and user_token are present
            case!empty($request_data) && !empty($request_data->new_password) && !empty($request_data->old_password) && !empty($request_data->user_token) && !empty($request_data->phone_no):
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
                    } elseif (md5($request_data->old_password) != $data[0]['User']['password']) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'Wrong Password';
                    } else { // User Token is valid
                        // Update user record and change password
                        $user_data['_id'] = $data[0]['User']['_id'];
                        $user_data['password'] = md5($request_data->new_password);

                        if ($this->User->save($user_data)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Password updated';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'There was a problem in verifying the user';
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
            // Email blank in request
            case!empty($request_data) && empty($request_data->new_password):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'New password cannot be blank.';
                break;
            // Password blank in request
            case!empty($request_data) && empty($request_data->old_password):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Old password cannot be blank.';
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

    /**
     * updateprofile method
     *
     * @return void
     * @access public
     */
    public function updateprofile() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and gender, dob, user_pic, phone_no and user_token are present
            case!empty($request_data) && //($request_data->gender == 1 || $request_data->gender == 0) && !empty($request_data->dob) && !empty($request_data->user_pic) &&
            !empty($request_data->user_token) && !empty($request_data->phone_no) && !empty($request_data->email) && !empty($request_data->first_name) && !empty($request_data->last_name):
                // !empty($request_data->city) && !empty($request_data->country) 
                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check user_token entered is valid
                    /* if ($data[0]['User']['verified'] === false) {
                      $success = false;
                      $status = UNAUTHORISED;
                      $message = 'User not verified';
                      } else */
                    if ($request_data->user_token != $data[0]['User']['user_token']) { // User Token is not valid
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is invalid';
                    } elseif ($this->User->checkEmailExists(trim($request_data->email), $request_data->phone_no)) {
                        $success = false;
                        $status = ERROR;
                        $message = 'Email already exists';
                    } else { // User Token is valid
                        //Generating image file from binary data
                        if (!empty($request_data->user_pic)) {
                            $image = $this->saveimage($request_data->user_pic, $data[0]['User']['_id']);
                            if ($image) {
                                $imageUrl = HOST_ROOT_PATH . 'images' . DS . 'user_pics' . DS . $data[0]['User']['_id'] . '/profile_pic.jpg';

                                // image on S3..
                                $user_path = WWW_ROOT . "images/user_pics/" . $data[0]['User']['_id'];
                                $fullpath = $user_path . "/profile_pic.jpg";

                                $fullpathThumb = $user_path . "/thumb_profile_pic.jpg";
                                $this->CakeS3->putObject($fullpathThumb, 'user_pics' . DS . $data[0]['User']['_id'] . '_thumb_profile_pic.jpg', $this->CakeS3->permission('public_read_write'));

                                $this->CakeS3->deleteObject('user_pics' . DS . $data[0]['User']['_id'] . '_profile_pic.jpg');
                                $response = $this->CakeS3->putObject($fullpath, 'user_pics' . DS . $data[0]['User']['_id'] . '_profile_pic.jpg', $this->CakeS3->permission('public_read_write'));
                                $imageUrl = $response['url'];

                                $user_data['user_pic'] = $imageUrl;
                                $request_data->partner_pic = $imageUrl;
                            }
                        }

                        // Update user record and set dob, gender and user_pic
                        $user_data['_id'] = $data[0]['User']['_id'];
                        if (isset($request_data->dob) && $request_data->dob != '')
                            $user_data['dob'] = trim($request_data->dob);
                        if (isset($request_data->gender) && $request_data->gender != '')
                            $user_data['gender'] = $request_data->gender;
                        $user_data['city'] = trim($request_data->city);
                        $user_data['country'] = trim($request_data->country);
                        $user_data['email'] = trim($request_data->email);
                        $user_data['name'] = trim($request_data->first_name) . ' ' . trim($request_data->last_name);

                        // if request have access token then we will get & store fb user detail in users collection.
                        if (isset($request_data->fb_access_token) && $request_data->fb_access_token != '') {
                            $fbDetail = $this->Facebook->getUserInfo($request_data->fb_access_token);
                            if (count($fbDetail) > 0) {
                                $user_data['fb_name'] = $fbDetail['name'];
                                $user_data['fb_id'] = $fbDetail['id'];
                                // it will be used to check other fb friends got push notifications or not..
                                $user_data['fb_signed_up'] = 1;
                            }
                        }

                        if ($this->User->save($user_data)) {

                            $request_data->name = $request_data->first_name . ' ' . $request_data->last_name;
                            // save user's other details in other user's relationships..
                            $userList = $this->User->findUserRelationshipsByNo($request_data->phone_no);
                            if (count($userList) > 0)
                                $this->User->updateRelationshipDataOfPartner($request_data, $userList);

                            // save user's details in other user's follower and following details..
                            $userFollowerList = $this->User->findUserFollowerFollowingsByNo($request_data->phone_no, array('follower', 'following'));
                            if (count($userFollowerList) > 0)
                                $this->User->updateFollowerFollowingDataOfPartner($request_data, $userFollowerList);

                            // send PN and notifications to partners
                            if (!empty($request_data->user_pic) && !empty($request_data->profile_image_change) && $request_data->profile_image_change == 'yes') {
                                // Get relationships for the user which have been accepted by partner
                                $user_data = $this->User->findRelationshipsByType($request_data->user_token, $request_data->phone_no, TRUE);
                                // Get relationships for the user which is pending on partner
                                $user_data_pending = $this->User->findRelationshipsByType($request_data->user_token, $request_data->phone_no, NULL);
                                //print_r($user_data);
                                //print_r($user_data_pending);
                                if (count($user_data) > 0) {
                                    $this->sendPNOnProfilePicUpdate($request_data, $data, $user_data);
                                }
                                if (count($user_data_pending) > 0) {
                                    $this->sendPNOnProfilePicUpdate($request_data, $data, $user_data_pending);
                                }
                            }

                            $success = true;
                            $status = SUCCESS;
                            $message = 'Profile updated';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'There was a problem in verifying the user';
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
            /* // Gender blank in request
              case!empty($request_data) && $request_data->gender == '':
              $success = false;
              $status = BAD_REQUEST;
              $message = 'Select gender';
              break;
              // Date of Birth blank in request
              case!empty($request_data) && empty($request_data->dob):
              $success = false;
              $status = BAD_REQUEST;
              $message = 'Date of Birth cannot be blank.';
              break; */
            // Firstname blank in request
            case!empty($request_data) && empty($request_data->first_name):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'First name cannot be blank.';
                break;
            // Lastname blank in request
            case!empty($request_data) && empty($request_data->last_name):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Last name cannot be blank.';
                break;
            /* / City blank in request
              case!empty($request_data) && empty($request_data->city):
              $success = false;
              $status = BAD_REQUEST;
              $message = 'City cannot be blank.';
              break;
              // Country blank in request
              case!empty($request_data) && empty($request_data->country):
              $success = false;
              $status = BAD_REQUEST;
              $message = 'Country cannot be blank.';
              break; */
            // Email blank in request
            case!empty($request_data) && empty($request_data->email):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Email cannot be blank.';
                break;
            /* / User Pic blank in request
              case!empty($request_data) && empty($request_data->user_pic):
              $success = false;
              $status = BAD_REQUEST;
              $message = 'Select a user image';
              break; */
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

    public function sendPNOnProfilePicUpdate($request_data, $data, $userData) {
        foreach ($userData as $udkey => $udvalue) {
            foreach ($udvalue['User']['relationships'] as $udvalue1) {
                $params = array(
                    'fields' => array('unread_notifications_count', '_id', 'device_type', 'device_token'),
                    'conditions' => array(
                        '_id' => new MongoId($udvalue1['partner_id'])
                ));
                $results = $this->User->find('first', $params);

                $results['User']['unread_notifications_count'] += 1;
                // Creating a new record for Partner's data in Users collection
                if ($this->User->save($results)) {
                    // Fetch the new partner's details
                    $new_partner = $this->User->findUser($udvalue1['phone_no']);
                    // Create a new entry for the notification to be shown to the new user
                    $new_partner_notification = $this->Notification->create();
                    $new_partner_notification['Notification']['user_id'] = $new_partner[0]['User']['_id'];
                    $new_partner_notification['Notification']['notification_msg'] = trim($data[0]['User']['name']) . " has changed their profile picture";
                    $new_partner_notification['Notification']['type'] = 'updateprofilepic'; // invite
                    $new_partner_notification['Notification']['read'] = false;
                    $new_partner_notification['Notification']['update_user_id'] = $data[0]['User']['_id'];
                    $new_partner_notification['Notification']['update_name'] = trim($data[0]['User']['name']);
                    $new_partner_notification['Notification']['update_user_pic'] = $data[0]['User']['user_pic'];
                    // Saving the new notification for the user
                    $this->Notification->save($new_partner_notification);

                    $message = trim($data[0]['User']['name']) . " has changed their profile picture";
                    $device_type = $new_partner[0]['User']['device_type'];
                    $device_token = $new_partner[0]['User']['device_token'];
                    $payLoadData = array(
                        'Tp' => "Upp",
                        'chat_message' => $message,
                        'phone_no' => $request_data->phone_no
                    );
                    if ($device_type != '' && $device_token != '') {
                        $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);
                    }
                }
            }
        }
    }

    /**
     * saveimage method
     *
     * @param String $image_data Image data to be saved
     * 	@param Integer $user_id User ID to which image belongs
     * 	@return Boolean
     * 	@access public
     */
    private function saveimage($image, $user_id) {
        // Check if image data is set
        if (isset($image)) {

            // Setting image path
            $user_path = WWW_ROOT . "images/user_pics/" . $user_id;

            // Create path if not found
            if (!file_exists($user_path)) {
                mkdir($user_path);
                // Provide required permissions to folder
                chmod($user_path, 0777);
            }
            $fullpath = $user_path . "/profile_pic.jpg";
            $fullpathThumb = $user_path . "/thumb_profile_pic.jpg";

            // Remove file if already exists
            if (file_exists($fullpath)) {
                unlink($fullpath);
            }

            // Put binary data into image file
            $png_file = fopen($fullpath, 'wb');
            fwrite($png_file, base64_decode($image));
            fclose($png_file);

            // making thumb for this image...
            $this->resize_image($fullpath, $fullpathThumb, 150, 150, false);

            // Check file type created
            // Remove if not JPG
            if (exif_imagetype($fullpath) != 2) {
                unlink($fullpath);
                return false;
            }

            // If image save successfully,
            return true;
        }

        return false;
    }

    /**
     * resize_image method
     * This function is used to make resized image from main image.
     * @param string $file
     * @param string $filePath
     * @param integer $w
     * @param resize_image $h
     * @param boolean $crop
     * @return string
     */
    function resize_image($file, $filePath, $w, $h, $crop = FALSE) {
        list($width, $height) = getimagesize($file);
        $r = $width / $height;
        if ($crop) {
            if ($width > $height) {
                $width = ceil($width - ($width * abs($r - $w / $h)));
            } else {
                $height = ceil($height - ($height * abs($r - $w / $h)));
            }
            $newwidth = $w;
            $newheight = $h;
        } else {
            if ($w / $h > $r) {
                $newwidth = $h * $r;
                $newheight = $h;
            } else {
                $newheight = $w / $r;
                $newwidth = $w;
            }
        }
        $src = imagecreatefromjpeg($file);
        $dst = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        imagejpeg($dst, $filePath);
    }

    /**
     * signin method
     *
     * @return void
     * @access public
     */
    public function signin() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and phone no / email, password and device token are present
            case!empty($request_data) && (!empty($request_data->phone_no) || !empty($request_data->email)) && !empty($request_data->password) && !empty($request_data->device_type): // && !empty($request_data->device_token) 

                if (!empty($request_data->phone_no)) {
                    // Check if phone no exists
                    $data = $this->User->findUser($request_data->phone_no);
                } else {
                    // Check if email exists
                    $data = $this->User->findUserByEmail($request_data->email);
                    // Set phone no in request object, in case of login with email
                    if (isset($data[0]['User']['phone_no']) && $data[0]['User']['phone_no'] != '') {
                        $request_data->phone_no = $data[0]['User']['phone_no'];
                    }
                }

                // Check if record exists
                if (count($data) != 0) {
                    /* / Check user is active or not.
                      if (isset($data[0]['User']['is_active']) && $data[0]['User']['is_active'] == 'no') {
                      $success = false;
                      $status = UNAUTHORISED;
                      $message = 'User not active';
                      } else */
                    if ($data[0]['User']['verified'] !== TRUE) { // Check user is verified
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } elseif (md5($request_data->password) != $data[0]['User']['password']) { // Wrong password
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'Wrong password';
                    } else { // Credentials are valid
                        // update detail in users collection..
                        $params = array(
                            'fields' => array('_id', 'device_token', 'device_type'),
                            'conditions' => array('phone_no' => $request_data->phone_no),
                        );
                        // Find records matching phone no.
                        $results = $this->User->find('first', $params);

                        if (isset($data[0]['User']['is_active']) && $data[0]['User']['is_active'] == 'no')
                            $results['User']['is_active'] = 'yes';

                        // Checking if phone is same as the device registered in database
                        if (isset($request_data->device_token) && $request_data->device_token != $data[0]['User']['device_token']) { // Match phone on successful login
                            $device_registered = false;

                            // Update user record and update device token
                            if (isset($request_data->device_token) && $request_data->device_token != '') {
                                // unset same device token from other user's data..
                                $this->unsetSameDeviceToken($request_data->device_token);
                                $results['User']['device_token'] = $request_data->device_token;
                            } else {
                                $results['User']['device_token'] = '';
                            }

                            $this->User->clear();
                            $results['User']['device_type'] = $request_data->device_type;
                            $this->User->save($results);
                        } else {

                            $this->User->clear();
                            $this->User->save($results);

                            // unset same device token from other user's data..
                            $this->unsetSameDeviceToken($request_data->device_token, $request_data->phone_no);

                            $device_registered = true;
                        }

                        $user_token = $data[0]['User']['user_token'];
                        $phone_no = $data[0]['User']['phone_no'];
                        $qb_id = $data[0]['User']['QB_id'];
                        $user_id = $data[0]['User']['_id'];
                        $user_pic = $data[0]['User']['user_pic'];
                        $user_name = $data[0]['User']['name'];

                        // add default follow user entry for this user...
                        $this->addFollowingUser($data[0]['User']['phone_no']);

                        $success = true;
                        $status = SUCCESS;
                        $message = 'User logged in.';
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'User not registered.';
                }
                break;
            // Phone no. / Email blank in request
            case!empty($request_data) && !array_filter(array($request_data->email, $request_data->phone_no)):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone no. / Email cannot be blank.';
                break;
            // Password blank in request
            case!empty($request_data) && empty($request_data->password):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Password cannot be blank.';
                break;
            /* / Device Token blank in request
              case!empty($request_data) && empty($request_data->device_token):
              $success = false;
              $status = BAD_REQUEST;
              $message = 'Device Token cannot be blank.';
              break; */
            // Device Type blank in request
            case!empty($request_data) && empty($request_data->device_type):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Device Type cannot be blank.';
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
            $out['user_token'] = $user_token;
            $out['device_registered'] = $device_registered;
            $out['phone_no'] = $phone_no;
            $out['user_id'] = $user_id;
            $out['QB_id'] = $qb_id;
            $out['user_name'] = $user_name;
            $out['user_pic'] = $user_pic;
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * unsetSameDeviceToken method
     * this function is used to unset same device token when any new user signup/signin with same device token.
     * 
     * @param string $device_token
     * @param string $curr_phone_no
     */
    public function unsetSameDeviceToken($device_token, $curr_phone_no = '') {
        // update device token in users collection..
        if ($curr_phone_no != '')
            $conditionArr = array(
                '$and' => array(
                    array('device_token' => $device_token),
                    array('phone_no' => array('$ne' => $curr_phone_no))
                )
            );
        else
            $conditionArr = array('device_token' => $device_token);

        $params = array(
            'fields' => array('_id', 'device_token'),
            'conditions' => $conditionArr
        );
        // Find records matching phone no.
        $userList = $this->User->find('all', $params);

        if ($device_token != '' && count($userList) > 0) {
            foreach ($userList as $urKey => $urVal) {
                $userList[$urKey]["User"]["device_token"] = '';
                $userList[$urKey]["User"]["device_type"] = '';
                $this->User->save($userList[$urKey]);
            }
        }
    }

    public function addFollowingUser($followingUserPhoneNo) {
        $followerUserPhoneNo = DEFAULT_FOLLOW_PHONE_NO; // default user.
        $followerUserData = $this->User->findUser($followerUserPhoneNo);

        //$followingUserPhoneNo = '+913333333333'; // will be logged in user.
        $followingUserArr = $this->User->findUser($followingUserPhoneNo);

        $following_user_count = 0;
        // checking user follow already or not..
        if (count($followingUserArr) > 0) {
            foreach ($followingUserArr[0]["User"]["following"] as $urKey => $urVal) {
                if ($followingUserArr[0]["User"]["following"][$urKey]['phone_no'] == $followerUserPhoneNo) {
                    $following_user_count = 1;
                    break;
                }
            }
        }

        // if not following then add entry for follow..
        if ($following_user_count == 0 && count($followingUserPhoneNo) > 0) {
            $rel_id = new MongoId();
            // Create data for following user
            $followee_data = array(
                '_id' => $rel_id,
                'phone_no' => $followerUserData[0]['User']['phone_no'],
                'followee_id' => $followerUserData[0]['User']['_id'],
                'followee_name' => trim($followerUserData[0]['User']['name']),
                'followee_pic' => $followerUserData[0]['User']['user_pic'],
                'accepted' => TRUE
            );

            $followingCount = count($followingUserArr);
            if ($followingCount >= 0) {
                $followingUserArr[0]["User"]["following"][] = $followee_data;
                $this->User->save($followingUserArr[0]);
            }

            // add logged in user detail to follower list of default user.
            // Create data for follower user
            $follower_data = array(
                '_id' => $rel_id,
                'phone_no' => $followingUserArr[0]['User']['phone_no'],
                'follower_id' => $followingUserArr[0]['User']['_id'],
                'follower_name' => trim($followingUserArr[0]['User']['name']),
                'follower_pic' => $followingUserArr[0]['User']['user_pic'],
                'accepted' => TRUE
            );

            $followerCount = count($followerUserData);
            if ($followerCount >= 0) {
                $followerUserData[0]["User"]["follower"][] = $follower_data;
                $this->User->save($followerUserData[0]);
            }
        }
    }

    /**
     * checkregisteredfriends method
     *
     * @return void
     * @access public
     */
    public function checkregisteredfriends() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');

        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Request cannot be empty.';
                break;
            // Request is valid and phone no., user token and phone nos. from contact list are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->phone_nos):
                // Check if phone no exists
                $data = $this->User->findUser($request_data->phone_no);

                // Check if record exists
                if (count($data) != 0) {
                    // Check user is verified
                    if ($data[0]['User']['verified'] !== TRUE) {
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User not verified';
                    } elseif ($request_data->user_token != $data[0]['User']['user_token']) { // Wrong password
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'User Token is invalid';
                    } else { // Credentials are valid
                        // Check phone nos. in database from the list of phone nos. received
                        $friendsRegistered = $this->User->checkPhoneNosRegistered($request_data->phone_nos, $request_data->phone_no);

                        $success = true;
                        $status = SUCCESS;
                        $message = 'Phone nos. listed.';
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
                $message = 'Device Token cannot be blank.';
                break;
            // Phone nos. from contact list blank in request
            case!empty($request_data) && empty($request_data->phone_nos):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Phone nos. cannot be blank.';
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

        // Send phone nos list if success
        if ($success) {
            $out['phone_nos'] = $friendsRegistered;
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * fetchprofileinfo method
     *
     * @return void
     * @access public
     */
    public function fetchprofileinfo() {
        // Fetch the query data from the url
        $query_data = $this->request->query;

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
                        $phone_no = $request_phone_no;
                        $following_user_count = 0;
                        $following_request_count = 0;
                        $follow_id = '';

                        // Fetch the profile phone no. being searched, assign user's phone no. otherwise
                        if (isset($this->request->named['profile_phone_no']) && $this->request->named['profile_phone_no'] !== null) {
                            $phone_no = $this->request->named['profile_phone_no'];

                            // Check if the logged in user is already following the searched phone no
                            $followingUserArr = $this->User->findFollowing($request_user_token, $phone_no);
                            if (count($followingUserArr) > 0) {
                                foreach ($followingUserArr[0]["User"]["following"] as $urKey => $urVal) {
                                    if ($followingUserArr[0]["User"]["following"][$urKey]['phone_no'] == $phone_no &&
                                            $followingUserArr[0]["User"]["following"][$urKey]['accepted'] === true) {
                                        $follow_id = (string) $followingUserArr[0]["User"]["following"][$urKey]['_id'];
                                        $following_user_count = 1;
                                        break;
                                    } else if ($followingUserArr[0]["User"]["following"][$urKey]['phone_no'] == $phone_no &&
                                            $followingUserArr[0]["User"]["following"][$urKey]['accepted'] === null) {
                                        $follow_id = (string) $followingUserArr[0]["User"]["following"][$urKey]['_id'];
                                        $following_request_count = 1;
                                        break;
                                    }
                                }
                            }
                        }

                        // Fetch details of the searched phone no.
                        $user_details = $this->User->fetchUserProfile($phone_no);

                        // Adding the following user result to the response array
                        $user_details['User']['is_following'] = $following_user_count;
                        $user_details['User']['is_following_requested'] = $following_request_count;
                        $user_details['User']['follow_id'] = $follow_id;

                        // Remove the unread_notification_count value from User array and place it outside
                        // Doing this to avoid sending other user's notifications count
                        unset($user_details['User']['unread_notifications_count']);

                        $success = true;
                        $status = SUCCESS;
                        $message = 'User details found.';
                    }
                }
                // Return false if record not found
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
            $out['user'] = $user_details['User'];
            $out['unread_notifications_count'] = $data[0]['User']['unread_notifications_count'];
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * fetchprofilerelationships method
     *
     * @return void
     * @access public
     */
    public function fetchprofilerelationships() {
        // Fetch the query data from the url
        $query_data = $this->request->query;
        $relationshipArr = array();
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
            // Check the phone no. in headers is set
            case $this->request->named['profile_phone_no'] === null :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Profile phone no. is required.';
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
                        // Fetch the profile phone no. being searched, assign user's phone no. otherwise
                        $phone_no = $this->request->named['profile_phone_no'];
                        // Check if the logged in user is already relationships the searched phone no
                        $relationshipArr = $this->User->findPublicRelationships($phone_no);


                        // get relationship with current user..
                        $params = array(
                            'fields' => array('_id', 'relationships'),
                            'conditions' => array('phone_no' => $request_phone_no, 'relationships.phone_no' => $this->request->named['profile_phone_no'])
                        );

                        $relation_status = '';
                        // Find records matching phone no.
                        $resultRelation = $this->User->find('first', $params);
                        if (count($resultRelation) > 0) {
                            foreach ($resultRelation["User"]["relationships"] as $urKey => $urVal) {
                                if ($resultRelation["User"]["relationships"][$urKey]['phone_no'] == $this->request->named['profile_phone_no']) {
                                    $accepted = $resultRelation["User"]["relationships"][$urKey]['accepted'];
                                    $deleted = $resultRelation["User"]["relationships"][$urKey]['deleted'];
                                    if ($deleted == 'yes') {
                                        $relation_status = '';
                                        break;
                                    }
                                    if ($accepted === null)
                                        $relation_status = 'requested';
                                    elseif ($accepted === true)
                                        $relation_status = 'accepted';
                                    elseif ($accepted === false)
                                        $relation_status = '';
                                    break;
                                }
                            }
                        }

                        $success = true;
                        $status = SUCCESS;
                        $message = count($relationshipArr) > 0 ? 'User details found.' : 'User details not found.';
                    }
                }
                // Return false if record not found
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
            $out['relationships'] = count($relationshipArr) > 0 ? $relationshipArr : array();
            $out['relation_status'] = $relation_status;
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * fetchprofilefollow method
     *
     * @return void
     * @access public
     */
    public function fetchprofilefollow() {
        // Fetch the query data from the url
        $query_data = $this->request->query;

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
                        $phone_no = $request_phone_no;
                        $isOwner = 1;
                        // Fetch the profile phone no. being searched, assign user's phone no. otherwise
                        if (isset($this->request->named['profile_phone_no']) && $this->request->named['profile_phone_no'] !== null) {
                            $phone_no = $this->request->named['profile_phone_no'];
                            $data = $this->User->findUser($phone_no);
                            if ($this->request->named['profile_phone_no'] == $phone_no)
                                $isOwner = 1;
                            else
                                $isOwner = 0;
                        }
                        // Check if the logged in user is already following the searched phone no
                        $followingArr = $this->User->getAllFollowing($data[0]['User']['_id']);
                        // Check if the logged in user is already follower the searched phone no
                        $followerArr = $this->User->getAllFollower($data[0]['User']['_id']);

                        // remove non accepted users from both list... 
                        if (count($followingArr) > 0)
                            $followingArr = $this->User->removeNonFollowFollowing($followingArr, 'following', $isOwner);
                        if (count($followerArr) > 0)
                            $followerArr = $this->User->removeNonFollowFollowing($followerArr, 'follower');

                        // set isFollowing and accepted value in follower list..
                        if (count($followingArr) > 0)
                            $followerArr = $this->User->addFollowingDetailInFollower($followerArr, $followingArr);

                        $success = true;
                        $status = SUCCESS;
                        $message = 'User details found.';
                    }
                }
                // Return false if record not found
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
            $out['follower'] = (isset($followerArr['User']['follower'])) ? $followerArr['User']['follower'] : array();
            $out['following'] = (isset($followingArr['User']['following'])) ? $followingArr['User']['following'] : array();
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * followupdatestatus method
     * this function is used to accept/reject followship of any user..
     * 
     * @access public
     * @return \CakeResponse
     */
    public function followupdatestatus() {

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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->follow_id) && isset($request_data->accepted):

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

                        // update follower data...
                        if ($this->User->saveFollowerFollowingData($request_data, $data)) {

                            // save notification..
                            $this->Notification->saveNotification($request_data, $data, 'followstatus');

                            $success = true;
                            $status = SUCCESS;
                            $message = ($request_data->accepted == 'true') ? 'Follow request successfully accepted.' : 'Follow request successfully rejected.';
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
            // Follow id blank in request
            case!empty($request_data) && empty($request_data->follow_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Follow id cannot be blank.';
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
        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * unfollowuser method
     * this function is used to unfollow any user..
     * 
     * @access public
     * @return \CakeResponse
     */
    public function unfollowuser() {

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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->follow_id) && isset($request_data->following):

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

                        // update follower data...
                        $following = $request_data->following == 'true' ? 'following' : 'follower';
                        $request_data->accepted = FALSE;
                        if ($this->User->saveFollowerFollowingData($request_data, $data, $following)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'User successfully unfollowed.';
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
            // Follow id blank in request
            case!empty($request_data) && empty($request_data->follow_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Follow id cannot be blank.';
                break;
            // following value blank in request
            case!empty($request_data) && empty($request_data->following):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Following value cannot be blank.';
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

    public function mapreduce() {
        $phone_no_arr = array('+911111111111', '+911234567890', '+912222222222', '+913333333333');
        $phone_nos = "['+911111111111', '+911234567890', '+912222222222', '+913333333333']";
        $result = $this->User->find('all', (array('conditions' => array('phone_no' => array('$in' => $phone_no_arr)))));
        $log = $this->User->getDataSource()->getLog(false, false);
        debug($log);
        pr($result);
        die;

        $phone_nos = "['+911111111111', '+911234567890', '+912222222222', '+913333333333']";
        $map = new MongoCode("function() { var phone_nos = " . $phone_nos . "; for(var i = 0; i < phone_nos.length; i++) { if(phone_nos[i] == this.phone_no) { emit(this.phone_no, 1); } else { emit(phone_nos[i], 0); } } }");
        //$map = new MongoCode("function() { if(this.verified == true) emit(this._id, this.phone_no) }");
        $reduce = new MongoCode("function(k, vals) { " .
                "var str = ''; " .
                //"str += ' | ' + k;".
                //"var sum = 0;".
                "for (var i in vals) {" .
                "if(vals[i] == 1)" .
                "str = vals[i];" .
                "}" .
                "return str" .
                "}"
        );

        $params = array(
            "mapreduce" => "users",
            "map" => $map,
            "reduce" => $reduce,
            "query" => array(
            //"count" => array('$gte' => 1),
            ),
            'out' => 'test_mapreduce_users', //must above MongoDB1.8
        );

        $mongo = $this->User->getDataSource();
        $results = $mongo->mapReduce($params);

        pr($results);
        return new CakeResponse(array('body' => json_encode($results), 'type' => 'json'));
    }

    public function testsms() {
        $phone_no = '+919891594731';
        $message = Configure::read('WEBSMS_TEMPLATE') . " 00001111";
        $this->Sms->sendSms($phone_no, $message);
        exit;
    }

    public function posttofb() {
        // configure basic setup for fb..
        $config = array(
            'appId' => FACEBOOK_CLIENT_ID,
            'secret' => FACEBOOK_CLIENT_SECRET,
            'cookie' => true
        );
        $this->facebook = new Facebook($config);
        $access_token = 'CAAFmZBOidnYkBAEWJzwmaTSryHti6cFCMWlrxtOKKEZAeF7smCOu5nAaLJeLqB2kdEOvQ1MwyVOpTxnyetMMOwWUqYlfZA1rhTwbPV4C9DHIP5ZClzrCi5cB3Q2i1JXxGt5quBrhqVqjmdFsTayyb3EIMpusfl5l3vhapCioo2IZAwZCfA76sRjjuL4PFe2QfzyD3Vq45HWlZA3nsJjyTnaIZAJBIMn1CYAZD';
        $this->facebook->setAccessToken($access_token);
        $this->facebook->setFileUploadSupport(TRUE);
        $userDetail = $this->facebook->api("/me");
        print_r($userDetail);

        //Create an album
        $album_details = array(
            'message' => 'Clickin is a social app.',
            'name' => 'Clickin'
        );
        $create_album = $this->facebook->api('/me/albums', 'post', $album_details);
        $album_uid = $create_album['id'];

        $params = array(
            //'image' => "@" . WWW_ROOT . "images/user_pics/5371ffff252e0878607d285c/profile_pic.jpg",
            'source' => 'https://qbprod.s3.amazonaws.com/fce755a0f6ef4e5b94afca5fbf8d93c600',
            'message' => 'this is test post...',
        );
        //$postdetails = $this->facebook->api("/me/photos", "post", $params);
        $postdetails = $this->facebook->api("/$album_uid/photos", "post", $params);
        echo '<pre>Photo ID: ' . $postdetails['id'] . '</pre>';
        exit;
    }
    
    public function getpartnerstatus() {
        
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        
        print_r($request_data);
        
        echo $user     = $this->User->findUser($request_data->phone_no);
        echo $partner  = $this->User->findUser($request_data->partnerNo);
        
        
        $success = false;
        $status = UNAUTHORISED;
        $message = 'User not verified';
        
        $out = array(
            "success" => $success,
            "message" => $message
        );
        
        return new CakeResponse(array('body' => json_encode($out), 'type' => 'json'));
    }
    
}
