<?php

class Admin extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';
    var $useTable = 'admins';
    public $validate = array(
        'username' => array(
            'required' => array(
                'required' => true,
                'message' => 'Please enter username.'
            )
        ),
        'password' => array(
            'rule' => array('minLength', '8'),
            'message' => 'Password should be 8 characters long.'
        )
    );

}