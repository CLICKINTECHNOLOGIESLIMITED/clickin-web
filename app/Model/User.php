<?php

class User extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';

    /*
      var $mongoSchema = array(
      'title' => array('type'=>'string'),
      'body'=>array('type'=>'string'),
      'hoge'=>array('type'=>'string'),
      'created'=>array('type'=>'datetime'),
      'modified'=>array('type'=>'datetime'),
      );
     */

    /**
     * findUser method
     * @param string $phone_no Phone no to be searched for in the database
     * @return array
     * @access public
     */
    public function findUser($phone_no) {
        $params = array(
            'fields' => array(
                '_id', 'phone_no', 'name', 'vcode', 'verified', 'user_token', 'device_token', 'device_type', 'email', 'is_new_clickin_user',
                'password', 'relationships', 'QB_id', 'user_pic', 'follower', 'following', 'unread_notifications_count','is_active'
            ),
            'conditions' => array('phone_no' => $phone_no),
            'order' => array('_id' => -1),
            'limit' => 20,
            'page' => 1
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);
        return $results;
    }

    /**
     * fetchUserProfile method
     * @param string $phone_no Phone no to be searched for in the database
     * @return array
     * @access public
     */
    public function fetchUserProfile($phone_no) {
        $params = array(
            'fields' => array('_id', 'name', 'QB_id', 'user_pic', 'dob', 'gender', 'unread_notifications_count', 'following', 'follower', 'city', 'country', 'email','is_enable_push_notification', 'is_new_clickin_user'),
            'conditions' => array('phone_no' => $phone_no, "verified" => true),
            'order' => array('_id' => -1),
            'limit' => 20,
            'page' => 1
        );
        // Find records matching phone no.
        $results = $this->find('first', $params);

        $follower = 0;
        $following = 0;

        // Fetching the follower count
        if (isset($results['User']['follower'])) {
            foreach ($results['User']['follower'] as $key => $value) {

                // checking follower is active or not..
                $params = array(
                    'fields' => array('_id', 'is_active'),
                    'conditions' => array(
                        '$and' => array(
                            array('_id' => new MongoId($results["User"]["follower"][$key]['follower_id'])),
                            array('is_active' => array('$ne' => 'no'))
                )));
                $resFollowerData = $this->find('first', $params);

                //if($results['User']['follower'][$key]['accepted'] === TRUE || $results['User']['follower'][$key]['accepted'] === NULL)
                if ($results['User']['follower'][$key]['accepted'] === TRUE && count($resFollowerData) > 0)
                    $follower++;
            }
        }

        // Fetching the following count
        if (isset($results['User']['following'])) {
            foreach ($results['User']['following'] as $key => $value) {

                // checking follower is active or not..
                $params = array(
                    'fields' => array('_id', 'is_active'),
                    'conditions' => array(
                        '$and' => array(
                            array('_id' => new MongoId($results["User"]["following"][$key]['followee_id'])),
                            array('is_active' => array('$ne' => 'no'))
                )));
                $resFollowingData = $this->find('first', $params);

                //if($results['User']['following'][$key]['accepted'] === TRUE || $results['User']['following'][$key]['accepted'] === NULL)
                if ($results['User']['following'][$key]['accepted'] === TRUE && count($resFollowingData) > 0)
                    $following++;
            }
        }

        // Append the follower and following count to the result data
        $results['User']['follower'] = $follower;
        $results['User']['following'] = $following;

        return $results;
    }

    /**
     * findUserByEmail method
     * @param string $email Email id to be searched for in the database
     * @return array
     * @access public
     */
    public function findUserByEmail($email) {
        $params = array(
            'fields' => array('_id', 'phone_no', 'name', 'vcode', 'verified', 'user_token', 'device_token', 'password', 'relationships', 'QB_id', 'user_pic',
                'is_active'),
            'conditions' => array('email' => array('$regex' => $email, '$options' => '-i')),
            'order' => array('_id' => -1),
            'limit' => 20,
            'page' => 1,
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);
        return $results;
    }

    /**
     * checkEmailExists method
     * @param string $email Email id to be searched for in the database
     * @return array
     * @access public
     */
    public function checkEmailExists($email, $phone_no = '') {

        $params = array(
            'fields' => array('phone_no', 'name', 'vcode', 'verified', 'user_token', 'email', 'is_new_clickin_user'),
            'conditions' => array('email' => array('$regex' => $email, '$options' => '-i')),
            'order' => array('_id' => -1),
            'limit' => 20,
            'page' => 1,
        );
        if ($phone_no != '')
            $params['conditions']['phone_no']['$ne'] = $phone_no;

        // Find records matching email
        $results = $this->find('all', $params);
        return count($results);
    }

    /**
     * findRelationshipRequest method
     * @param string $phone_no Phone no to be searched for in the database
     * @param string $user_token User Token to be searched for in the database
     * @return array
     * @access public
     */
    public function findRelationshipRequest($user_token, $phone_no) {
        $params = array(
            'fields' => array('phone_no', 'name', 'vcode', 'verified', 'user_token', 'device_token', 'password', 'relationships'),
            'conditions' => array('relationships.phone_no' => $phone_no, 'user_token' => $user_token),
            'order' => array('_id' => -1),
            'limit' => 20,
            'page' => 1
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);
        return $results;
    }

    /**
     * findRelationshipsByType method
     * @param string $phone_no Phone no to be searched for in the database
     * @param string $user_token User Token to be searched for in the database
     * @param string $type true - accepted, false - rejected, null - pending
     * @return array
     * @access public
     */
    public function findRelationshipsByType($user_token, $phone_no, $type) {

        $conditions = array('phone_no' => $phone_no, 'relationships.accepted' => $type);

        /* $conditions['$or'] = array(
          array('relationships.deleted' => array('$exists' => false)),
          array('$and' => array(
          array('relationships.deleted' => array('$exists' => true)),
          array('relationships.deleted' => 'no')
          ))
          ); */
        //$conditions['relationships.deleted']['$ne'] = 'yes';
        $params = array(
            'fields' => array('_id', 'phone_no', 'name', 'vcode', 'verified', 'user_token', 'user_pic', 'device_token', 'password', 'relationships', 'QB_id', 'is_new_clickin_user'),
            'conditions' => $conditions,
            'order' => array('_id' => -1),
            'limit' => 20,
            'page' => 1
        );

        // Find records matching phone no.
        $results = $this->find('all', $params);
        // Loop through all the relationships to fetch the clicks from partner's data
        if (count($results) > 0 && isset($results[0]['User']['relationships'])) {
            foreach ($results[0]['User']['relationships'] as $key => $relationship) {

                $params = array(
                    'fields' => array('_id', 'phone_no', 'name', 'is_active', 'last_seen_time'),
                    'conditions' => array(
                        '$and' => array(
                            array('_id' => new MongoId($relationship['partner_id'])),
                            array('is_active' => array('$ne' => 'no'))
                        )
                    )
                );
                $resPartnerData = $this->find('first', $params);

                if ($relationship['accepted'] === $type && count($resPartnerData) > 0 && (!isset($relationship['deleted']) ||
                        (isset($relationship['deleted']) && $relationship['deleted'] != 'yes'))) {
                    
                    // setting last seen time of current user..
                    $results[0]['User']['relationships'][$key]['last_seen_time'] = isset($resPartnerData['User']['last_seen_time']) ? 
                                                                                            $resPartnerData['User']['last_seen_time'] : '';
                    
                    // Check if the user does not have clicks
                    if (empty($results[0]['User']['relationships'][$key]['user_clicks'])) {
                        $results[0]['User']['relationships'][$key]['user_clicks'] = 25;
                    }
                    // Check if the user does not have partner's clicks
                    if (empty($results[0]['User']['relationships'][$key]['clicks'])) {
                        $results[0]['User']['relationships'][$key]['clicks'] = 25;
                    }
                }
                else
                    unset($results[0]['User']['relationships'][$key]);
            }
        }
        return $results;
    }

    /**
     * checkPhoneNosRegistered method
     * @param array $phone_nos_arr Array of phone nos to be searched for in the database
     * $param string $user_phone_no Phone no. of the user being searched for
     * @return array
     * @access public
     */
    public function checkPhoneNosRegistered($phone_nos_arr, $user_phone_no) {
        $params = array(
            'fields' => array('phone_no', 'user_pic'),
            'conditions' => array('phone_no' => array('$in' => $phone_nos_arr), 'verified' => true),
            'order' => array('_id' => -1)
        );

        // Find records matching phone nos. array
        $existing_phone_nos = $this->find('list', $params);

        // Fetching the current user's details and user's followed
        $user_following = $this->findUser($user_phone_no);
        $following = array();

        // Loop through the user details to fetch the list of phone nos. followed
        foreach ($user_following[0]['User']['following'] as $followee) {
            $following[] = $followee['phone_no'];
        }

        // Updated contact list
        $updatedContactsList = array();

        // Loop through the initial phone nos list
        foreach ($phone_nos_arr as $contact_phone_no) {
            // Check if phone no exists in the phone nos existing in database
            // Check if user already following contact, if contact exists in database
            $contact_details = array(
                'phone_no' => $contact_phone_no,
                'exists' => 0
            );

            // If phone no. found in database, then mark as existing
            if (array_key_exists($contact_phone_no, $existing_phone_nos)) {
                $contact_details['exists'] = 1;
                $contact_details['user_pic'] = $existing_phone_nos[$contact_phone_no];

                // If contact exists in database, then check if already following user
                if (in_array($contact_phone_no, $following))
                    $contact_details['following'] = 1;
            }

            // Add the details to the new list of contacts
            $updatedContactsList[] = $contact_details;
        }

        return $updatedContactsList;
    }

    /**
     * findFollowing method
     * @param string $phone_no Phone no to be searched for in the database
     * @param string $user_token User Token to be searched for in the database
     * @return array
     * @access public
     */
    public function findFollowing($user_token, $phone_no) {
        $params = array(
            'fields' => array('phone_no', 'name', 'vcode', 'verified', 'user_token', 'device_token', 'password', 'following'),
            'conditions' => array('following.phone_no' => $phone_no, 'user_token' => $user_token),
            'order' => array('_id' => -1),
            'limit' => 20,
            'page' => 1
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);
        return $results;
    }

    /**
     * getAllFollower method
     * find all followers of a user..
     * @param int $user_id
     * @return array
     * @access public
     */
    public function getAllFollower($user_id) {
        $params = array(
            'fields' => array('follower'),
            'conditions' => array('_id' => new MongoId($user_id))
        );
        // Find records matching phone no.
        $results = $this->find('first', $params);

        // null values will be in bottom..
        if (count($results) > 0 && isset($results["User"]["follower"])) {
            
            // Rearranging the keys in the array after unsetting keys
            $results["User"]["follower"] = array_values($results["User"]["follower"]);
            
            $follower_name = array();
            foreach ($results["User"]["follower"] as $urKey => $urVal) {

                $params = array(
                    'fields' => array('_id', 'phone_no', 'name', 'is_active'),
                    'conditions' => array(
                        '$and' => array(
                            array('_id' => new MongoId($results["User"]["follower"][$urKey]['follower_id'])),
                            array('is_active' => array('$ne' => 'no'))
                        )
                    )
                );
                $resFollowerData = $this->find('first', $params);

                if (count($resFollowerData) > 0) {
                    $follower_name[$urKey] = $results["User"]["follower"][$urKey]['follower_name'];
                } else {
                    unset($results["User"]["follower"][$urKey]);
                }
            }

            // Rearranging the keys in the array after unsetting keys
            $results["User"]["follower"] = array_values($results["User"]["follower"]);

            $follower_name = array_map('strtolower', $follower_name);

            array_multisort($follower_name, SORT_ASC, $results["User"]["follower"]);
        }
        return $results;
    }

    /**
     * getAllFollowing method
     * find all followings of a user..
     * @param int $user_id
     * @return array
     * @access public
     */
    public function getAllFollowing($user_id) {
        $params = array(
            'fields' => array('following'),
            'conditions' => array('_id' => new MongoId($user_id))
        );
        // Find records matching phone no.
        $results = $this->find('first', $params);

        if (count($results) > 0) {
            foreach ($results["User"]["following"] as $urKey => $urVal) {

                $params = array(
                    'fields' => array('_id', 'phone_no', 'name', 'is_active'),
                    'conditions' => array(
                        '$and' => array(
                            array('_id' => new MongoId($results["User"]["following"][$urKey]['followee_id'])),
                            array('is_active' => array('$ne' => 'no'))
                        )
                    )
                );
                $resFollowingData = $this->find('first', $params);

                if (count($resFollowingData) == 0) {
                    unset($results["User"]["following"][$urKey]);
                }
            }

            // Rearranging the keys in the array after unsetting keys
            $results["User"]["following"] = array_values($results["User"]["following"]);
        }

        return $results;
    }

    /**
     * findRelationship method
     * find relationship detail of user by relationship id...
     * @param string $user_token
     * @param string $phone_no
     * @param int $relationship_id
     * @return array
     * @access public
     */
    public function findRelationship($phone_no, $relationship_id) {
        $params = array(
            'fields' => array('_id', 'phone_no', 'name', 'relationships'),
            'conditions' => array('phone_no' => $phone_no, 'relationships.accepted' => TRUE, 'relationships.id' => new MongoId($relationship_id))
        );
        // Find records matching phone no.
        $results = $this->find('first', $params);
        return $results;
    }

    /**
     * findPublicRelationships method
     * @param string $phone_no Phone no to be searched for in the database
     * @return array
     * @access public
     */
    public function findPublicRelationships($phone_no) {
        $params = array(
            'fields' => array('_id', 'relationships'),
            'conditions' => array('phone_no' => $phone_no),
            'order' => array('_id' => -1),
            'limit' => 20,
            'page' => 1
        );

        // Find records matching phone no.
        $results = $this->find('first', $params);

        $resultArr = array();
        if (count($results) > 0) {
            foreach ($results["User"]["relationships"] as $urKey => $urVal) {
                if ($results["User"]["relationships"][$urKey]['accepted'] == true && $results["User"]["relationships"][$urKey]['public'] == true) {

                    $params = array(
                        'fields' => array('_id', 'is_active'),
                        'conditions' => array('_id' => new MongoId($results["User"]["relationships"][$urKey]['partner_id']))
                    );
                    // Find records matching phone no.
                    $resRelUserDetail = $this->find('first', $params);

                    if (isset($resRelUserDetail['User']['is_active']) && $resRelUserDetail['User']['is_active'] == 'yes' || !isset($resRelUserDetail['User']['is_active'])) {
                        $resultArr[] = $results["User"]["relationships"][$urKey];
                    }
                }
            }
        }
        return $resultArr;
    }

    /**
     * saveRelationshipData method
     * @param array $request_data
     * @param array $data
     * @param array $is_new_request
     * @return array
     * @access public
     */
    public function saveRelationshipData($request_data, $data, $is_new_request = 0) {
        // Find records matching relationship and other data for user..
        $params = array(
            'fields' => array('relationships'),
            'conditions' => array('_id' => new MongoId($data[0]['User']['_id']), 'relationships.id' => new MongoId($request_data->relationship_id))
        );
        $results = $this->find('first', $params);
        if (count($results) > 0) {
            foreach ($results["User"]["relationships"] as $urKey => $urVal) {
                if ($request_data->relationship_id == (string) $results["User"]["relationships"][$urKey]['id']) {
                    if (isset($request_data->accepted)) {
                        $results["User"]["relationships"][$urKey]["accepted"] = ($request_data->accepted == 'true') ? true : false;

                        // add 25 clicks in both user's relationships subcollection..
                        if ($request_data->accepted == 'true') {
                            // Check if the user does not have clicks
                            if (empty($results["User"]["relationships"][$urKey]['user_clicks'])) {
                                $results["User"]["relationships"][$urKey]['user_clicks'] = 25;
                            }
                            // Check if the user does not have partner's clicks
                            if (empty($results["User"]["relationships"][$urKey]['clicks'])) {
                                $results["User"]["relationships"][$urKey]['clicks'] = 25;
                            }
                        }
                    }
                    if ($is_new_request == 1) {
                        $results["User"]["relationships"][$urKey]["request_initiator"] = true;
                        $results["User"]["relationships"][$urKey]["is_new_partner"] = 'yes';
                    }                        
                    if (isset($request_data->public))
                        $results["User"]["relationships"][$urKey]["public"] = ($request_data->public == 'true') ? true : false;
                    if (isset($request_data->deleted)) {
                        $results["User"]["relationships"][$urKey]["deleted"] = $request_data->deleted;
                        $results["User"]["relationships"][$urKey]["accepted"] = null;
                        $results["User"]["relationships"][$urKey]["public"] = null;
                        $results["User"]["relationships"][$urKey]["is_new_partner"] = 'no';
                    }
                }
            }
        }
        if ($this->save($results)) {
            $params = array(
                'fields' => array('relationships'),
                'conditions' => array('relationships.partner_id' => $data[0]['User']['_id'], 'relationships.id' => new MongoId($request_data->relationship_id))
            );
            $results = $this->find('first', $params);
            if (count($results) > 0) {
                foreach ($results["User"]["relationships"] as $urKey => $urVal) {
                    if ($request_data->relationship_id == (string) $results["User"]["relationships"][$urKey]['id']) {
                        if (isset($request_data->accepted)) {
                            $results["User"]["relationships"][$urKey]["accepted"] = ($request_data->accepted == 'true') ? true : false;

                            // add 25 clicks in both user's relationships subcollection..
                            if ($request_data->accepted == 'true') {
                                // Check if the user does not have clicks
                                if (empty($results["User"]["relationships"][$urKey]['user_clicks'])) {
                                    $results["User"]["relationships"][$urKey]['user_clicks'] = 25;
                                }
                                // Check if the user does not have partner's clicks
                                if (empty($results["User"]["relationships"][$urKey]['clicks'])) {
                                    $results["User"]["relationships"][$urKey]['clicks'] = 25;
                                }
                            }
                        }
                        if ($is_new_request == 1) {
                            unset($results["User"]["relationships"][$urKey]["request_initiator"]);
                            $results["User"]["relationships"][$urKey]["is_new_partner"] = 'yes';
                        }
                        if (isset($request_data->public))
                            $results["User"]["relationships"][$urKey]["public"] = ($request_data->public == 'true') ? true : false;
                        if (isset($request_data->deleted)) {
                            $results["User"]["relationships"][$urKey]["deleted"] = $request_data->deleted;
                            $results["User"]["relationships"][$urKey]["accepted"] = null;
                            $results["User"]["relationships"][$urKey]["public"] = null;
                            $results["User"]["relationships"][$urKey]["is_new_partner"] = 'no';
                        }
                    }
                }
            }
            return $this->save($results);
        }
        else
            return FALSE;
    }

    /**
     * findUserRelationshipsByNo method
     * @param string $phone_no Phone no to be searched for in the database
     * @return array
     * @access public
     */
    public function findUserRelationshipsByNo($phone_no) {
        $params = array(
            'fields' => array('_id', 'relationships'),
            'conditions' => array('relationships.phone_no' => $phone_no),
            'order' => array('_id' => -1),
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);

        return $results;
    }

    /**
     * updateRelationshipDataOfPartner method
     * @param array $request_data
     * @param array $userList
     * @access public
     */
    public function updateRelationshipDataOfPartner($request_data, $userList = array()) {
        if (isset($request_data) && count($userList) > 0) {
            foreach ($userList as $urKey => $urVal) {
                foreach ($userList[$urKey]["User"]["relationships"] as $rkey => $rvalue) {

                    if ($request_data->phone_no == $userList[$urKey]["User"]["relationships"][$rkey]['phone_no']) {
                        if (isset($request_data->name))
                            $userList[$urKey]["User"]["relationships"][$rkey]["partner_name"] = trim($request_data->name);
                        if (isset($request_data->partner_pic))
                            $userList[$urKey]["User"]["relationships"][$rkey]["partner_pic"] = trim($request_data->partner_pic);
                        if (isset($request_data->partner_QB_id))
                            $userList[$urKey]["User"]["relationships"][$rkey]["partner_QB_id"] = $request_data->partner_QB_id;
                    }
                }
                $this->save($userList[$urKey]);
            }
        }
    }

    /**
     * findUserFollowerByNo method
     * @param string $phone_no Phone no to be searched for in the database
     * @param string $type 'follower' or 'following'
     * @return array
     * @access public
     */
    public function findUserFollowerFollowingsByNo($phone_no, $typeArray = array()) {

        $results = array();

        if (count($typeArray) > 0) {
            $fieldArray = array('_id');
            $fieldArray = array_merge($fieldArray, $typeArray);

            if (count($typeArray) > 1) {
                $conditionArray = array('$or' => array(
                        array($typeArray[0] . '.phone_no' => $phone_no),
                        array($typeArray[1] . '.phone_no' => $phone_no),
                ));
            } else {
                $conditionArray = array($typeArray[0] . '.phone_no' => $phone_no);
            }

            $params = array(
                'fields' => $fieldArray,
                'conditions' => $conditionArray,
                'order' => array('_id' => -1),
            );
            //print_r($params);exit;
            // Find records matching phone no.
            $results = $this->find('all', $params);
        }
        return $results;
    }

    /**
     * updateFollowerFollowingDataOfPartner method
     * @param array $request_data
     * @param array $userList
     * @access public
     */
    public function updateFollowerFollowingDataOfPartner($request_data, $userList = array()) {
        if (isset($request_data) && count($userList) > 0) {
            foreach ($userList as $urKey => $urVal) {
                // check follower records for updation if user exists...
                if (isset($userList[$urKey]["User"]["follower"]) && count($userList[$urKey]["User"]["follower"]) > 0) {
                    foreach ($userList[$urKey]["User"]["follower"] as $rkey => $rvalue) {

                        if ($request_data->phone_no == $userList[$urKey]["User"]["follower"][$rkey]['phone_no']) {
                            if (isset($request_data->name))
                                $userList[$urKey]["User"]["follower"][$rkey]["follower_name"] = trim($request_data->name);
                            if (isset($request_data->partner_pic))
                                $userList[$urKey]["User"]["follower"][$rkey]["follower_pic"] = trim($request_data->partner_pic);
                        }
                    }
                }
                // check following records for updation if user exists...
                if (isset($userList[$urKey]["User"]["following"]) && count($userList[$urKey]["User"]["following"]) > 0) {
                    foreach ($userList[$urKey]["User"]["following"] as $rkey => $rvalue) {

                        if ($request_data->phone_no == $userList[$urKey]["User"]["following"][$rkey]['phone_no']) {
                            if (isset($request_data->name))
                                $userList[$urKey]["User"]["following"][$rkey]["followee_name"] = trim($request_data->name);
                            if (isset($request_data->partner_pic))
                                $userList[$urKey]["User"]["following"][$rkey]["followee_pic"] = trim($request_data->partner_pic);
                        }
                    }
                }
                $this->save($userList[$urKey]);
            }
        }
    }

    /**
     * saveFollowerData method
     * @param array $request_data
     * @param array $data
     * @return array
     * @access public
     */
    public function saveFollowerFollowingData($request_data, $data, $type = 'follower') {
        if ($type == 'follower') {
            // Find records matching follower and other data for user..
            $params = array(
                'fields' => array('_id', 'follower'),
                'conditions' => array('_id' => new MongoId($data[0]['User']['_id']), 'follower._id' => new MongoId($request_data->follow_id))
            );
            $results = $this->find('first', $params);
            if (count($results) > 0) {
                foreach ($results["User"]["follower"] as $urKey => $urVal) {
                    if ($request_data->follow_id == (string) $results["User"]["follower"][$urKey]['_id'])
                        $results["User"]["follower"][$urKey]["accepted"] = ($request_data->accepted == 'true') ? true : false;
                }
            }

            if ($this->save($results)) {
                // Find records matching followings and other data in following user..
                $params = array(
                    'fields' => array('_id', 'following'),
                    'conditions' => array('following._id' => new MongoId($request_data->follow_id), 'following.followee_id' => $data[0]['User']['_id'])
                );
                $results = $this->find('first', $params);

                if (count($results) > 0) {
                    foreach ($results["User"]["following"] as $urKey => $urVal) {
                        if ($results["User"]["following"][$urKey]['followee_id'] == $data[0]['User']['_id'])
                            $results["User"]["following"][$urKey]["accepted"] = ($request_data->accepted == 'true') ? true : false;
                    }
                }
                return $this->save($results);
            }
            else
                return FALSE;
        }
        else {
            // Find records matching followings and other data in following user..
            $params = array(
                'fields' => array('_id', 'following'),
                'conditions' => array('_id' => new MongoId($data[0]['User']['_id']), 'following._id' => new MongoId($request_data->follow_id))
            );
            $results = $this->find('first', $params);
            
            if (count($results) > 0) {
                foreach ($results["User"]["following"] as $urKey => $urVal) {
                    if ((string) $results["User"]["following"][$urKey]['_id'] == $request_data->follow_id)
                        $results["User"]["following"][$urKey]["accepted"] = ($request_data->accepted == 'true') ? true : false;
                }
            }
		
            if ($this->save($results)) {
                // Find records matching follower and other data for user..
                $params = array(
                    'fields' => array('_id', 'follower'),
                    'conditions' => array('follower._id' => new MongoId($request_data->follow_id), 'follower.follower_id' => $data[0]['User']['_id'])
                );
                $results = $this->find('first', $params);
                if (count($results) > 0) {
                    foreach ($results["User"]["follower"] as $urKey => $urVal) {
                        if ($request_data->follow_id == (string) $results["User"]["follower"][$urKey]['_id'])
                            $results["User"]["follower"][$urKey]["accepted"] = ($request_data->accepted == 'true') ? true : false;
                    }
                }
                return $this->save($results);
            }
            else
                return FALSE;
        }
    }

    /**
     * removeNonFollowFollowing method
     * @param array $dataArray
     * @param string $type -> follower, following
     * @param int $isOwner
     * @return array
     * @access public
     */
    public function removeNonFollowFollowing($dataArray, $type, $isOwner = 1) {
        if (count($dataArray) > 0 && $type == 'following') {
            $followingTrueArr = array();
            $followingNullArr = array();
            foreach ($dataArray["User"][$type] as $urKey => $urVal) {
                if ($dataArray["User"][$type][$urKey]['accepted'] === FALSE)
                    unset($dataArray["User"][$type][$urKey]);
                elseif ($dataArray["User"][$type][$urKey]['accepted'] === TRUE)
                    $followingTrueArr["User"][$type][] = $dataArray["User"][$type][$urKey];
                // added when this user is self logged in..
                elseif ($dataArray["User"][$type][$urKey]['accepted'] === NULL && $isOwner == 1)
                    $followingNullArr["User"][$type][] = $dataArray["User"][$type][$urKey];
            }
        }

        // null values will be in bottom..
        if ($type == 'following') {
            // Rearranging the keys in the array after unsetting keys
            $followingTrueArr["User"][$type] = array_values($followingTrueArr["User"][$type]);

            $acceptedTrue = array();
            foreach ($followingTrueArr["User"][$type] as $urKey => $urVal)
                $acceptedTrue[$urKey] = $followingTrueArr["User"][$type][$urKey]['followee_name'];

            $acceptedTrue = array_map('strtolower', $acceptedTrue);

            array_multisort($acceptedTrue, SORT_ASC, $followingTrueArr["User"][$type]);

            $dataArray = array();
            if (count($followingNullArr) > 0) {
                // Rearranging the keys in the array after unsetting keys
                $followingNullArr["User"][$type] = array_values($followingNullArr["User"][$type]);

                // null values will be in bottom..
                $acceptedNull = array();
                foreach ($followingNullArr["User"][$type] as $urKey => $urVal)
                    $acceptedNull[$urKey] = $followingNullArr["User"][$type][$urKey]['followee_name'];

                $acceptedNull = array_map('strtolower', $acceptedNull);

                array_multisort($acceptedNull, SORT_ASC, $followingNullArr["User"][$type]);
                
                if(count($followingTrueArr["User"][$type])>0)
                    $dataArray["User"][$type] = array_merge($followingTrueArr["User"][$type], $followingNullArr["User"][$type]);
                else
                    $dataArray["User"][$type] = $followingNullArr["User"][$type];                
            } else {
                $dataArray["User"][$type] = $followingTrueArr["User"][$type];
            }
        }
        //print_r($dataArray);exit;
        //print_r($followingTrueArr);	
        //print_r($followingNullArr);exit;        

        /* // Rearranging the keys in the array after unsetting keys
          $dataArray["User"][$type] = array_values($dataArray["User"][$type]);
          // null values will be in bottom..
          if($type == 'following')
          {
          $accepted = array();
          foreach($dataArray["User"][$type] as $urKey => $urVal)
          $accepted[$urKey] = $dataArray["User"][$type][$urKey]['accepted'];
          array_multisort($accepted, SORT_DESC, $dataArray["User"][$type]);
          } */
        return $dataArray;
    }

    /**
     * addFollowingDetailInFollower method
     * this function is used for adding some information about following in follower list of user..
     * @param array $followerArr
     * @param array $followingArr
     * @return array
     * @access public
     */
    public function addFollowingDetailInFollower($followerArr, $followingArr) {
        if (count($followerArr) > 0) {
            foreach ($followerArr["User"]["follower"] as $urKey => $urVal) {
                foreach ($followingArr["User"]["following"] as $urKey1 => $urVal1) {
                    if ($followerArr["User"]["follower"][$urKey]['follower_id'] == $followingArr["User"]["following"][$urKey1]['followee_id']) {
                        $followerArr["User"]["follower"][$urKey]['following_accepted'] = $followingArr["User"]["following"][$urKey1]['accepted'];
                        $followerArr["User"]["follower"][$urKey]['following_id'] = (string) $followingArr["User"]["following"][$urKey1]['_id'];
                        $followerArr["User"]["follower"][$urKey]['is_following'] = $followingArr["User"]["following"][$urKey1]['accepted'] === false ? FALSE : TRUE;
                    }
                }
            }
        }
        return $followerArr;
    }

    /**
     * fetchUsersByName method
     * this function is used to fetch users by name and last user id..
     * @param string $name
     * @param string $data optional
     * @return array
     * @access public
     */
    public function fetchUsersByName($name, $data = array()) {

        $order = array('_id' => -1);
        $limit = 100;
        // Find records matching phone no.
        $conditions = array('$and' => array(
                array('name' => array('$regex' => $name, '$options' => 'i')),
                array('_id' => array('$ne' => new MongoId($data[0]['User']['_id']))),
                array('is_active' => array('$ne' => 'no'))
            )
        );
        $params = array(
            'fields' => array('_id', 'phone_no', 'name', 'user_token', 'QB_id', 'user_pic', 'city', 'country'),
            'conditions' => $conditions,
            'order' => $order,
            'limit' => $limit
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);

        $resultArr = array();
        if (count($results) > 0) {
            foreach ($results as &$value) {
                $value['User']['_id'] = new MongoId($value['User']['_id']);
                $resultArr[] = $value['User'];
            }
        }
        //print_r($resultArr);exit;   
        return $resultArr;

        /* $param = array('text' => 'users', 'search' => $name,
          'project' => array(
          '_id' => '1', 'phone_no' => '1', 'name' => '1', 'user_token' => '1',
          'user_pic' => '1', 'QB_id' => '1','city' => '1', 'country' => '1'),
          //'order' => $order,
          'limit' => (int) $limit
          );
          $results = $this->query($param);
          $resultArr = array();
          if(count($results)>0 && count($results['results'])>0)
          {
          foreach ($results['results'] as $resArr) {
          $resultArr[] = $resArr['obj'];
          }
          }
          //print_r($resultArr);exit;
          return $resultArr; */
    }

    /**
     * updateRelationshipDataOfPartnerById method
     * @param array $request_data
     * @param array $userList
     * @access public
     */
    public function updateRelationshipDataOfPartnerById($request_data, $userList = array()) {
        if (isset($request_data) && count($userList) > 0) {
            foreach ($userList as $urKey => $urVal) {
                foreach ($userList[$urKey]["User"]["relationships"] as $rkey => $rvalue) {

                    if ($request_data->relationship_id == (string) $userList[$urKey]["User"]["relationships"][$rkey]['id']) {
                        if (isset($request_data->name))
                            $userList[$urKey]["User"]["relationships"][$rkey]["partner_name"] = trim($request_data->name);
                        if (isset($request_data->partner_pic))
                            $userList[$urKey]["User"]["relationships"][$rkey]["partner_pic"] = trim($request_data->partner_pic);
                        if (isset($request_data->partner_QB_id))
                            $userList[$urKey]["User"]["relationships"][$rkey]["partner_QB_id"] = $request_data->partner_QB_id;
                        if (isset($request_data->is_new_partner))
                            $userList[$urKey]["User"]["relationships"][$rkey]["is_new_partner"] = $request_data->is_new_partner;
                    }
                }
                $this->save($userList[$urKey]);
            }
            return 1;
        } else {
            return 0;
        }
    }
}
