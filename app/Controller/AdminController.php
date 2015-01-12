<?php

App::uses('AppController', 'Controller');

class AdminController extends AppController {

    public $uses = array('User', 'Category', 'Card', 'Admin', 'Newsfeeds', 'Sharing', 'Feedback', 'Commentstar', 'Notification');
    public $components = array('Session', 'Paginator', 'CakeS3.CakeS3' => array(
            's3Key' => AMAZON_S3_KEY,
            's3Secret' => AMAZON_S3_SECRET_KEY,
            'bucket' => BUCKET_NAME,
            'endpoint' => END_POINT
    ));
    public $limitPerPage = 10;

    public function beforeFilter() {
        parent::beforeFilter();

        if ($this->Session->read('User.loginUserID') == '' && $this->Session->read('User.loginUserName') == '' && $this->action != 'login') {
            $this->redirect('login');
        }
    }

    public function login() {
        if ($this->request->is('post')) {

            $args = $this->request->data('Admin');
            $username = $args['username'];
            $password = $args['password'];

            $params = array(
                'fields' => array('_id', 'username', 'name'),
                'conditions' => array('username' => $username, 'password' => $password)
            );
            $data = $this->Admin->find('first', $params);
            if (count($data) > 0) {
                $this->Session->write('User.loginUserID', $data['Admin']['_id']);
                $this->Session->write('User.loginUserName', $data['Admin']['username']);
                $this->redirect('dashboard');
            } else {
                $this->Session->setFlash(__('Invalid username or password, try again'), 'default', array('class' => 'alert alert-danger'));
            }
        }
        $this->set('title_for_layout', 'Login');
        $this->set('page_title', 'Login');
        $this->render('/admin/login');
    }

    public function logout() {
        $this->Session->destroy();
        $this->redirect('login');
    }

    public function dashboard() {

        $dataCount = $this->User->find('count');
        $cardCount = $this->Card->find('count');
        $shareCount = $this->Sharing->find('count');
        $categoryCount = $this->Category->find('count');

        $this->set('userCount', $dataCount);
        $this->set('cardCount', $cardCount);
        $this->set('shareCount', $shareCount);
        $this->set('categoryCount', $categoryCount);
        $this->set('title_for_layout', 'Dashboard');
        $this->set('page_title', 'Dashboard');
        $this->render('/admin/dashboard');
    }

    public function categories() {

        /* $this->Paginator->settings = array(
          'Category' => array(
          'limit' => 5,
          'order' => array('created' => 'desc')
          )
          );
          $categories = $this->Paginator->paginate('Category'); */

        $categories = $this->Category->find('all', array('order' => array('order' => 'asc')));

        $this->set('categories', $categories);
        $this->set('title_for_layout', 'Categories');
        $this->set('page_title', 'Categories');
        $this->render('/admin/categories');
    }

    public function addcategory() {

        if (!empty($this->request->data)) {

            $maxOrderCatNameAr = $this->Category->find('first', array('order' => array('order' => 'desc')));
            if (count($maxOrderCatNameAr) > 0) {
                $maxOrder = $maxOrderCatNameAr['Category']['order'] + 1;
            } else {
                $maxOrder = 1;
            }

            $this->request->data['Category']['order'] = $maxOrder;
            if ($this->Category->save($this->request->data)) {
                $this->Session->setFlash(__('Category has been saved.'), 'default', array('class' => 'alert alert-success'));
                return $this->redirect('/admin/categories');
            } else {
                $this->Session->setFlash(__('Category did not save. Please try again..'), 'default', array('class' => 'alert alert-danger'));
            }
        }
        $this->set('title_for_layout', 'Add Category');
        $this->set('page_title', 'Add Category');
        $this->render('/admin/addcategory');
    }

    public function editcategory() {

        $id = $this->request->named['id'];
        $this->Category->id = $id;
        // Has any form data been POSTed?
        if ($this->request->is('post')) {
            // If the form data can be validated and saved...
            if ($this->Category->save($this->request->data)) {
                // Set a session flash message and redirect.
                $this->Session->setFlash(__('Category has been updated.'), 'default', array('class' => 'alert alert-success'));
                return $this->redirect('/admin/categories');
            }
        }

        // If no form data, find the recipe to be edited
        // and hand it to the view.
        $this->set('categoryData', $this->Category->find('first', array('conditions' => array('_id' => new MongoId($id)))));
        $this->set('title_for_layout', 'Edit Category');
        $this->set('page_title', 'Edit Category');
        $this->render('/admin/editcategory');
    }

    public function ordercategories() {
        $categoryIdArr = explode('##', trim($this->request->data['displayOrders'], '##'));
        if (count($categoryIdArr) > 0) {
            $rowCount = 1;
            foreach ($categoryIdArr as $catId) {
                $params = array('conditions' => array('_id' => $catId));
                $results = $this->Category->find('first', $params);
                $results['Category']['order'] = $rowCount;
                $this->Category->save($results);
                $rowCount++;
            }
        }
        exit;
    }

