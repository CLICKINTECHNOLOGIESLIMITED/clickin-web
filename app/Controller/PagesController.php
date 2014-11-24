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
 *
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
class PagesController extends AppController {

    /**
     * This controller uses Staticpage model
     *
     * @var array
     */
    public $uses = array('Staticpage', 'Sharing', 'User', 'Chat', 'Newsfeeds');
    
    /**
     * index method
     *
     * @return void
     * @access public
     */
    public function index() {
        $this->layout = 'front';
        
        $slug = $this->request->params['slug'];
        $staticPageData = $this->Staticpage->find('first', array('conditions' => array('slug' => $slug)));
        
        //$staticPageData['Staticpage']['description'] = addslashes('');        
        //$this->Staticpage->save($staticPageData);
        
        $this->set('staticPageData', $staticPageData);
        $this->set('title_for_layout', $staticPageData['Staticpage']['title']);
        $this->set('page_title', $staticPageData['Staticpage']['title']);
        $this->render('/Pages/index');
    }    

    /**
     * getscreenshot method
     *
     * @return void
     * @access public
     */
    public function getscreenshot()
    {
        $this->layout = 'share';
        // clicks : "53726076252e08f7358b4567"
        // image + clicks : "537242b9252e088f2c8b4567"
        // image : "5372489c252e087e478b4567"
        // audio + clicks : "53980044252e0849758b4567"
        // audio : "53a03e4e252e080f4f8b4567"        
        // video + clicks : "53800d2d252e08a67a8b4567"
        // video : "53724df4252e084a618b4567"     
           
        // custom card : "5373509c252e08f0778b4567"
        // trade card :  "5371fbf9252e084f678b4567"
        // location : "53720378252e08e40a8b4567"
        
        $sharingId = $this->request->named['shareid'];;
        
        $paramSharing = array('conditions' => array('_id' => new MongoId($sharingId)));
        $sharingDetailArr = $this->Sharing->find('first', $paramSharing);
        //print_r($sharingDetailArr);        
        
        $chat_id = $this->request->named['chatid'];
        
         // fetching chat details by id...
        $paramChats = array('conditions' => array('_id' => new MongoId($chat_id)));
        $chatDetailArr = $this->Chat->find('first', $paramChats);
        
        // get sender detail..
        $senderId = $chatDetailArr['Chat']['userId'];
        $senderDataArr = $this->User->find('first', array('conditions' => array( '_id' => $senderId)));
        $senderDetail = array(
            '_id'=>$senderDataArr['User']['_id'],
            'name'=>$senderDataArr['User']['name'],
            'user_pic'=>$senderDataArr['User']['user_pic']
        );
        
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
                'name'=>$receiverUserDataArr['User']['name'],
                'user_pic'=>$receiverUserDataArr['User']['user_pic']
            );
        }
                
        $this->set('sharingDetailArr', $sharingDetailArr);
        $this->set('chatDetailArr', $chatDetailArr);
        $this->set('senderDetail', $senderDetail);
        $this->set('receiverUserDetail', $receiverUserDetail);
        $this->render('/Pages/getscreenshot');
    }

    /**
     * Displays a view
     *
     * @param mixed What page to display
     * @return void
     * @throws NotFoundException When the view file could not be found
     * 	or MissingViewException in debug mode.
     */
    public function display() {
        $path = func_get_args();

        $count = count($path);
        if (!$count) {
            return $this->redirect('/');
        }
        $page = $subpage = $title_for_layout = null;

        if (!empty($path[0])) {
            $page = $path[0];
        }
        if (!empty($path[1])) {
            $subpage = $path[1];
        }
        if (!empty($path[$count - 1])) {
            $title_for_layout = Inflector::humanize($path[$count - 1]);
        }
        $this->set(compact('page', 'subpage', 'title_for_layout'));

        try {
            $this->render(implode('/', $path));
        } catch (MissingViewException $e) {
            if (Configure::read('debug')) {
                throw $e;
            }
            throw new NotFoundException();
        }
    }

}
