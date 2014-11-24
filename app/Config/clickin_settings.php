<?php
##### Custom Setting file for Clickin app #####
define('DATABASE_DATASOURCE', 'Mongodb.MongodbSource');
define('DATABASE_HOST', '23.21.65.97');
define('DATABASE_USER', '');
define('DATABASE_PASS', '');
define('DATABASE_NAME', 'clickin');
define('DATABASE_PORT', 27017);
define('DATABASE_REPLICA_HOST', '');
define('DATABASE_REPLICA_SET', '');

// Quickblox credentials
/*$config['QB']['host'] = 'https://apiclickin.quickblox.com/';
$config['QB']['app_id'] = '5';
$config['QB']['auth_key'] = '6QQJq2FSKKzHK2-';
$config['QB']['auth_secret'] = 'k9cTQAeFWrkEAWv';
$config['QB']['email'] = 'kabir.chandhoke@sourcefuse.com';
$config['QB']['password'] = 'sourcefuse123';*/
$config['QB']['host'] = 'https://api.quickblox.com/';
$config['QB']['app_id'] = '6768';
$config['QB']['auth_key'] = 'QVr4uK5tt6cu6dN';
$config['QB']['auth_secret'] = '4thHbq-eyLVJrhe';
$config['QB']['email'] = 'clickin@sourcefuse.com';
$config['QB']['password'] = 'sourcefuse1234';

// Quickblox custom object name
$config['QB']['customobject'] = 'chats';

//---------- Twilio ----------//
// Twilio Credentials
//$config['Twilio']['account_sid'] = 'ACb71a56200e83e88564f2f39da2caaccb';
//$config['Twilio']['auth_token'] = '5f53192809aebb95265ecf51e8b8f0b8';
//$config['Twilio']['number'] = '(919) 636-5295';

$config['Twilio']['account_sid'] = 'ACe76a3e9efddd85ac3c490cf249378495';
$config['Twilio']['auth_token'] = '5616327c60383d84d0e071ffe278d901';
$config['Twilio']['number'] = '(208) 578-4493';

define('HOST_ROOT_PATH', Router::fullbaseUrl() . DS);

// Status codes for responses
define('SUCCESS', 200);
define('BAD_REQUEST', 400);
define('UNAUTHORISED', 401);
define('ERROR', 500);

define("CHAT_TYPE_CARD",5);

// device type..
define('DEVICE_TYPE_IOS', 'IOS');
define('DEVICE_TYPE_ANDROID', 'ANDROID');

/**** Facebook ****/
/*define('FACEBOOK_CLIENT_ID', '394694217276809');
define('FACEBOOK_CLIENT_SECRET', '00b88fae22798b103618c3b1e777a964');*/
define('FACEBOOK_CLIENT_ID', '715102098501759');
define('FACEBOOK_CLIENT_SECRET', 'ae3e4809f9f35438723fe9f65e3c6abb');

/**** Twitter ****/
define('TWITTER_CONSUMER_KEY', 'Xe0OqZFhpqTrI15ykmPTg');
define('TWITTER_CONSUMER_SECRET', 'tZDWvrQnVviuXo7Pl1JbkNq0kviLqDf1Xwy5CfPQq0w');
define('TWITTER_OAUTH_ACCESS_TOKEN', '154510499-l3BMu8Q3c0L9Tn8ucbsSmjugzNbXDozj8snjAH6R');
define('TWITTER_OAUTH_ACCESS_TOKEN_SECRET', '5RqcSdMInBY2vPFwfyO6TwWAC4FZCRsEXF5zqAE8ATdF6');


define("TWITTER_UPDATE_JSON",           'https://api.twitter.com/1.1/statuses/update.json');
define("TWITTER_UPDATE_MEDIA_JSON",     'https://api.twitter.com/1.1/statuses/update_with_media.json');
define("CHECK_TWITTER",                 'https://api.twitter.com/1.1/account/verify_credentials.json');
define("TWITTER_AUTH",                  "https://api.twitter.com/oauth/authenticate?oauth_token=");
define('TWITTER_CALLBACK',              "client/index.php/api/twitter/savetoken");

/**** GPlus ****/
/*define('GOOGLE_PLUS_APPNAME', 'clickin');
define('GOOGLE_PLUS_CLIENT_ID', '286268971814.apps.googleusercontent.com');
define('GOOGLE_PLUS_CLIENT_SECRET', 'AbirmaX6xDi5jjnTtKrM7c3N');
define('GOOGLE_PLUS_CLIENT_RETURN_URL', 'http://dev.sourcefuse.com/clickin/chats/dshare');
define('GOOGLE_PLUS_CLIENT_KEY', 'AIzaSyAryNRbqaz-6Ud4uUBkX2y6-8fKHkRqvuE');*/

define('GOOGLE_PLUS_APPNAME', 'clickin');
define('GOOGLE_PLUS_CLIENT_ID', '107668867752.apps.googleusercontent.com');
define('GOOGLE_PLUS_CLIENT_SECRET', 'AbirmaX6xDi5jjnTtKrM7c3N');
define('GOOGLE_PLUS_CLIENT_RETURN_URL', 'http://localhost/clickin/chats/dshare');
define('GOOGLE_PLUS_CLIENT_KEY', 'AIzaSyD-Uhe6MGY6wruXMtFX2zyCOjwU9ne_Klg');

/***** APNS GATEWAY SETTINGS *****/
define('IS_SANDBOX', TRUE);
if(IS_SANDBOX == TRUE)
    define('APPLE_GATEWAY_URL', 'ssl://gateway.sandbox.push.apple.com:2195');
else    
    define('APPLE_GATEWAY_URL', 'ssl://gateway.push.apple.com:2195');
define('APPLE_CERTIFICATE_FILE_PATH', '/files/ck.pem');
define('APPLE_PASS_PHRASE', 'clickin');

// for email setup
define('SUPPORT_SENDER_EMAIL', 'support@clickinapp.com');
define('SUPPORT_SENDER_EMAIL_NAME', 'Clickin');
define('SUPPORT_RECEIVER_EMAIL', 'saurabh.jacob@sourcefuse.com');

// Credentials for Amazon SES Email
define('SMTP_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('SMTP_USER', 'AKIAJAOYV2QA2KU6XHGA');
define('SMTP_PWD', 'AqhvglGP2ElgzKvfKnObWsWy5pzwOV4Mwulw5uCBczxf');
define('SMTP_PORT', 25);

// set up for Aws S3..
define('AMAZON_S3_KEY', 'AKIAJFL3TXCOXO3TBIVQ');
define('AMAZON_S3_SECRET_KEY', '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');
define('BUCKET_NAME', 'clickin-dev');
define('END_POINT', 's3.amazonaws.com');

Configure::write('AVAILABLE_SERVERS', array('http://dev.sourcefuse.com/clickin', 'http://23.21.65.97'));
Configure::write('CURRENT_SERVER', 'http://23.21.65.97');

// setting for all sms gateway..
$config['SMS']['+91'] = 'WEBSMS';
$config['SMS']['+44'] = 'Twilio';
$config['SMS']['+1'] = 'Twilio';

// set up for websms gateway..
Configure::write('WEBSMS_USERNAME', 'gurpreetsf');
Configure::write('WEBSMS_PASSWORD', 'sourcefuse123');
Configure::write('WEBSMS_SENDER_ID', 'WEBSMS');
Configure::write('WEBSMS_GWID', 2);
Configure::write('WEBSMS_TEMPLATE', 'This is test sms '); // later it will be : Your verification code for the Clickin app is 
