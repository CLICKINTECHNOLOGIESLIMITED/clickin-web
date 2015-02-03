<?php

App::uses('AppController', 'Controller');

class SettingsController extends AppController {

    /**
     * use data model property
     * @var array
     * @access public 
     */
    var $uses = array('User', 'Feedback');
    public $name = 'Settings';

    /**
     * change method
     * this function is used as web service to enable or disable push notification for any user...
     * @return \CakeResponse
     * access public
     */
    public function change() {
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

                        $dataArray = array();
                        $dataArray['User']['_id'] = $data[0]['User']['_id'];
                        if (!empty($request_data->is_enable_push_notification))
                            $dataArray['User']['is_enable_push_notification'] = $request_data->is_enable_push_notification;

                        if ($this->User->save($dataArray)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Settings has been changed.';
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
     * this function is used as web service to change password of any user...
     * @return \CakeResponse
     * access public
     */
    public function changepassword() {
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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->old_password) && !empty($request_data->new_password) && !empty($request_data->confirm_password):

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
                    } elseif (md5($request_data->old_password) != $data[0]['User']['password']) { // User password is matching or not.
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'Password did not match';
                    } elseif (trim($request_data->new_password) == trim($request_data->old_password)) { // New password is not equal to old password.
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'New Password is not equal to old password.';
                    } elseif (trim($request_data->new_password) != trim($request_data->confirm_password)) { // New password is not equal to confirm password.
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'New Password is not equal to confirm password.';
                    } else {

                        $dataArray = array();
                        $dataArray['User']['_id'] = $data[0]['User']['_id'];
                        $dataArray['User']['password'] = md5(trim($request_data->new_password));

                        if ($this->User->save($dataArray)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Settings has been changed.';
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
            // Old Password blank in request
            case!empty($request_data) && empty($request_data->old_password):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Old password cannot be blank.';
                break;
            // New Password blank in request
            case!empty($request_data) && empty($request_data->new_password):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'New password cannot be blank.';
                break;
            // Confirm Password blank in request
            case!empty($request_data) && empty($request_data->confirm_password):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Confirm password cannot be blank.';
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

    // deactivate account - will impact other flow when isActive =  yes or no.
    /**
     * deactivateaccount method
     * this function is used as web service to deactivate account of any user...
     * @return \CakeResponse
     * access public
     */
    public function deactivateaccount() {

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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->reason_type) && !empty($request_data->password):  // && !empty($request_data->other_reason)  && !empty($request_data->email_opt_out):
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
                    } elseif (md5($request_data->password) != $data[0]['User']['password']) { // User password is matching or not.
                        $success = false;
                        $status = UNAUTHORISED;
                        $message = 'Password did not match';
                    } else {

                        $dataArray = array();
                        $dataArray['User']['_id'] = $data[0]['User']['_id'];
                        $dataArray['User']['reason_type'] = $request_data->reason_type;
                        $dataArray['User']['is_active'] = 'no';
                        $dataArray['User']['other_reason'] = !empty($request_data->other_reason) ? trim($request_data->other_reason) : '';
                        $flag = $this->User->save($dataArray);

                        if ($flag) {
                            // send email to user's email address..
                            if (!empty($request_data->email_opt_out) && $request_data->email_opt_out == 'yes') {
                                App::uses('CakeEmail', 'Network/Email');
                                $Email = new CakeEmail('default');
                                $Email->from(array(SUPPORT_SENDER_EMAIL => SUPPORT_SENDER_EMAIL_NAME));
                                $Email->to(strtolower(trim($data[0]['User']['email'])));
                                $Email->subject('Clickin | Account Deactivation');
                                $Email->emailFormat('html');
                                $messageEmail = '';
                                $messageEmail .= "Hi " . trim($data[0]['User']['name']) . ',<br><br> You have deactivated clickin account. You can reactivate your account
                                by signing in again.<br><br>Regards,<br>Clickin\' Team';
                                $Email->send($messageEmail);
                            }

                            $success = true;
                            $status = SUCCESS;
                            $message = 'Your account has been deactivated.';
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
            // Reason type blank in request
            case!empty($request_data) && empty($request_data->reason_type):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Reason type cannot be blank.';
                break;
            // Password blank in request
            case!empty($request_data) && empty($request_data->password):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Password cannot be blank.';
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
     * reportaproblem method
     * this function is used as web service to save problem reported by any user...
     * // report a problem : 'spam & abuse', 'its not working', 'general feedback' 
     * @return \CakeResponse
     * access public
     */
    public function reportaproblem() {
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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->problem_type):
                // && !empty($request_data->comment):
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

                        $dataArray = array();
                        $dataArray['Feedback']['problem_type'] = $request_data->problem_type;
                        if (!empty($request_data->spam_or_abuse_type))
                            $dataArray['Feedback']['spam_or_abuse_type'] = $request_data->spam_or_abuse_type;
                        $dataArray['Feedback']['comment'] = trim($request_data->comment);
                        $dataArray['Feedback']['user_id'] = (string) $data[0]['User']['_id'];

                        if ($this->Feedback->save($dataArray)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Your problem has been saved.';
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
            // Problem type blank in request
            case!empty($request_data) && empty($request_data->problem_type):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Problem type cannot be blank.';
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
     * forgotpassword method
     * this function is used recover password using email registered on app.
     * 
     * @return \CakeResponse
     */
    public function forgotpassword() {
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
            case!empty($request_data) && !empty($request_data->email):
                // && !empty($request_data->comment):
                // Check if phone no exists
                $data = $this->User->findUserByEmail(trim($request_data->email));

                // Check if record exists
                if (count($data) != 0) {

                    $userEmail = $request_data->email;
                    $userName = trim($data[0]['User']['name']);
                    $recoveryStr = $this->generateRandomString(12);

                    $dataArray = array();
                    $dataArray['User']['_id'] = $data[0]['User']['_id'];
                    $dataArray['User']['recoverystr'] = $recoveryStr;

                    if ($this->User->save($dataArray)) {

                        $recoveryLink = HOST_ROOT_PATH . 'recover/password/id:' . (string) $data[0]['User']['_id'] . '/rid:' . $recoveryStr;
                        // send mail to user..
                        $messageEmail = "Hi $userName,<br><br>
                        You can recover your password by using this link : <a href='$recoveryLink'>$recoveryLink</a><br><br>
                        Regards,<br>Support Team.";
                        App::uses('CakeEmail', 'Network/Email');
                        $Email = new CakeEmail();
                        $Email->config('default');
                        $Email->from(array(SUPPORT_SENDER_EMAIL => SUPPORT_SENDER_EMAIL_NAME));
                        $Email->to($userEmail);
                        $Email->addCc(SUPPORT_RECEIVER_EMAIL);
                        $Email->subject(SUPPORT_SENDER_EMAIL_NAME . ' | Recover Password');
                        $Email->emailFormat('html');
                        $Email->send($messageEmail);

                        $success = true;
                        $status = SUCCESS;
                        $message = 'Password has been sent to your email.';
                        //$message = 'A password recovery link has been sent to your email.';
                    } else {

                        $success = false;
                        $status = ERROR;
                        $message = 'There was a problem in processing your request';
                    }
                }
                // Return false if record not found
                else {
                    $success = false;
                    $status = UNAUTHORISED;
                    $message = 'Email not registered.';
                }
                break;
            // User Email blank in request
            case!empty($request_data) && empty($request_data->email):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User Email cannot be blank.';
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
     * generateRandomString method
     * 
     * @param integer $length
     * @return string
     */
    function generateRandomString($length = 8) {

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    /**
     * changelastseentime method
     * this function is used as web service to set last seen time of any user...
     * 
     * @return \CakeResponse
     * access public
     */
    public function changelastseentime() {
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

                        $dataArray = array();
                        $dataArray['User']['_id'] = $data[0]['User']['_id'];
                        // when user will logout then we will get this variable for reset device details.
                        if (isset($request_data->reset_device) && $request_data->reset_device == 'yes') {
                            $dataArray['User']['device_type'] = '';
                            $dataArray['User']['device_token'] = '';
                        }
                        $dataArray['User']['last_seen_time'] = new MongoDate();

                        if ($this->User->save($dataArray)) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Last seen time has been saved.';
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

?>
