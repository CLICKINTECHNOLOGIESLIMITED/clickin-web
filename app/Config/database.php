<?php
class DATABASE_CONFIG {

	public $default = array(
		'datasource' => DATABASE_DATASOURCE,
		'host' => DATABASE_HOST,
		'database' => DATABASE_NAME,
                'port' => DATABASE_PORT,
                'persistent' => false,
		'replicaset' => array(
                                'host' => DATABASE_REPLICA_HOST,
                                'options' => array('replicaSet' => DATABASE_REPLICA_SET)
                )
	);
        var $mongo = array(
		'datasource' => DATABASE_DATASOURCE,
		'host' => DATABASE_HOST,
		'database' => DATABASE_NAME,
		'port' => DATABASE_PORT,
		'persistent' => true,
                'replicaset' => array(
                                'host' => DATABASE_REPLICA_HOST,
                                'options' => array('replicaSet' => DATABASE_REPLICA_SET)
                )
	);

}
