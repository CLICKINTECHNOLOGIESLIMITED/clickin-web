<?php

class Feedback extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';
    var $useTable = 'feedbacks';

    /*
        _id
        problem_type (‘spamorabuse’, ‘notworking’, ‘feedback’)
        spam_or_abuse_type (Optional - will added for problem_type = ‘spamorabuse’)
        comment
        user_id
    */

}