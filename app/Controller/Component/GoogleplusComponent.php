<?php

App::uses('Component', 'Controller');

App::import('Vendor', 'google/Google_Client');
App::import('Vendor', 'google/contrib/Google_PlusService');

class GoogleplusComponent extends Component {
    
    /**
     * for set google client object.
     * @var object 
     */
    var $gClient;
    
    /**
     * for set google plus object.
     * @var object 
     */
    var $gPlus;
    
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
    
    public function __construct() {
        
        // configure basic setup for google plus..
        $this->gClient = new Google_Client();
        $this->gClient->setApplicationName(GOOGLE_PLUS_APPNAME);
        $this->gClient->setClientId(GOOGLE_PLUS_CLIENT_ID);
        $this->gClient->setClientSecret(GOOGLE_PLUS_CLIENT_SECRET);
        $this->gClient->setRedirectUri(GOOGLE_PLUS_CLIENT_RETURN_URL);
        $this->gClient->setDeveloperKey(GOOGLE_PLUS_CLIENT_KEY);
        
        $requestVisibleActions = array(
            'http://schemas.google.com/AddActivity',
            'http://schemas.google.com/ReviewActivity'
        );                
        $this->gClient->setRequestVisibleActions($requestVisibleActions);
        
        $this->gPlus = new Google_PlusService($this->gClient);
        
        // set sharing, user & chat data model objects.
        $this->Sharing = ClassRegistry::init('Sharing');
        $this->Chat = ClassRegistry::init('Chat');
        $this->User = ClassRegistry::init('User');
    }
    
    /**
     * gplusshare method
     * this function is used to share text, image, audio & videos on google plus..
     * 
     * @param int $sharingId
     * @return array
     * @access public
     */
    public function gplusshare($sharingId) {
        
        //echo '<pre>';print_r(array($this->gClient,$this->gPlus));
        $this->gClient->authenticate();
        $result = $this->gClient->getAccessToken();
        $data = json_decode($result);
        //print_r($data);
        //echo $_REQUEST["code"];
        if(isset($_REQUEST["code"]))
        {
            
            $me = $this->gPlus->people->get('me');
            
            $moment_body = new Google_Moment();
            $moment_body->setType("http://schemas.google.com/AddActivity");
            
            $item_scope = new Google_ItemScope();
            $item_scope->setId('KJHKJHU8098');
            $item_scope->setType('http://schema.org/CreativeWork');
            $item_scope->setName('asfdsa dsa fdsa ');
            $item_scope->setDescription('asf dsafdsa fdsa fdsa fdsa ds dsa');
            $item_scope->setImage('http://www.google.com/s2/static/images/GoogleyEyes.png');
            //$item_scope->setUrl("https://developers.google.com/+/plugins/snippet/examples/thing");
            
            $moment_body->setTarget($item_scope);
            
            $momentResult = $this->gPlus->moments->insert('me', 'vault', $moment_body);                
            print_r($momentResult);
            die;
        }     
            /*
             # This example shows how to create a moment that is associated with a URL that has schema.org markup.
            $moment_body = new Google_Moment();
            $moment_body->setType("http://schemas.google.com/AddActivity");
            $item_scope = new Google_ItemScope();
            $item_scope->setUrl("https://developers.google.com/+/plugins/snippet/examples/thing");
            $moment_body->setTarget($item_scope);
            $momentResult = $plus->moments->insert('me', 'vault', $moment_body);

            # This example shows how to create moment that does not have a URL.
            $moment_body = new Google_Moment();
            $moment_body->setType("http://schemas.google.com/AddActivity");
            $item_scope = new Google_ItemScope();
            $item_scope->setId("target-id-1");
            $item_scope->setType("http://schemas.google.com/AddActivity");
            $item_scope->setName("The Google+ Platform");
            $item_scope->setDescription("A page that describes just how awesome Google+ is!");
            $item_scope->setImage("https://developers.google.com/+/plugins/snippet/examples/thing.png");
            $moment_body->setTarget($item_scope);
            $momentResult = $plus->moments->insert('me', 'vault', $moment_body);
             */
            
            //}
        
        exit;
    }
}
