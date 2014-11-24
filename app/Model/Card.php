<?php

class Card extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';
    var $useTable = 'cards';

    /*  _id
        title
        description (empty for custom cards)
        image (empty for custom cards)
        user_id (0 by default)
        category : [ 
        {_id, name}
        {_id, name}
        ]
        active (yes or no)
    */
    public $validate = array(
        'title' => array(
            'required' => array(
                'rule' => array('notEmpty'),
                'message' => 'Please enter title.'
            )
        ),
        /*'category' => array(
            'required' => array(
                'rule' => array('notEmpty'),
                'message' => 'Please enter category.'
            )
        ),*/
        'description' => array(
            'required' => array(
                'rule' => array('notEmpty'),
                'message' => 'Please enter description.'
            )
        )
    );
    
    /**
     * saveCard method
     * this function is used to save comment/star detail and update chat & newsfeed collections for comment_count / star_count..
     * 
     * @param array $dataArray
     * @access public
     */
    public function saveCard($dataArray)
    {
        // Generate new id for sharing
        $insert_id = new MongoId();  
        
        $Category = ClassRegistry::init('Category');
        
        $categorySaveArr = array();
        if(count($dataArray['category'])>0) {
            // fetch categories details..
            $categoryDetail = $Category->find('all', array(
                                                    'fields' => array('_id','name'), 
                                                    'conditions' => array('name' => array('$in' => $dataArray['category'])))
            );            
            
            if(count($categoryDetail)>0) {
                foreach ($categoryDetail as $valArr) {
                    $categorySaveArr[] = $valArr['Category'];
                }
            }
        }
        
        $dataArray = array_merge($dataArray, array(
            '_id' => $insert_id,
            'description' => '',
            'image' => '',
            'category' => $categorySaveArr,
            'active' => 'yes',
        ));
        // save data in commentstar collection..
        unset($this->validate);
        return $this->save($dataArray);
    }
    
    public function fetchCards($user_id = 0)
    {
        if($user_id != 0)
            $conditions['$or'] = array(array('user_id' => '0'), array('user_id' => $user_id));
        else
            $conditions['user_id'] = $user_id;
        
        $order = array('_id' => -1);
        
        $params = array(
            'conditions' => $conditions,
            'order' => $order,
            //'limit' => 25,
            //'page' => 1
        );
        // Find records matching phone no.
        $results = $this->find('all', $params);

        return $results;
    }
}