    public function cards() {

        $this->Paginator->settings = array(
            'Card' => array(
                'limit' => $this->limitPerPage,
                'order' => array('title' => 'asc')
            )
        );
        $cards = $this->Paginator->paginate('Card');

        //$cards = $this->Card->find('all', array('order' => array('title' => 'asc')));

        $this->set('cards', $cards);
        $this->set('title_for_layout', 'Cards');
        $this->set('page_title', 'Cards');
        $this->render('/admin/cards');
    }

    public function addcard() {

        if (!empty($this->request->data)) {

            $categoryStr = $this->request->data['Card']['category'];
            $categoryArr = explode(',', $categoryStr);

            $newCatArr = array();
            foreach ($categoryArr as $catAr) {
                $catDetail = $this->Category->find('first', array('conditions' => array('name' => $catAr)));
                $newCatArr[] = array('_id' => (string) $catDetail['Category']['_id'], 'name' => $catAr);
            }

            // upload file on server..
            if ($_FILES['data']['name']['Card']['image'] != '') {
                $imageName = $_FILES['data']['name']['Card']['image'];
                //move_uploaded_file($_FILES['data']['tmp_name']['Card']['image'], WWW_ROOT . "images/" . $imageName);
                $response = $this->CakeS3->putObject($_FILES['data']['tmp_name']['Card']['image'], 'cards/' . $imageName, $this->CakeS3->permission('public_read_write'));
                $this->request->data['Card']['image'] = $response['url'];

                // for 1080 dimension image.
                $imageName1080 = $_FILES['data']['name']['Card']['image1080'];
                $this->CakeS3->putObject($_FILES['data']['tmp_name']['Card']['image1080'], 'cards/a/1080/' . $imageName1080, $this->CakeS3->permission('public_read_write'));
                // for 720 dimension image.
                $imageName720 = $_FILES['data']['name']['Card']['image720'];
                $this->CakeS3->putObject($_FILES['data']['tmp_name']['Card']['image720'], 'cards/a/720/' . $imageName720, $this->CakeS3->permission('public_read_write'));
            }

            $this->request->data['Card']['category'] = $newCatArr;
            $this->request->data['Card']['user_id'] = '0';
            if ($this->Card->save($this->request->data)) {
                $this->Session->setFlash(__('Card has been saved.'), 'default', array('class' => 'alert alert-success'));
                return $this->redirect('/admin/cards');
            } else {
                $this->request->data['Card']['category'] = $categoryStr;
                $this->Session->setFlash(__('Card did not save. Please try again..'), 'default', array('class' => 'alert alert-danger'));
            }
        }
        $this->set('categoryData', $this->Category->find('all'));

        $this->set('title_for_layout', 'Add Card');
        $this->set('page_title', 'Add Card');
        $this->render('/admin/addcard');
    }

    public function editcard() {

        $id = $this->request->named['id'];

        // Has any form data been POSTed?
        if ($this->request->is('post')) {

            $categoryStr = $this->request->data['Card']['category'];
            $categoryArr = explode(',', $categoryStr);

            $newCatArr = array();
            foreach ($categoryArr as $catAr) {
                $catDetail = $this->Category->find('first', array('conditions' => array('name' => $catAr)));
                $newCatArr[] = array('_id' => (string) $catDetail['Category']['_id'], 'name' => $catAr);
            }

            // upload file on server..
            if ($_FILES['data']['name']['Card']['image'] != '') {
                $imageName = $_FILES['data']['name']['Card']['image'];
                //move_uploaded_file($_FILES['data']['tmp_name']['Card']['image'], WWW_ROOT . "images/" . $imageName);
                $response = $this->CakeS3->putObject($_FILES['data']['tmp_name']['Card']['image'], 'cards/' . $imageName, $this->CakeS3->permission('public_read_write'));
                $this->request->data['Card']['image'] = $response['url'];
            } else {
                $this->request->data['Card']['image'] = $this->request->data['Card']['hidimage'];
            }
            unset($this->request->data['Card']['hidimage']);
            if ($_FILES['data']['name']['Card']['image1080'] != '') {
                $imageName = $_FILES['data']['name']['Card']['image1080'];
                $response = $this->CakeS3->putObject($_FILES['data']['tmp_name']['Card']['image1080'], 'cards/a/1080/' . $imageName, $this->CakeS3->permission('public_read_write'));
            }
            if ($_FILES['data']['name']['Card']['image720'] != '') {
                $imageName = $_FILES['data']['name']['Card']['image720'];
                $response = $this->CakeS3->putObject($_FILES['data']['tmp_name']['Card']['image720'], 'cards/a/720/' . $imageName, $this->CakeS3->permission('public_read_write'));
            }

            $this->request->data['Card']['_id'] = $id;
            $this->request->data['Card']['category'] = $newCatArr;
            $this->request->data['Card']['user_id'] = '0'; //$this->Session->read('User.loginUserID');
            // If the form data can be validated and saved...
            if ($this->Card->save($this->request->data)) {
                // Set a session flash message and redirect.
                $this->Session->setFlash(__('Card has been updated.'), 'default', array('class' => 'alert alert-success'));
                return $this->redirect('/admin/cards');
            }
        }

        // If no form data, find the recipe to be edited
        // and hand it to the view.
        $this->set('categoryData', $this->Category->find('all'));
        $this->set('cardData', $this->Card->find('first', array('conditions' => array('_id' => new MongoId($id)))));
        $this->set('title_for_layout', 'Edit Card');
        $this->set('page_title', 'Edit Card');
        $this->render('/admin/editcard');
    }

