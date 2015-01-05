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
     * components property
     * 
     * @var array
     * @access public
     */
    public $components = array('CakeS3.CakeS3' => array(
            's3Key' => AMAZON_S3_KEY,
            's3Secret' => AMAZON_S3_SECRET_KEY,
            'bucket' => BUCKET_NAME,
            'endpoint' => END_POINT
    ));

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
    public function getscreenshot() {
        $this->layout = 'share';
        $sharingId = $this->request->named['shareid'];
        ;

        $paramSharing = array('conditions' => array('_id' => new MongoId($sharingId)));
        $sharingDetailArr = $this->Sharing->find('first', $paramSharing);
        //print_r($sharingDetailArr);        

        $chat_id = $this->request->named['chatid'];

        // fetching chat details by id...
        $paramChats = array('conditions' => array('_id' => new MongoId($chat_id)));
        $chatDetailArr = $this->Chat->find('first', $paramChats);

        // get sender detail..
        $senderId = $chatDetailArr['Chat']['userId'];
        $senderDataArr = $this->User->find('first', array('conditions' => array('_id' => $senderId)));
        $senderDetail = array(
            '_id' => $senderDataArr['User']['_id'],
            'name' => $senderDataArr['User']['name'],
            'user_pic' => $senderDataArr['User']['user_pic']
        );

        $relationshipId = $chatDetailArr['Chat']['relationshipId'];

        // get receiver detail..
        $receiverDataArr = $this->User->find('first', array('conditions' => array(
                '_id' => new MongoId($chatDetailArr['Chat']['userId']), 'relationships.id' => new MongoId($relationshipId)
        )));
        $releationshipArr = $receiverDataArr['User']['relationships'];
        $receiverUserId = '';
        if (count($releationshipArr) > 0) {
            foreach ($releationshipArr as $rsArr) {
                if ($relationshipId == (string) $rsArr['id']) {
                    $receiverUserId = $rsArr['partner_id'];
                    break;
                }
            }
        }
        if ($receiverUserId != '') {
            $receiverUserDataArr = $this->User->find('first', array('conditions' => array('_id' => new MongoId($receiverUserId))));
            $receiverUserDetail = array(
                '_id' => $receiverUserDataArr['User']['_id'],
                'name' => $receiverUserDataArr['User']['name'],
                'user_pic' => $receiverUserDataArr['User']['user_pic']
            );
        }

        $this->set('sharingDetailArr', $sharingDetailArr);
        $this->set('chatDetailArr', $chatDetailArr);
        $this->set('senderDetail', $senderDetail);
        $this->set('receiverUserDetail', $receiverUserDetail);
        $this->render('/Pages/getscreenshot');
    }

    /**
     * generatethumb method
     *  
     */
    public function generatethumb() {
        $userArr = $this->User->find('all', array('conditions' => array('verified' => true)));
        if (count($userArr) > 0) {
            foreach ($userArr as $uArr) {
                // Setting image path
                echo 'User S3 image: ' . $uArr['User']['user_pic'];
                echo '<br>';
                if (substr_count($uArr['User']['user_pic'], 'amazonaws') > 0) {
                    $file_content = file_get_contents(str_replace('https', 'http', $uArr['User']['user_pic'])); //exit;
                    $user_path = WWW_ROOT . "images/user_pics/" . (string) $uArr['User']['_id'];
                    $fullpath = $user_path . "/profile_pic.jpg";

                    // Create path if not found
                    if (!file_exists($user_path)) {
                        mkdir($user_path);
                        // Provide required permissions to folder
                        chmod($user_path, 0777);
                    }

                    touch($fullpath);
                    chmod($fullpath, 0777);
                    $png_file = fopen($fullpath, 'wb');
                    fwrite($png_file, $file_content);
                    fclose($png_file);
                    echo 'User Id: ' . $uArr['User']['_id'];
                    echo '<br>';
                    $fullpathThumb = $user_path . "/thumb_profile_pic.jpg";
                    $this->resize_image($fullpath, $fullpathThumb, 150, 150, false);
                    $response = $this->CakeS3->putObject($fullpathThumb, 'user_pics' . DS . $uArr['User']['_id'] . '_thumb_profile_pic.jpg', $this->CakeS3->permission('public_read_write'));
                    echo 'S3 Image Url: ' . $imageUrl = $response['url'] . '<br><br>';
                }
            }
        }
        //echo '<pre>';//print_r($userArr);
        exit;
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
