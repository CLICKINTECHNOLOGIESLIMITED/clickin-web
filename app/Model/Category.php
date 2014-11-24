<?php

class Category extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';
    var $useTable = 'categories';

    /*
      _id
      name
      active  (yes or no)
     */
    public $validate = array(
        'name' => array(
            'required' => array(
                'rule' => array('notEmpty'),
                'message' => 'Please enter name.'
            )
        )
    );

}