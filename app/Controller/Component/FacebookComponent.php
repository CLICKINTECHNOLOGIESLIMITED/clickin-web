<?php

App::uses('Component', 'Controller');

App::import('Vendor', 'facebook/facebook');

App::import('Component', 'Image');

App::import('Component', 'CakeS3.CakeS3');

class FacebookComponent extends Component {

    /**
     * for set facebook object.
     * @var object 
     */
    var $facebook;

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

    /**
     * S3 Component property
     *
     * @access public
     */
    var $cakes3c;

    public function __construct() {

        // initialize image component..
        $this->Image = & new ImageComponent(new ComponentCollection());

        // initialize s3 component..
        $this->cakes3c = & new CakeS3Component(new ComponentCollection(), array(
            's3Key' => AMAZON_S3_KEY,
            's3Secret' => AMAZON_S3_SECRET_KEY,
            'bucket' => BUCKET_NAME,
            'endpoint' => END_POINT
        ));

        // configure basic setup for fb..
        $config = array(
            'appId' => FACEBOOK_CLIENT_ID,
            'secret' => FACEBOOK_CLIENT_SECRET,
            'cookie' => true
        );
        $this->facebook = new Facebook($config);

        // set sharing, user & chat data model objects.
        $this->Sharing = ClassRegistry::init('Sharing');
        $this->Chat = ClassRegistry::init('Chat');
        $this->User = ClassRegistry::init('User');
    }

