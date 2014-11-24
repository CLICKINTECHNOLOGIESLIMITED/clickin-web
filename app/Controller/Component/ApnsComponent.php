<?php

class APNSComponent extends Component {

    /**
     * APNS settings
     * @var array (
     * 		'gateway' : APNS gateway (gateway.push.apple.com | gateway.sandbox.push.apple.com),
     * 		'cert' : certificate file name
     * 		'passphrase' : certificate passphrase
     * )
     */

    /**
     * Log file name
     * @var string
     */
    private $tag = 'apns';

    public function sendPushMessage($deviceToken, $config = array(), $extra = array()) {
        
        if (!isset($config['message'])) {
            return false;
        }

        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', WWW_ROOT . $config['cert']);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $config['passphrase']);

        // Open a connection to the APNS server
        $fp = stream_socket_client($config['gateway'], $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);

        if (!$fp) {
            CakeLog::write('info', "\n PN Status in APNS :  Failed to connect: $err $errstr", array('clickin'));
            return "Failed to connect: $err $errstr";
        }
        //echo 'Connected to APNS.', $this->tag ,'<br>';
        
        // Create the payload body
        $body['aps'] = array(
            'alert' => (isset($extra['chat_message']) ? $extra['chat_message'] : ""), // $config['message'] . 
            'sound' => isset($config['sound']) ? $config['sound'] : 'default',
            'badge' => isset($config['badge']) ? $config['badge'] : 0
        );

        if (is_array($extra)) {
            if(isset($extra['chat_message']))
                unset($extra['chat_message']);
            $body['extra'] = $extra;
        }

        // Encode the payload as JSON
        $payload = json_encode($body);

        // Build the binary notification
        $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
        
        // Send it to the server
        $result = fwrite($fp, $msg, strlen($msg));
        
        CakeLog::write('info', "\n PN Status in APNS : " . $result, array('clickin'));
        
        // Close the connection to the server
        fclose($fp);
        
        if (!$result) {
            return FALSE;
        } else {
            return TRUE; 
        }
    }
}