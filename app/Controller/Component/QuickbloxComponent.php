<?php

App::uses('Component', 'Controller');

class QuickbloxComponent extends Component {

    /**
     * HttpSocket property
     *
     * @access public
     */
    public $HttpSocket;

    /**
     * fetchQBToken method
     *
     * @return QuickBlox token
     * @access private
     */
    private function fetchQBToken() {
        // Initialize the HttpSocket class
        App::uses('HttpSocket', 'Network/Http');
        $this->HttpSocket = new HttpSocket();

        // Set the headers for the request
        $request['header'] = (array('Content-Type' => 'application/json',
            'QuickBlox-REST-API-Version' => '0.1.0'
        ));

        // Fetching the QuickBlox credentials for the request
        $QB_host = Configure::read('QB.host');
        $QB_app_id = Configure::read('QB.app_id');
        $QB_auth_key = Configure::read('QB.auth_key');
        $QB_auth_secret = Configure::read('QB.auth_secret');

        $response = array(
            'host' => Configure::read('QB.host'),
            'app_id' => Configure::read('QB.app_id'),
            'auth_key' => Configure::read('QB.auth_key'),
            'auth_secret' => Configure::read('QB.auth_secret')
        );

        $time = time();
        $nonce = rand();

        // Preparing the request params
        $request_params = "application_id=$QB_app_id&auth_key=$QB_auth_key&nonce=$nonce&timestamp=" . $time;
        // Generate the HMAC-SHA has for the signature in the request from the QB auth secret
        $QB_signature = hash_hmac('sha1', $request_params, $QB_auth_secret);

        // Any data to be sent with the request
        $data = array(
            'application_id' => $QB_app_id,
            'auth_key' => $QB_auth_key,
            'timestamp' => $time,
            'nonce' => $nonce,
            'signature' => $QB_signature
        );

        try {
            // Make the request to get the QuickBlox token
            $results = $this->HttpSocket->post(
                    $QB_host . 'session.json', json_encode($data), $request
            );

            // Parse the response fetched from Quickblox
            $response_body = json_decode($results->body);
            // Return the token retrieved
            return $response_body->session->token;
        } catch (Exception $e) {
            CakeLog::write('info', "\nfetchQBToken : " . $e->getMessage(), array('clickin'));
            throw $e->getMessage();
        }
    }

    /**
     * loginQBUser method
     *
     * @return boolean
     * @access private
     */
    private function loginQBUser() {
        // Fetch the QuickBlox token to make further requests
        $qb_token = $this->fetchQBToken();

        // Fetching the QuickBlox credentials for the request
        $QB_host = Configure::read('QB.host');

        // Prepare headers for the request
        $request['header'] = (array('Content-Type' => 'application/json',
            'QuickBlox-REST-API-Version' => '0.1.0',
            'QB-Token' => $qb_token));

        // Fetch the QuickBlox login credentials
        $QB_username = Configure::read('QB.email');
        $QB_password = Configure::read('QB.password');

        // Any data to be sent with the request
        $data = array(
            'email' => $QB_username,
            'password' => $QB_password
        );

        try {
            // Try creating a new user on QuickBlox
            $response = $this->HttpSocket->post(
                    $QB_host . 'login.json', json_encode($data), // Sending the data in JSON format
                    $request);

            // Check response code
            // If success return true, else false
            if ($response->code == '202') {
                return $qb_token;
            } else {
                return false;
            }
        } catch (Exception $e) {
            CakeLog::write('info', "\nloginQBUser : " . $e->getMessage(), array('clickin'));
            throw $e->getMessage();
        }
    }