    /**
     * facebookshare method
     * this function is used to share text, image, audio & videos on facebook..
     * 
     * @param int $sharingId
     * @return array
     * @access public
     */
    public function facebookshare($sharingId) {

        //$chatId = "52eb7ab1038c70c54d000000";
        //$newsfeedId = "52eb7c42038c701d30000017";
        //$sharingId = "52f8df35038c70cb4d000000";
        // fetching newsfeed details by id...
        $paramSharing = array('conditions' => array('_id' => new MongoId($sharingId)));
        $sharingDetailArr = $this->Sharing->find('first', $paramSharing);

        // fetching chat details by id...
        $paramChats = array('conditions' => array('_id' => new MongoId($sharingDetailArr['Sharing']['chat_id'])));
        $chatDetailArr = $this->Chat->find('first', $paramChats);

        // posting on user's wall...
        if (count($sharingDetailArr) > 0) {
            $access_token = $sharingDetailArr ['Sharing']['fb_access_token'];

            $this->facebook->setAccessToken($access_token);
            $userDetail = $this->facebook->api("/me");

            $params = array("access_token" => $access_token);
            if (count($userDetail) > 0 && count($chatDetailArr) > 0) {
                $chatTypeArr = array('1' => 'Text', '2' => 'Image', '3' => 'Audio', '4' => 'Video', '5' => 'Cards', '6' => 'Location');

                $screenshotUrl = HOST_ROOT_PATH . "pages/getscreenshot/chatid:" . $sharingDetailArr['Sharing']['chat_id'] . "/shareid:" . $sharingId;

                /* if ($chatDetailArr['Chat']['clicks'] !== NULL || ($chatDetailArr['Chat']['type'] == 1 || $chatDetailArr['Chat']['type'] == 2 ||
                  $chatDetailArr['Chat']['type'] == 3 || $chatDetailArr['Chat']['type'] == 4 || $chatDetailArr['Chat']['type'] == 6))
                  { */

                    //$params = array_merge ($params,array("picture" => $chatDetailArr['Chat']['content']));
                    //$sharingImageUrl = $this->Image->watermarkingOnImage($chatDetailArr['Chat']['content']);
                    // generating random image name for tomporary file path.
                    $randomImageName = strtotime(date('Y-m-d H:i:s'));
                    $fileName = $randomImageName . '.png';
                    $srcImagePath = WWW_ROOT . 'images/' . $fileName;
                    $fp = fopen($srcImagePath, 'w+');
                    chmod($srcImagePath, 0777);
                    fclose($fp);

                    //xvfb-run --server-args="-screen 0, 1024x680x24" ./wkhtmltoimage --use-xserver --quality 83 --javascript-delay 200 http://google.com pawan.png
                    $execOptions = "--use-xserver --load-error-handling ignore --crop-w 550 --crop-x 245 --crop-y 0 --quality 90 --javascript-delay 300";
                    // run wkhtmltoimage for capturing screenshot from url..
                    exec("xvfb-run --server-args=\"-screen 0, 1024x680x24\" /usr/local/bin/wkhtmltoimage $execOptions $screenshotUrl $srcImagePath");
                    
                    // upload file on s3 and delete from local folder..
                    $response = $this->cakes3c->putObject($srcImagePath, $fileName, $this->cakes3c->permission('public_read_write'));
                    //if($response['url']!='')
                    //    unlink($srcImagePath);        
                    $sharingImageUrl = $response['url'];
                    
                    if ($chatDetailArr['Chat']['type'] != 3 && $chatDetailArr['Chat']['type'] != 4) {
                        $params = array_merge($params, array("picture" => $sharingImageUrl, "link" => $sharingImageUrl));
                    }

                $query_params = array('conditions' => array('_id' => new MongoId($chatDetailArr['Chat']['userId'])));
                $userDetailArr = $this->User->find('first', $query_params);

                $message = ($chatDetailArr['Chat']['message'] != '') ? $chatDetailArr['Chat']['message'] :
                        $userDetailArr['User']['name'] . " shared a " . $chatTypeArr[$chatDetailArr['Chat']['type']] . ".";
                $params = array_merge($params, array("message" => $message, "name" => $message)); // , "name" => $message

                $params = array_merge($params, array("caption" => SUPPORT_SENDER_EMAIL_NAME));

                if ($chatDetailArr['Chat']['clicks'] !== NULL) {

                    // get relation user detail..
                    $chat_relation_id = $chatDetailArr['Chat']['relationshipId'];
                    $query_params = array('conditions' => array(
                            'relationships.id' => new MongoId($chat_relation_id),
                            'relationships.partner_id' => $chatDetailArr['Chat']['userId']
                    ));
                    $relUserDetailArr = $this->User->find('first', $query_params);
                    $partner_name = $relUserDetailArr["User"]["name"];
                    
                    //$params = array_merge($params, array("message" => $userDetailArr['User']['name'] . " shared " . $chatDetailArr['Chat']['clicks'] . " clicks."));
                    $params = array_merge($params, array("message" => $userDetailArr['User']['name'] . " just Clicked with $partner_name."));
                    $params = array_merge($params, array("name" => $chatDetailArr['Chat']['message']));
                    if ($chatDetailArr['Chat']['message'] == '')
                        $params = array_merge($params, array("name" => $userDetailArr['User']['name'] . " just Clicked with $partner_name."));
                        //$params = array_merge($params, array("name" => $userDetailArr['User']['name'] . " just Clicked with " . $chatDetailArr['Chat']['clicks'] . "."));
                }

                // for audio posting..
                if ($chatDetailArr['Chat']['type'] != '') {
                    if ($chatDetailArr['Chat']['type'] == 3)
                        $params = array_merge($params, array("picture" => $sharingImageUrl, "link" => $chatDetailArr['Chat']['content']));
                    elseif ($chatDetailArr['Chat']['type'] == 4)
                        $params = array_merge($params, array("link" => $chatDetailArr['Chat']['content'], 'picture' => $sharingImageUrl));
                        //$params = array_merge($params, array("link" => $chatDetailArr['Chat']['content'], 'source' => $chatDetailArr['Chat']['video_thumb']));
                }

                //}                
            }

            // text or images...
            /* $params = array(
              "access_token" => $access_token,
              "message" => "Here is a blog post about auto posting on Facebook using PHP facebook",
              "link" => "http://www.pontikis.net/blog/auto_post_on_facebook_with_php",
              "picture" => "http://i.imgur.com/lHkOsiH.png",
              "name" => "How to Auto Post on Facebook with PHP",
              "caption" => "www.pontikis.net",
              "description" => "Automatically post on Facebook with PHP using Facebook PHP SDK."
              );

              // for video..
              $params = array(
              "access_token" => $access_token,
              'link'=>'http://www.youtube.com/watch?v=seBpXt8_6xs',
              'name' => 'custom video name',
              'caption'=>'custom video caption',
              'description'=> 'custom video description',
              'source' => 'http://i.imgur.com/lHkOsiH.png',
              ); */
            //print_r($params);
            try {
                return $postdetails = $this->facebook->api("/me/feed", "post", $params);
            } catch (Exception $e) {
                return array('exception' => $e->getMessage());
            }
        } else {
            return array('exception' => 'data not avaiable for share.');
        }
    }

    /**
     * getfriends method
     * this function is used to get friend list of any user by access token and/or facebook id.
     * 
     * @param string $access_token
     * @param integer $facebookId
     * @return array
     * @access public
     */
    public function getfriends($access_token, $facebookId = '') {
        // set access token..
        $this->facebook->setAccessToken($access_token);
        // get friend list..
        return $userDetail = $this->facebook->api("/" . ($facebookId != '' ? $facebookId : "me") . "/friends?fields=installed,name");
    }

    /**
     * getUserInfo method
     * this function is used to get user info of any user by access token.
     * 
     * @param string $access_token
     * @return array
     * @access public
     */
    public function getUserInfo($access_token) {
        // set access token..
        $this->facebook->setAccessToken($access_token);
        // get friend list..
        return $userDetail = $this->facebook->api("/me");
    }

}