    public function deletecard() {
        $id = $this->request->named['id'];

        $this->Card->delete(new MongoId($id));

        $this->Session->setFlash(__('Card has been deleted.'), 'default', array('class' => 'alert alert-success'));
        return $this->redirect('/admin/cards');
    }

    public function reportedinappropriatenewsfeeds() {

        $this->Paginator->settings = array(
            'Newsfeeds' => array(
                'conditions' => array('inappropriatedby_user_list' => array('$exists' => TRUE)),
                'limit' => $this->limitPerPage,
                'order' => array('newsfeed_msg' => 'asc')
            )
        );
        $newsfeeds = $this->Paginator->paginate('Newsfeeds');
        //print_r($newsfeeds);exit;
        $this->set('newsfeeds', $newsfeeds);
        $this->set('title_for_layout', 'Reported Inappropriate Newsfeeds');
        $this->set('page_title', 'Reported Inappropriate Newsfeeds');
        $this->render('/admin/reportedinappropriatenewsfeeds');
    }

    public function deletenewsfeed() {
        $id = $this->request->named['id'];

        $this->Notification->deleteAll(array('newsfeed_id' => $id));
        $this->Commentstar->deleteAll(array('newsfeed_id' => $id));
        $this->Newsfeeds->delete(new MongoId($id));

        $this->Session->setFlash(__('Newsfeed has been deleted.'), 'default', array('class' => 'alert alert-success'));
        return $this->redirect('/admin/reportedinappropriatenewsfeeds');
    }

    public function viewnewsfeed() {

        $id = $this->request->named['id'];

        $newsfeed = $this->Newsfeeds->find('first', array('conditions' => array('_id' => new MongoId($id))));
        $commentStars = $this->Commentstar->find('all', array('conditions' => array('newsfeed_id' => $id)));
        $creatorData = $this->User->find('first', array('conditions' => array('_id' => new MongoId($newsfeed['Newsfeeds']['user_id']))));

        // If no form data, find the recipe to be edited
        // and hand it to the view.
        $this->set('newsfeed', $newsfeed);
        $this->set('commentStars', $commentStars);
        $this->set('creatorData', $creatorData);
        $this->set('title_for_layout', 'View Newsfeed');
        $this->set('page_title', 'View Newsfeed');
        $this->render('/admin/viewnewsfeed');
    }

    public function reportedproblems() {

        $this->Paginator->settings = array(
            'Feedback' => array(
                'limit' => $this->limitPerPage,
                'order' => array('problem_type' => 'asc')
            )
        );
        $feedbacks = $this->Paginator->paginate('Feedback');

        $this->set('feedbacks', $feedbacks);
        $this->set('title_for_layout', 'Reported Problems & Feedbacks');
        $this->set('page_title', 'Reported Problems & Feedbacks');
        $this->render('/admin/reportedproblems');
    }

    public function changepassword() {

        if ($this->request->is('post')) {

            $this->Admin->validate['oldpassword']['required'] = array('rule' => 'notEmpty', 'message' => 'Must be at least 18.');
            $this->Admin->validate['newpassword']['required'] = array('rule' => 'notEmpty', 'message' => 'Must be at least 18.');
            $this->Admin->validate['confirmpassword']['required'] = array('rule' => 'notEmpty', 'message' => 'Must be at least 18.');

            //$this->request->data['Admin']['_id'] = $this->Session->read('User.loginUserID');
            //print_r($this->request->data);exit;
            if ($this->Admin->save($this->request->data)) {
                // valid
                echo 'sadsaf';
                exit;
            } else {
                // invalid
                print_r($this->Admin->validationErrors());
                echo 'invalid';
                exit;
            }
        }

        $this->set('title_for_layout', 'Change Password');
        $this->set('page_title', 'Change Password');
        $this->render('/admin/changepassword');
    }

    public function users() {

        $this->Paginator->settings = array(
            'User' => array(
                'limit' => $this->limitPerPage,
                'conditions' => array('verified' => true),
                'order' => array('User.created' => 'desc')
            )
        );
        $users = $this->Paginator->paginate('User');

        $this->set('users', $users);
        $this->set('title_for_layout', 'Users');
        $this->set('page_title', 'Users');
        $this->render('/admin/users');
    }

}
