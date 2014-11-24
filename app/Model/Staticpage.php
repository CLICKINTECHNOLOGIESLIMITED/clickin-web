<?php

class Staticpage extends AppModel {

    public $primaryKey = '_id';
    var $useDbConfig = 'mongo';
    var $useTable = 'static_pages';
 
    /*
        "_id" : ObjectId("5319d3ec212e0876346f7ed6"),
        "title" : "description 1",
        "description" : "description 1",
        "slug" : "description-1",
        "active" : "yes",
        "modified" : ISODate("2014-05-07T14:13:00.695Z"),
        "created" : ISODate("2014-05-07T14:13:00.695Z")
     */
       
}