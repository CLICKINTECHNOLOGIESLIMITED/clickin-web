<?php

/**
 * ContentController class
 *
 * @uses          AppController
 * @package       mongodb
 * @subpackage    mongodb.samples.controllers
 */
class ContentController extends AppController {

    /**
     * name property
     *
     * @var string 'name'
     * @access public
     */
    public $name = 'content';

    /**
     * use data model property
     * @var array
     * @access public 
     */
    var $uses = array('Content','User');
    
    /**
     * fetchlist method
     * this function is used to fetch list of content..
     * @return \CakeResponse
     * @access public
     */
    public function fetchlist() 
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
                
                        // Fetch the content records
                        $dataArray = $this->Content->find('all');                        
                        
                        if (count($dataArray)>0) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'content(s) found.';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'Content not found.';
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
            $contentArray = array();
            $rCount = 0;
            foreach ($dataArray as $notkey => $notVal) {
                $contentArray[$rCount]['_id'] = $dataArray[$notkey]['Content']['_id'];
                $contentArray[$rCount]['description'] = $dataArray[$notkey]['Content']['description'];
                $contentArray[$rCount]['created']=date('Y-m-d h:i:s', $dataArray[$notkey]['Content']['created']->sec);
                $contentArray[$rCount]['modified'] = date('Y-m-d h:i:s', $dataArray[$notkey]['Content']['modified']->sec);
                $rCount++;
            }
            $out['contents'] = $contentArray;
        }
        
        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

}
    
    
    
    
    