<?php

App::uses('Component', 'Controller');

App::import('Component', 'Image');

App::import('Vendor', 'twitter/twitteroauth-master/twitteroauth/twitteroauth');
App::import('Vendor', 'twitter/library/tmhOAuth');
App::import('Vendor', 'twitter/library/tmhUtilities');

class TwitterComponent extends Component {
    
    /**
     * for set tmhOAuth object.
     * @var object 
     */
    var $tmhOAuth;
    
    /**
     * for set sharing data model object.
     * @var object 
     */
    var $Sharing;
    
    /**
     * for set chat data model object.
     * @var object 
     */
    var $Chat;
    
    /**
     * for set user data model object.
     * @var object 
     */
    var $User;
    
    /**
     * Image Component property
     *
     * @access public
     */
    var $Image;
    
    public function __construct() {
        
        // initialize image component..
        $this->Image = & new ImageComponent(new ComponentCollection());
        
        // set sharing, user & chat data model objects.
        $this->Sharing = ClassRegistry::init('Sharing');
        $this->Chat = ClassRegistry::init('Chat');
        $this->User = ClassRegistry::init('User');
    }
    
    public function authorizeOnTwitter($user_access_token = '', $user_access_token_secret = '')
    {
        // configure basic setup for twitter..
        $this->tmhOAuth = new tmhOAuth(array(
                'consumer_key'    => TWITTER_CONSUMER_KEY,
                 'consumer_secret' => TWITTER_CONSUMER_SECRET,
                 'user_token'      => $user_access_token!='' ? $user_access_token : TWITTER_OAUTH_ACCESS_TOKEN,
                 'user_secret'     => $user_access_token_secret!='' ? $user_access_token_secret : TWITTER_OAUTH_ACCESS_TOKEN_SECRET,
        ));
        
        // check authorization of user on twitter..
        return $code = $this->tmhOAuth->request('GET',CHECK_TWITTER);
    }

    
    protected function getImageRawData($url)
    {
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
        return curl_exec($ch);
    }
    
    /**
     * twittershare method
     * this function is used to share text, image on twitter..
     * 
     * @param int $sharingId
     * @return array
     * @access public
     */
    public function twittershare($sharingId) {
        
        // fetching newsfeed details by id...
        $params = array('conditions' => array('_id' => new MongoId($sharingId)));
        $sharingDetailArr = $this->Sharing->find('first', $params);
        
        // fetching chat details by id...
        $params = array('conditions' => array('_id' => new MongoId($sharingDetailArr['Sharing']['chat_id'])));
        $chatDetailArr = $this->Chat->find('first', $params);
        
        // posting on user's wall...
        if(count($sharingDetailArr)>0 && count($chatDetailArr)>0)
        {
            $user_access_token = $sharingDetailArr ['Sharing']['twitter_access_token'];
            $user_access_token_secret = $sharingDetailArr ['Sharing']['twitter_access_token_secret'];
           
            // check authorization of user by token..
            $code = $this->authorizeOnTwitter($user_access_token, $user_access_token_secret);
            
            // if twitter return 200 in response then user can tweet..
            if($code == 200) 
            {
                $chatTypeArr = array('1' => 'Text', '2' => 'Image', '3' => 'Audio', '4' => 'Video', '5' => 'Cards');
                if($chatDetailArr['Chat']['clicks'] !== NULL || ($chatDetailArr['Chat']['type'] == 1 || $chatDetailArr['Chat']['type'] == 2 || 
                        $chatDetailArr['Chat']['type'] == 3 || $chatDetailArr['Chat']['type'] == 4))
                {
                    $query_params = array('conditions' => array('_id' => new MongoId($chatDetailArr['Chat']['userId'])));
                    $userDetailArr = $this->User->find('first', $query_params);

                    try
                    {
                        if($chatDetailArr['Chat']['content']!='')
                        {
                            // when clicks are available for sharing..
                            if($chatDetailArr['Chat']['clicks']!== NULL)
                            {
                                $message = $userDetailArr['User']['name'] ." shared " . $chatDetailArr['Chat']['clicks'] . " clicks.";
                                $postreply = $this->tmhOAuth->request('POST',TWITTER_UPDATE_JSON, $message);
                            }
                            // when type is text..
                            else if($chatDetailArr['Chat']['type'] == 1)
                            {
                                $message = ($chatDetailArr['Chat']['message']!='') ? $chatDetailArr['Chat']['message'] : 
                                                $userDetailArr['User']['name'] . " shared " . $chatTypeArr[$chatDetailArr['Chat']['type']] . ".";
                                $postreply = $this->tmhOAuth->request('POST',TWITTER_UPDATE_JSON, $message);
                            }
                            // when type is image..
                            else if($chatDetailArr['Chat']['type'] == 2)
                            {
                                $message = ($chatDetailArr['Chat']['message']!='') ? $chatDetailArr['Chat']['message'] : 
                                                $userDetailArr['User']['name'] . " shared " . $chatTypeArr[$chatDetailArr['Chat']['type']] . ".";
                                //$image = $this->getImageRawData($chatDetailArr['Chat']['content']);
                                $sharingImageUrl = $this->Image->watermarkingOnImage($chatDetailArr['Chat']['content']);
                                $image = $this->getImageRawData( $sharingImageUrl );
                                $postreply = $this->tmhOAuth->request('POST',TWITTER_UPDATE_MEDIA_JSON,
                                                    array('media' => "{$image}",'status'=> $message),true,true);
                            }
                            // when type is audio..
                            else if($chatDetailArr['Chat']['type'] == 3)
                            {
                                $message = $userDetailArr['User']['name'] ." shared audio " . $chatDetailArr['Chat']['content'] . ".";
                                $postreply = $this->tmhOAuth->request('POST',TWITTER_UPDATE_JSON, $message);
                            }
                            // when type is video..
                            else if($chatDetailArr['Chat']['type'] == 4)
                            {
                                $message = $userDetailArr['User']['name'] ." shared video " . $chatDetailArr['Chat']['content'] . ".";
                                $postreply = $this->tmhOAuth->request('POST',TWITTER_UPDATE_JSON, $message);
                            }
                            // to do : we will implement posting for cards...

                            if($postreply == "200")
                                return TRUE;
                            else
                                return array('exception' => 'Tweet did not posted..');
                        }
                    }
                    catch (Exception $e)
                    {
                        return array('exception' => $e->getMessage());
                    }
                }
            }
            else
            {
                return array('exception' => 'You are not authorized to tweet..');
            }
        }
        else
        {
            return array('exception' => 'You have nothing to tweet..');
        }              
    }
}