    /**
     * createQBUser method
     *
     * @param string $id Id of the new user to be used as QB login
     * @param string $user_token User Token of the new user to be used as QB password
     * @param int $phone_no Phone no. with which the user is registered to be used as QB email ({$phone_no}@clickin.com)
     * @return QuickBlox token
     * @access public
     */
    public function createQBUser($id, $user_token, $phone_no) {
        // Fetch the QuickBlox token to make further requests
        $qb_token = $this->fetchQBToken();

        // Fetching the QuickBlox credentials for the request
        $QB_host = Configure::read('QB.host');

        // Prepare headers for the request
        $request['header'] = (array('Content-Type' => 'application/json',
            'QuickBlox-REST-API-Version' => '0.1.0',
            'QB-Token' => $qb_token));

        $QB_login = $id; // Using the user id as the QuickBlox login
        $QB_password = $user_token; // TODO :: Using encryption of user token as the QuickBlox password
        $QB_email = $phone_no . '@clickin.com'; // Using the phone no. as the QuickBlox email [{$phone_no}@clickin.com]
        // Any data to be sent with the request
        $data = array(
            'user' => array(
                'login' => $QB_login,
                'password' => $QB_password,
                'email' => $QB_email
            )
        );

        // Prepare the request params
        //$request_params = "user[login]=$QB_login&user[password]=$QB_password&user[email]=$QB_email";

        try {
            // Try creating a new user on QuickBlox
            $response = $this->HttpSocket->post(
                    $QB_host . 'users.json', json_encode($data), $request);

            // Return the data for the new user
            return json_decode($response->body);
        } catch (Exception $e) {
            CakeLog::write('info', "\ncreateQBUser : " . $e->getMessage(), array('clickin'));
            throw $e->getMessage();
        }
    }

    /**
     * fetchChatHistory method
     *
     * @return string JSON encoded array of retrieved chat records
     * @access public
     */
    public function fetchChatHistory() {
        // Fetch the QuickBlox token to make further requests
        $qb_token = $this->fetchQBToken();

        // Fetching the QuickBlox credentials for the request
        $QB_host = Configure::read('QB.host');

        // Fetching the Custom Object class name on Quickblox
        $QB_CustObj = Configure::read('QB.customobject');

        // Prepare headers for the request
        $request['header'] = (array('Content-Type' => 'application/json',
            'QuickBlox-REST-API-Version' => '0.1.0',
            'QB-Token' => $qb_token));

        // Data to be sent with the request
        $data = array(
            'limit' => 20, // No. of records to be fetched
            //'skip' => 7 // No. of items to skip
            'sort_asc' => 'created_at', // Set ascending order of created_at field in Custom Object
        );

        try {
            // Try creating a new user on QuickBlox
            $response = $this->HttpSocket->get(
                    $QB_host . 'data/' . $QB_CustObj . '.json', $data, $request);

            // Return the data for the new user
            return json_decode($response->body);
        } catch (Exception $e) {
            CakeLog::write('info', "\nfetchChatHistory : " . $e->getMessage(), array('clickin'));
            throw $e->getMessage();
        }
    }

    /**
     * deleteChatRecords method
     *
     * @param array $chat_ids IDs of the chat records to be deleted from the QB Custom Object
     * @return string JSON encoded array of deleted chat records
     * @access public
     */
    public function deleteChatRecords($chat_ids) {
        // Check if there were any records to be deleted
        if (count($chat_ids) > 0) {
            // Login the user in QuickBlox to perform write, update or delete operations
            $qb_token = $this->loginQBUser();

            // Fetching the QuickBlox credentials for the request
            $QB_host = Configure::read('QB.host');

            // Fetching the Custom Object class name on Quickblox
            $QB_CustObj = Configure::read('QB.customobject');

            // Prepare headers for the request
            $request['header'] = (array('Content-Type' => 'application/json',
                'QuickBlox-REST-API-Version' => '0.1.0',
                'QB-Token' => $qb_token));

            // Prepare the request params
            $request_params = implode(',', $chat_ids);

            // Data to be sent with the request
            $data = array();

            if ($qb_token) {
                try {
                    // Try creating a new user on QuickBlox
                    $response = $this->HttpSocket->delete(
                            $QB_host . 'data/' . $QB_CustObj . '/' . $request_params . '.json', $data, $request);

                    // Return the data for the new user
                    return json_decode($response->body);
                } catch (Exception $e) {
                    CakeLog::write('info', "\ndeleteChatRecords : " . $e->getMessage(), array('clickin'));
                    throw $e->getMessage();
                }
            } else {
                CakeLog::write('info', "\ndeleteChatRecords : User not logged in into QB.", array('clickin'));
                throw new RuntimeException('User not logged in into QB.');
            }
        } else {
            return false;
        }
    }

}
