<?php

/**
 * NewsfeedController class
 *fetchcommentstars
 * @uses          AppController
 * @package       mongodb
 * @subpackage    mongodb.samples.controllers
 */
class NewsfeedController extends AppController {

    /**
     * name property
     *
     * @var string 'newsfeed'
     * @access public
     */
    public $name = 'newsfeed';

    /**
     * use data model property
     * @var array
     * @access public 
     */
    var $uses = array('User','Notification','Newsfeeds','Chat','Commentstar');
    
    var $components = array('Facebook','Pushnotification');
    
    /**
     * fetchnewsfeeds method
     * this function is used to fetch newsfeeds detail by id..
     * 
     * @return \CakeResponse
     */
    public function fetchnewsfeeds() 
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
                
                        // Check if last newsfeed id is present
                        $last_newsfeed_id = (isset($request_data->last_newsfeed_id)) ? $request_data->last_newsfeed_id : '';
                        // Fetch the newsfeed records, starting from the last newsfeed id, if present
                        $dataArray = $this->Newsfeeds->fetchnewsfeeds($data[0]['User']['_id'], $last_newsfeed_id);                        
                        
                        if (count($dataArray)>0) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'User have newsfeed(s).';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'User don\'t have any newsfeed.';
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
            $newsfeedArray = array();
            $rCount = 0;
            foreach ($dataArray as $notkey => $notVal) {
                $newsfeedArray[$rCount]['_id'] = $dataArray[$notkey]['Newsfeeds']['_id'];
                $newsfeedArray[$rCount]['user_id'] = $dataArray[$notkey]['Newsfeeds']['user_id'];
                $newsfeedArray[$rCount]['newsfeed_msg'] = $dataArray[$notkey]['Newsfeeds']['newsfeed_msg'];
                $newsfeedArray[$rCount]['read'] = $dataArray[$notkey]['Newsfeeds']['read'];
                $newsfeedArray[$rCount]['follower_user_id'] = $dataArray[$notkey]['Newsfeeds']['follower_user_id'];
                
                // set comment / star count in newsfeed...
                if(isset($dataArray[$notkey]['Newsfeeds']['stars_count']))
                    $newsfeedArray[$rCount]['stars_count'] = $dataArray[$notkey]['Newsfeeds']['stars_count'];
                if(isset($dataArray[$notkey]['Newsfeeds']['comments_count']))
                    $newsfeedArray[$rCount]['comments_count'] = $dataArray[$notkey]['Newsfeeds']['comments_count'];
                
                // check starred given by current user in this newsfeed..
                $starredDetailArr = $this->Commentstar->find('first', array('conditions' => array( 
                                                                'user_id' => $data[0]['User']['_id'],
                                                                'newsfeed_id' => $dataArray[$notkey]['Newsfeeds']['_id'],
                                                                'type' => 'star'
                                                            )));
                $newsfeedArray[$rCount]['user_starred'] = (count($starredDetailArr)>0)? '1' : '0';
                
                // check comment given by current user in this newsfeed..
                $commentDetailArr = $this->Commentstar->find('first', array('conditions' => array( 
                                                                'user_id' => $data[0]['User']['_id'],
                                                                'newsfeed_id' => $dataArray[$notkey]['Newsfeeds']['_id'],
                                                                'type' => 'comment'
                                                            )));
                $newsfeedArray[$rCount]['user_commented'] = (count($commentDetailArr)>0)? '1' : '0';
                
                // Find chat detail by chat id...
                $chat_id = $dataArray[$notkey]['Newsfeeds']['chat_id'];
                $chatDetailArr = $this->Chat->find('first', array('conditions' => array( '_id' => $chat_id)));
                $newsfeedArray[$rCount]['chatDetail'] = $chatDetailArr['Chat'];
                
                // get sender detail..
                $senderId = $chatDetailArr['Chat']['userId'];
                $senderDataArr = $this->User->find('first', array('conditions' => array( '_id' => $senderId)));
                $senderDetail = array(
                    '_id'=>$senderDataArr['User']['_id'],
                    'phone_no'=>$senderDataArr['User']['phone_no'],
                    'name'=>$senderDataArr['User']['name'],
                    'user_pic'=>$senderDataArr['User']['user_pic']
                );
                $newsfeedArray[$rCount]['senderDetail'] = $senderDetail;
                $relationshipId = $chatDetailArr['Chat']['relationshipId'];
                
                // get receiver detail..
                $receiverDataArr = $this->User->find('first', array('conditions' => array( 
                    '_id' => new MongoId($chatDetailArr['Chat']['userId']), 'relationships.id' => new MongoId($relationshipId)
                )));
                $releationshipArr = $receiverDataArr['User']['relationships'];
                $receiverUserId = '';
                if(count($releationshipArr)>0)
                {
                    foreach($releationshipArr as $rsArr)
                    {
                        if($relationshipId == (string)$rsArr['id'])
                        {
                           $receiverUserId =  $rsArr['partner_id'];
                           break;
                        }
                    }
                }
                if($receiverUserId !='')
                {
                    $receiverUserDataArr = $this->User->find('first', array('conditions' => array( '_id' =>  new MongoId($receiverUserId))));
                    $receiverUserDetail = array(
                        '_id'=>$receiverUserDataArr['User']['_id'],
                        'phone_no'=>$receiverUserDataArr['User']['phone_no'],
                        'name'=>$receiverUserDataArr['User']['name'],
                        'user_pic'=>$receiverUserDataArr['User']['user_pic']
                    );
                    $newsfeedArray[$rCount]['receiverDetail'] = $receiverUserDetail;
                }
                
                // add latest 3 comments of this newsfeed..
                $threeCommentsArr = $this->Commentstar->find('all', array(
                                                            'conditions' => array( 
                                                                'newsfeed_id' => $dataArray[$notkey]['Newsfeeds']['_id'],
                                                                'type' => 'comment'
                                                            ),
                                                            'order' => array('_id' => -1),
                                                            'limit' => 3,
                                                            ));
                $threeCommentsArray = array();
                if(count($threeCommentsArr)>0) {
                    foreach($threeCommentsArr as $tcArr) {
                        $threeCommentsArray[] = $tcArr['Commentstar'];
                    }
                    $threeCommentsArray = array_reverse($threeCommentsArray);
                }
                $newsfeedArray[$rCount]['commentArray'] = $threeCommentsArray;
                
                // return 5 users detail who gave starred on this newsfeed..
                $fiveStarredArr = $this->Commentstar->find('all', array(
                                                            'fields' => array('user_name'),
                                                            'conditions' => array( 
                                                                'newsfeed_id' => $dataArray[$notkey]['Newsfeeds']['_id'],
                                                                'type' => 'star'
                                                            ),
                                                            'order' => array('_id' => -1),
                                                            'limit' => 5,
                                                            ));
                $fiveStarredArray = array();
                if(count($fiveStarredArr)>0) {
                    foreach($fiveStarredArr as $fsArr) {
                        $fiveStarredArray[] = $fsArr['Commentstar'];
                    }
                }
                $newsfeedArray[$rCount]['starredArray'] = $fiveStarredArray;
                
                $newsfeedArray[$rCount]['created']=date('Y-m-d h:i:s', $dataArray[$notkey]['Newsfeeds']['created']->sec);
                $newsfeedArray[$rCount]['modified'] = date('Y-m-d h:i:s', $dataArray[$notkey]['Newsfeeds']['modified']->sec);
                $rCount++;
            }
            $out['newsfeedArray'] = $newsfeedArray;
        }
        
        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }
    
    /**
     * view method
     * this function is used to fetch newsfeed detail by id..
     * 
     * @return \CakeResponse
     */
    public function view() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        
        $success = false;
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;

            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->newsfeed_id) && !empty($request_data->phone_no) && !empty($request_data->user_token):

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

                        $dataArray = $this->Newsfeeds->find('first', array('conditions' => array('_id' => new MongoId($request_data->newsfeed_id))));
                        //print_r($dataArray);exit;
                        if (count($dataArray) > 0) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Newsfeed has record.';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'Newsfeed has no record.';
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
            // newsfeed id blank in request
            case!empty($request_data) && empty($request_data->newsfeed_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'newsfeed id cannot be blank.';
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
            
            $newsfeedArray = array();
            $rCount = 0;
            $newsfeedArray[$rCount]['_id'] = $dataArray['Newsfeeds']['_id'];
            $newsfeedArray[$rCount]['user_id'] = $dataArray['Newsfeeds']['user_id'];
            $newsfeedArray[$rCount]['newsfeed_msg'] = $dataArray['Newsfeeds']['newsfeed_msg'];
            $newsfeedArray[$rCount]['read'] = $dataArray['Newsfeeds']['read'];
            $newsfeedArray[$rCount]['follower_user_id'] = $dataArray['Newsfeeds']['follower_user_id'];
            
            // set comment / star count in newsfeed...
            if(isset($dataArray['Newsfeeds']['stars_count']))
                $newsfeedArray[$rCount]['stars_count'] = $dataArray['Newsfeeds']['stars_count'];
            if(isset($dataArray['Newsfeeds']['comments_count']))
                $newsfeedArray[$rCount]['comments_count'] = $dataArray['Newsfeeds']['comments_count'];

            // check starred given by current user in this newsfeed..
            $starredDetailArr = $this->Commentstar->find('first', array('conditions' => array( 
                                                            'user_id' => $data[0]['User']['_id'],
                                                            'newsfeed_id' => $dataArray['Newsfeeds']['_id'],
                                                            'type' => 'star'
                                                        )));
            $newsfeedArray[$rCount]['user_starred'] = (count($starredDetailArr)>0)? '1' : '0';

            // check comment given by current user in this newsfeed..
            $commentDetailArr = $this->Commentstar->find('first', array('conditions' => array( 
                                                            'user_id' => $data[0]['User']['_id'],
                                                            'newsfeed_id' => $dataArray['Newsfeeds']['_id'],
                                                            'type' => 'comment'
                                                        )));
            $newsfeedArray[$rCount]['user_commented'] = (count($commentDetailArr)>0)? '1' : '0';
            
            // Find chat detail by chat id...
            $chat_id = $dataArray['Newsfeeds']['chat_id'];
            $chatDetailArr = $this->Chat->find('first', array('conditions' => array( '_id' => $chat_id)));
            $newsfeedArray[$rCount]['chatDetail'] = $chatDetailArr['Chat'];

            // get sender detail..
            $senderId = $chatDetailArr['Chat']['userId'];
            $senderDataArr = $this->User->find('first', array('conditions' => array( '_id' => $senderId)));
            $senderDetail = array(
                '_id'=>$senderDataArr['User']['_id'],
                'phone_no'=>$senderDataArr['User']['phone_no'],
                'name'=>$senderDataArr['User']['name'],
                'user_pic'=>$senderDataArr['User']['user_pic']
            );
            $newsfeedArray[$rCount]['senderDetail'] = $senderDetail;
            $relationshipId = $chatDetailArr['Chat']['relationshipId'];

            // get receiver detail..
            $receiverDataArr = $this->User->find('first', array('conditions' => array( 
                '_id' => new MongoId($chatDetailArr['Chat']['userId']), 'relationships.id' => new MongoId($relationshipId)
            )));
            $releationshipArr = $receiverDataArr['User']['relationships'];
            $receiverUserId = '';
            if(count($releationshipArr)>0)
            {
                foreach($releationshipArr as $rsArr)
                {
                    if($relationshipId == (string)$rsArr['id'])
                    {
                       $receiverUserId =  $rsArr['partner_id'];
                       break;
                    }
                }
            }
            if($receiverUserId !='')
            {
                $receiverUserDataArr = $this->User->find('first', array('conditions' => array( '_id' =>  new MongoId($receiverUserId))));
                $receiverUserDetail = array(
                    '_id'=>$receiverUserDataArr['User']['_id'],
                    'phone_no'=>$receiverUserDataArr['User']['phone_no'],
                    'name'=>$receiverUserDataArr['User']['name'],
                    'user_pic'=>$receiverUserDataArr['User']['user_pic']
                );
                $newsfeedArray[$rCount]['receiverDetail'] = $receiverUserDetail;
            }
            
            
            // add latest 3 comments of this newsfeed..
            $threeCommentsArr = $this->Commentstar->find('all', array(
                                                        'conditions' => array( 
                                                            'newsfeed_id' => $dataArray['Newsfeeds']['_id'],
                                                            'type' => 'comment'
                                                        ),
                                                        'order' => array('_id' => -1),
                                                        'limit' => 3,
                                                        ));
            $threeCommentsArray = array();
            if(count($threeCommentsArr)>0) {
                foreach($threeCommentsArr as $tcArr) {
                    $threeCommentsArray[] = $tcArr['Commentstar'];
                }
                $threeCommentsArray = array_reverse($threeCommentsArray);
            }
            $newsfeedArray[$rCount]['commentArray'] = $threeCommentsArray;

            // return 5 users detail who gave starred on this newsfeed..
            $fiveStarredArr = $this->Commentstar->find('all', array(
                                                        'fields' => array('user_name'),
                                                        'conditions' => array( 
                                                            'newsfeed_id' => $dataArray['Newsfeeds']['_id'],
                                                            'type' => 'star'
                                                        ),
                                                        'order' => array('_id' => -1),
                                                        'limit' => 5,
                                                        ));
            $fiveStarredArray = array();
            if(count($fiveStarredArr)>0) {
                foreach($fiveStarredArr as $fsArr) {
                    $fiveStarredArray[] = $fsArr['Commentstar'];
                }
            }
            $newsfeedArray[$rCount]['starredArray'] = $fiveStarredArray;
            
            
            
            
            $newsfeedArray[$rCount]['created']=date('Y-m-d h:i:s', $dataArray['Newsfeeds']['created']->sec);
            $newsfeedArray[$rCount]['modified'] = date('Y-m-d h:i:s', $dataArray['Newsfeeds']['modified']->sec);
            
            $out['newsfeedArray'] = $newsfeedArray;            
        }

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }

    /**
     * savecommentstar method
     * this function is used to save comment and star.
     * 
     * @return \CakeResponse
     */
    public function savecommentstar()
    {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        //echo '<pre>';print_r($request_data);
        //exit;
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;
            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->newsfeed_id) 
                            && !empty($request_data->type) && ((!empty($request_data->comment) && $request_data->type == 'comment') || 
                            $request_data->type == 'star'):

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
                    
                        // fetch chat id from newsfeed id..
                        $newsfeedDetail = $this->Newsfeeds->find('first', array('conditions' => array('_id' => new MongoId($request_data->newsfeed_id))));
                        
                        if(count($newsfeedDetail)>0)
                        {
                            // add save comment/share detail here...
                            $dataArray['chat_id'] = $newsfeedDetail['Newsfeeds']['chat_id']; 
                            $dataArray['newsfeed_id'] = $request_data->newsfeed_id;
                            $dataArray['type'] =  $request_data->type;
                            if($request_data->type == 'comment')
                                $dataArray['comment'] =  trim($request_data->comment);
                            $dataArray['user_id'] = $data[0]['User']['_id'];
                            $dataArray['user_name'] = trim($data[0]['User']['name']);
                            $dataArray['user_pic'] = $data[0]['User']['user_pic'];

                            // save comment/star..
                            if ($this->Commentstar->saveCommentStar($dataArray)) {

                                $success = true;
                                $status = SUCCESS;
                                $message = ($request_data->type == 'comment') ? 'Comment has been saved.' : 'Star has been saved.';
                            } else {
                                $success = false;
                                $status = ERROR;
                                $message = 'There was a problem in processing your request';
                            }
                        
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
            // newsfeed id blank in request
            case!empty($request_data) && empty($request_data->newsfeed_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Newsfeed id cannot be blank.';
                break;
            // comment blank in request
            case!empty($request_data) && empty($request_data->comment) && ($request_data->type == 'comment'):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Comment cannot be blank.';
                break;
            // type blank in request
            case!empty($request_data) && empty($request_data->type):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Type cannot be blank.';
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
     * unstarrednewsfeed method
     * this function is used to unstarred any newsfeed...
     * 
     * @return \CakeResponse
     */
    public function unstarrednewsfeed()
    {
         // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        //echo '<pre>';print_r($request_data);
        //exit;
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;
            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->newsfeed_id):

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
                        
                        // unstarred here...
                        $dataArray = $this->Commentstar->find('first', array('conditions' => array('newsfeed_id' => $request_data->newsfeed_id, 'type' => 'star', 'user_id' => $data[0]['User']['_id'])));
                        if(count($dataArray)>0) {
                            
                            $this->Commentstar->delete($dataArray['Commentstar']['_id']);
                        
                            // Decrement the star count in the newsfeed
                            $this->Newsfeeds->updateCommentStarCount($request_data->newsfeed_id, -1, 'star');
                        
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Starred has been deleted.';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'Starred did not delete due to error.';
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
            // newsfeed_id blank in request
            case!empty($request_data) && empty($request_data->newsfeed_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Commentstar id cannot be blank.';
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
     * fetchcommentstars method
     * this function is used to fetch comment/star detail by newsfeed id..
     * 
     * @return \CakeResponse
     */
    public function fetchcommentstars() 
    {
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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->newsfeed_id) 
                && !empty($request_data->type):
                
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
                
                        // Check if last id is present
                        $last_id = (isset($request_data->last_id)) ? $request_data->last_id : '';
                        
                        // Fetch the comment/star records, starting from the last id, if present
                        $dataArray = $this->Commentstar->fetchcommentstars($request_data->newsfeed_id, $request_data->type, $last_id);                        
                        
                        if (count($dataArray)>0) {
                            $success = true;
                            $status = SUCCESS;
                            $message = $request_data->type == 'comment' ? 'User have comment(s).' : 'User have star(s).';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = $request_data->type == 'comment' ? 'User don\'t have any comment.' : 'User don\'t have any star.';
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
            // newsfeed id blank in request
            case!empty($request_data) && empty($request_data->newsfeed_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Newsfeed id cannot be blank.';
                break;
            // type blank in request
            case!empty($request_data) && empty($request_data->type):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Type cannot be blank.';
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
            $commentstarArray = array();
            $rCount = 0;
            foreach ($dataArray as $notkey => $notVal) {
                $commentstarArray[$rCount]['_id'] = $dataArray[$notkey]['Commentstar']['_id'];
                $commentstarArray[$rCount]['chat_id'] = $dataArray[$notkey]['Commentstar']['chat_id'];
                $commentstarArray[$rCount]['newsfeed_id'] = $dataArray[$notkey]['Commentstar']['newsfeed_id'];
                $commentstarArray[$rCount]['type'] = $dataArray[$notkey]['Commentstar']['type'];
                $commentstarArray[$rCount]['user_id'] = $dataArray[$notkey]['Commentstar']['user_id'];
                if(isset($dataArray[$notkey]['Commentstar']['comment']))
                    $commentstarArray[$rCount]['comment'] = $dataArray[$notkey]['Commentstar']['comment'];
                
                // set stutus of logged in user with current user who sent starred..                
                if($dataArray[$notkey]['Commentstar']['type'] == 'star' && $dataArray[$notkey]['Commentstar']['user_id'] != (string) $data[0]['User']['_id'])
                {
                    // checking that starred user is follower to current user or not..
                    $commentstarArray[$rCount]['is_user_follower'] = 0;
                    $commentstarArray[$rCount]['is_user_follower_acceptance'] = NULL;
                    if(count($data[0]['User']['follower'])>0)
                    {
                        foreach($data[0]['User']['follower'] as $urKey => $urVal)
                        {
                            if($dataArray[$notkey]['Commentstar']['user_id'] == (string) $data[0]['User']['follower'][$urKey]['follower_id'])
                            {
                                $commentstarArray[$rCount]['is_user_follower'] = 1;
                                $commentstarArray[$rCount]['is_user_follower_acceptance'] = $data[0]['User']['follower'][$urKey]['accepted'];
                                $commentstarArray[$rCount]['user_follow_id'] = $data[0]['User']['follower'][$urKey]['_id'];
                                $commentstarArray[$rCount]['user_follow_phone_no'] = $data[0]['User']['follower'][$urKey]['phone_no'];
                                break;
                            }
                        }
                    }
                
                    // checking that starred user is following to current user or not..
                    $commentstarArray[$rCount]['is_user_following'] = 0;
                    $commentstarArray[$rCount]['is_user_following_acceptance'] = NULL;
                    if(count($data[0]['User']['following'])>0)
                    {
                        foreach($data[0]['User']['following'] as $urKey => $urVal)
                        {
                            if($dataArray[$notkey]['Commentstar']['user_id'] == (string) $data[0]['User']['following'][$urKey]['followee_id'])
                            {
                                $commentstarArray[$rCount]['is_user_following'] = 1;
                                $commentstarArray[$rCount]['is_user_following_acceptance'] = $data[0]['User']['following'][$urKey]['accepted'];
                                $commentstarArray[$rCount]['user_follow_id'] = $data[0]['User']['following'][$urKey]['_id'];
                                $commentstarArray[$rCount]['user_follow_phone_no'] = $data[0]['User']['following'][$urKey]['phone_no'];
                                break;
                            }
                        }
                    }
                    
                    // checking that starred user is in relationship with current user or not..
                    $commentstarArray[$rCount]['is_user_in_relation'] = 0;
                    $commentstarArray[$rCount]['is_user_in_relation_acceptance'] = NULL;
                    if(count($data[0]['User']['relationships'])>0)
                    {
                        foreach($data[0]['User']['relationships'] as $urKey => $urVal)
                        {
                            if($dataArray[$notkey]['Commentstar']['user_id'] == (string) $data[0]['User']['relationships'][$urKey]['partner_id'])
                            {
                                $commentstarArray[$rCount]['is_user_in_relation'] = 1;
                                $commentstarArray[$rCount]['is_user_in_relation_acceptance'] = $data[0]['User']['relationships'][$urKey]['accepted'];
                                break;
                            }
                        }
                    }
                }
                
                $commentstarArray[$rCount]['user_name'] = $dataArray[$notkey]['Commentstar']['user_name'];
                $commentstarArray[$rCount]['user_pic'] = $dataArray[$notkey]['Commentstar']['user_pic'];                
                $commentstarArray[$rCount]['created']= $dataArray[$notkey]['Commentstar']['created'];
                $commentstarArray[$rCount]['modified'] = $dataArray[$notkey]['Commentstar']['modified'];
                $rCount++;
            }
            $out['records'] = $commentstarArray;
        }
        
        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }
    
    public function sendnotification()
    {

        $type = DEVICE_TYPE_IOS;
        //$deviceToken = '46a6bf2257dcb37fa27f3342a37a7d9075a2d99d150cc6943c79de72968c9605';
        $deviceToken = '1d445f9663423b2910b179055effbeb889d75be906ffa4f9dcf38dcb60b5622e';
        $message = 'This is test message..';


        $payLoadData = array(
                                   'Tp' => 'Trade Card',
                                   //'Notfication Text' => 'Dan Qa sent you a Click',
                                   //"chat_message" => $chat_message,
                                   "Rid"  => '53ce7e37cc4bd00433d096ae',
                                   "phn" => '+919891594731',
                                   "pid" => '53ce7d62cc4bd08331d096ad',
                                   "pname" => 'Dan Qa',
                                   //"partner_pic" => '', //'http://api.clickinapp.com/images/user_pics/53ce7d62cc4bd08331d096ad/profile_pic.jpg', 
                                   "pQBid" => 26,
                                   "clk"  => 114,
                                   "uclk" => 73
                               );
        print_r($payLoadData);
        echo $this->Pushnotification->sendMessage($type, $deviceToken, $message, $payLoadData);exit;
    }
    
    public function sendnotificationgcm()
    {
        // generating random image name for tomporary file path.
        $randomImageName = strtotime(date('Y-m-d H:i:s'));
        $fileName = $randomImageName.'.jpg';
        echo $srcImagePath = WWW_ROOT .'images/'.$fileName;
        $fp = fopen($srcImagePath,'w+');
        chmod($srcImagePath, 0777);
        fclose($fp);
        //xvfb-run --server-args="-screen 0, 1024x680x24" ./wkhtmltoimage --use-xserver -f png --quality 83 --javascript-delay 500  http://google.com pawan.png
        $execOptions = "--use-xserver --load-error-handling ignore --crop-w 550 --crop-x 245 --crop-y 0 --quality 100 --javascript-delay 200";
        $url = "http://yahoo.co.in";
        // run wkhtmltoimage for capturing screenshot from url..
        exec("xvfb-run --server-args=\"-screen 0, 1024x680x24\" /usr/local/bin/wkhtmltoimage $execOptions $url $srcImagePath");
        exit;
        
        $type = DEVICE_TYPE_ANDROID;
        $deviceToken = 'APA91bFtNRvg1aTv0QqWoHUeeQqo9h8jDX5czVWcWtczQKhdOlfMFx9_jUFxxaIZuhmIiwjkXAfS1odovGAUVgfbT-I2oFCfxPksHj3LN0toRXawIBLyDRp-niKv-jPbFY7_cu0YKjCDq_OghCNDXiyJwPUr4HZFrxArjEmHwHEIsf5Ox4orf_4';
        $message = 'this is test message of push notification on android..';
        echo $this->Pushnotification->sendMessage($type, $deviceToken, $message); exit;
    }
    
    /**
     * fetchfriendlist method
     * This function is used to fetch fb friend list of any user when we pass access token.
     * 
     * @param string $accessToken
     * @param array $loggedInUserData
     * @return array
     */
    public function fetchfriendlist($accessToken, $loggedInUserData)
    {
        //$accessToken = 'CAAFmZBOidnYkBAGDbEQEC0ZC8zV4t2kv4b2Iyb0g5HVqnEEupv8pBSb9hIiVMIYuHdwnwvvAfZBM8HNZAw62AIDM6j7dsmK04fD7iUHE8TL1hpqO2XZCGFFh9ckfzIeQryB8jHBNWSWtpOBvx4ZAndMZBGoufZAeQDA7sq00b5spaS8ZAV2iugxoXiC0bhxt9ZCCvvoasAGKzwxgZDZD';
        //$fbId = '100001119103030';
        $dataArray = array();
        if($accessToken != '') {
            $friends = $this->Facebook->getfriends($accessToken);
            
            if(count($friends)>0)
            {
                foreach ($friends["data"] as $value) {
                    if(isset($value["installed"]) && $value["installed"] == TRUE) {
                        
                        // get user phone detail by using fb id..
                        $params = array(
                            'fields' => array('_id', 'phone_no', 'name','user_token', 'device_token', 'device_type'),
                            'conditions' => array('fb_id' => $value["id"]),
                            'order' => array('name' => 1)
                        );
                        // Find records matching phone no.
                        $results = $this->User->find('first', $params);
                        
                        if(count($results)>0) {
                            
                            // checking that starred user is following to current user or not..
                            $follow_status = 'notrequested';
                            
                            if(count($loggedInUserData[0]['User']['following'])>0)
                            {
                                foreach($loggedInUserData[0]['User']['following'] as $urKey => $urVal)
                                {
                                    if((string) $results["User"]['_id'] == (string) $loggedInUserData[0]['User']['following'][$urKey]['followee_id'])
                                    {
                                        if($loggedInUserData[0]['User']['following'][$urKey]['accepted'] === true) {
                                            $follow_status = 'accepted';
                                        }
                                        else if($loggedInUserData[0]['User']['following'][$urKey]['accepted'] === false) {
                                            $follow_status = 'rejected';
                                        }
                                        else if($loggedInUserData[0]['User']['following'][$urKey]['accepted'] === null) { 
                                                $follow_status = 'pending';
                                        }
                                        break;
                                    }
                                }
                            }
                        
                            $dataArray[] = array(
                                            'fb_id' => $value["id"],
                                            'fb_name' => $value["name"],
                                            'fb_user_pic_url' => 'http://graph.facebook.com/'.$value["id"].'/picture?type=normal',
                                            'id' => (string) $results["User"]['_id'],
                                            'phone_no' => $results["User"]['phone_no'],
                                            'follow_status' => $follow_status
                                        );
                        }
                    }
                }        
            }
        }     
        return $dataArray;
    }
    
    /**
     * fetchfbfriends method
     * This function is used to get facebook friends by access token of any user. It is a wrapper web service.
     * 
     * @return \CakeResponse
     */
    public function fetchfbfriends() 
    {
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
            case!empty($request_data) && !empty($request_data->phone_no) && !empty($request_data->user_token) && !empty($request_data->access_token):
                
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
                        
                        $dataArray = $this->fetchfriendlist($request_data->access_token, $data);                        
                        
                        if (count($dataArray)>0) {
                            $success = true;
                            $status = SUCCESS;
                            $message = 'User have facebook friend(s).';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'User don\'t have facebook friend.';
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
            // User Token blank in request
            case!empty($request_data) && empty($request_data->user_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'User Token cannot be blank.';
                break;
            // Access Token blank in request
            case!empty($request_data) && empty($request_data->access_token):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Access Token cannot be blank.';
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
            "fbfriends" => $dataArray
        );

        return new CakeResponse(array('status' => $status, 'body' => json_encode($out), 'type' => 'json'));
    }
    
    /**
     * delete method
     * this function is used to delete newsfeed detail by id..
     * 
     * @return \CakeResponse
     */
    public function delete() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        
        $success = false;
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;

            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->newsfeed_id) && !empty($request_data->phone_no) && !empty($request_data->user_token):

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

                        $deleteFlag = FALSE;
                        $dataArray = $this->Newsfeeds->find('first', array('conditions' => array('_id' => new MongoId($request_data->newsfeed_id))));
                        if(count($dataArray)>0) {
                            
                            $this->Commentstar->deleteAll(array('newsfeed_id' => $request_data->newsfeed_id));
                            $this->Newsfeeds->delete(new MongoId($request_data->newsfeed_id));
                        
                            $success = true;
                            $status = SUCCESS;
                            $message = 'Newsfeed has been deleted.';
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'Newsfeed did not delete due to error.';
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
            // newsfeed id blank in request
            case!empty($request_data) && empty($request_data->newsfeed_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'newsfeed id cannot be blank.';
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
     * reportinappropriate method
     * this function is used to report inappropriate about newsfeed by id..
     * 
     * @return \CakeResponse
     */
    public function reportinappropriate() {
        // Fetch the request data in JSON format and convert it into object
        $request_data = $this->request->input('json_decode');
        
        $success = false;
        switch (true) {
            // When request is not made using POST method
            case!$this->request->isPost() :
                $success = false;
                $status = BAD_REQUEST;
                $message = 'Wrong request method.';
                break;

            // Request is valid and phone no and name are present
            case!empty($request_data) && !empty($request_data->newsfeed_id) && !empty($request_data->phone_no) && !empty($request_data->user_token):

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

                        $dataArray = $this->Newsfeeds->find('first', array('conditions' => array('_id' => new MongoId($request_data->newsfeed_id))));
                        if(count($dataArray)>0) 
                        {
                            $userName = trim($data[0]['User']['name']);
                            $newsfeedChatId = $dataArray['Newsfeeds']['chat_id'];
                            $newsfeedId = (string) $dataArray['Newsfeeds']['_id'];
                            $newsfeed_msg = $dataArray['Newsfeeds']['newsfeed_msg'];
                            // mark inappropriate newsfeed..
                            $dataArray['Newsfeeds']['inappropriatedby_user_list'][] =  array(
                                                                                            '_id' => new MongoId(), 
                                                                                            'user_id' => (string) $data[0]['User']['_id'],
                                                                                            'user_name' => (string) $data[0]['User']['name']
                                                                                        );
                            // Insert new inappropriate user data into the inappropriatedby_user_list's details
                            $this->Newsfeeds->save($dataArray);
                            
                            // send mail to admin..
                            $messageEmail = "Hi Admin,<br><br>
                            $userName reported about a inappropriate newsfeed whose detail is given following:<br> 
                            Newsfeed id: $newsfeedId <br>chat id: $newsfeedChatId <br>Newsfeed Message: $newsfeed_msg <br><br>
                            Regards,<br>Support Team.";
                            
                            App::uses('CakeEmail', 'Network/Email');
                            $Email = new CakeEmail();
                            $Email->config('default');
                            $Email->from(array(SUPPORT_SENDER_EMAIL => SUPPORT_SENDER_EMAIL_NAME));
                            $Email->to(SUPPORT_RECEIVER_EMAIL);
                            $Email->subject('Report about Inappropriate Newsfeed');
                            $Email->emailFormat('html');
                            $Email->send($messageEmail);                            
                        
                            // send notification..
                            $notificationArr = $this->Notification->create();
                            $notificationArr['Notification']['_id'] = new MongoId();
                            $notificationArr['Notification']['user_id'] = $dataArray['Newsfeeds']['user_id'];
                            $notificationArr['Notification']['notification_msg'] = 'This post has been reported'; //$userName.' reported your post';
                            $notificationArr['Notification']['type'] = 'report';
                            $notificationArr['Notification']['newsfeed_id'] = $newsfeedId;
                            $notificationArr['Notification']['chat_id'] = $newsfeedChatId;
                            $notificationArr['Notification']['read'] = false;
                            $this->Notification->save($notificationArr);
                            
                            // send push notification...
                            $message = 'This post has been reported'; //$userName.' reported your post';
                            /*if(isset($data[0]['User']['is_enable_push_notification']) && $data[0]['User']['is_enable_push_notification'] == 'yes' 
                                        || !isset($data[0]['User']['is_enable_push_notification']))
                            {*/
                                
                            
                                $paramUser = array(
                                    'fields' => array('_id', 'device_type', 'device_token'),
                                    'conditions' => array('_id' => new MongoId($dataArray['Newsfeeds']['user_id']))
                                );
                                $resultUser = $this->User->find('first', $paramUser);
                            
                            
                                $device_type = $resultUser['User']['device_type'];
                                $device_token = $resultUser['User']['device_token'];
                                $payLoadData = array(
                                    'Tp' => "Rpt" , 
                                    //'Notfication Text' => $message, 
                                    'chat_message'     => $message
                                );
                                if($device_type!='' && $device_token!='') {
                                    $this->Pushnotification->sendMessage($device_type, $device_token, $message, $payLoadData);                            
                                }
                            //}
                            
                            $success = true;
                            $status = SUCCESS;
                            $message = 'A Report mail has been sent to Admin about inappropriate newsfeed.';
                            
                        } else {
                            $success = false;
                            $status = ERROR;
                            $message = 'A Report mail has not been due to error.';
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
            // newsfeed id blank in request
            case!empty($request_data) && empty($request_data->newsfeed_id):
                $success = false;
                $status = BAD_REQUEST;
                $message = 'newsfeed id cannot be blank.';
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
