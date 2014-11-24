<?php

App::uses('AppController', 'Controller');

class RecoverController extends AppController {
    
    /**
     * use data model property
     * @var array
     * @access public 
     */
    var $uses = array('User');
    public $name = 'Recover';
    
    /**
     * its for redirect to main app site.
     */
    public function index() {
        $this->redirect('http://clickinapp.com/');
    }

    /**
     * password method
     * this function is used for recover password of any user when he will have 
     * email for recovery for which he requested on app...
     * 
     * @return \CakeResponse
     * access public
     */
    public function password() {
        // http://localhost/clickin/recover/password/id:537ccd55252e08f467ef8b81/rid:IinPC2iq8pB8
        
        $this->layout = 'front';
        
        if($this->request->params['named']['id'] == '' || $this->request->params['named']['rid'] == '') 
        {
            $this->Session->setFlash(__('Invalid url.'), 'default', array('class' => 'alert alert-danger'));
            $args = array('error' => 1);
        }
        else
        {
            $args = $this->request->data('User');
            $args = !empty($args) ? $args : array();
            
            $params = array(
                'fields' => array('_id', 'recoverystr'),
                'conditions' => array(
                                '_id' => new MongoId($this->request->params['named']['id']), 
                                'recoverystr' => $this->request->params['named']['rid']
                )
            );
            $data = $this->User->find('first', $params);
            //print_r($data);exit;
            if(count($data)==0){
                $this->Session->setFlash(__('Invalid request.'), 'default', array('class' => 'alert alert-danger'));
                $args = array_merge($args, array('error' => 1));
            } else {          
                $args = array_merge($args, array('error' => 0));
            }
            switch (true) {
                case $this->request->is('post') && !empty($args['password'])  && !empty($args['confirmpassword']) && ($args['password']==$args['confirmpassword'] 
                         && strlen($args['password'])>=8 && strlen($args['confirmpassword'])>=8) : 
                    if (count($data) > 0) {
                        $dataArray = array();
                        $dataArray['User']['_id'] = $data['User']['_id'];
                        $dataArray['User']['recoverystr'] = '';
                        $dataArray['User']['password'] = md5($args['password']);
                        $this->User->save($dataArray);
                        $this->Session->setFlash(__('Your password has been saved.'), 'default', array('class' => 'alert alert-success'));
                    }
                    break;
                case $this->request->is('post') && isset($args['password']) && ($args['password'] == ''):
                    $this->Session->setFlash(__('Please enter new password.'), 'default', array('class' => 'alert alert-danger'));
                    break;
                case $this->request->is('post') && isset($args['password']) && ($args['password'] != '' && strlen($args['password'])<8):
                    $this->Session->setFlash(__('New password should be atleast 8 characters long.'), 'default', array('class' => 'alert alert-danger'));
                    break;
                case $this->request->is('post') && isset($args['confirmpassword']) && ($args['confirmpassword'] == ''): 
                    $this->Session->setFlash(__('Please enter confirm password.'), 'default', array('class' => 'alert alert-danger'));
                    break;
                case $this->request->is('post') && isset($args['confirmpassword']) && ($args['confirmpassword'] != '' && strlen($args['confirmpassword'])<8): 
                    $this->Session->setFlash(__('Confirm password should be atleast 8 characters long.'), 'default', array('class' => 'alert alert-danger'));
                    break;
                case $this->request->is('post') && isset($args['confirmpassword']) && isset($args['password']) && ($args['confirmpassword'] != $args['password']): 
                    $this->Session->setFlash(__('Password and confirm password should be same.'), 'default', array('class' => 'alert alert-danger'));
                    break;
            }
        }
        
        $this->set('data', $args);
        $this->set('title_for_layout', 'Recover Password');
        $this->set('page_title', 'Recover Password');
        $this->render('/recover/password');
        
    }
    
}
