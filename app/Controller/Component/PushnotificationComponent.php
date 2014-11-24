<?php

App::uses('Component', 'Controller');

class PushnotificationComponent extends Component {

    // load component..
    public $components = array('Apns', 'C2DM');

    /**
     * sendMessage method
     * 
     * @param string $type
     * @param string $deviceToken
     * @param string $message
     * @param array $payLoadData
     * @return boolean $response
     */
    public function sendMessage($type, $deviceToken, $message, $payLoadData = array()) {

        $badgeCount = 1;
        $params = array(
            'fields' => array('_id', 'badge_count'),
            'conditions' => array('device_token' => $deviceToken)
        );
        // Find records matching device token.
        $User = ClassRegistry::init('User');
        $data = $User->find('first', $params);
        if(count($data)>0) {
            $badgeCount = isset($data["User"]["badge_count"]) ? $data["User"]["badge_count"]+1 : 1;
            $data["User"]["badge_count"] = $badgeCount;
            $User->save($data);
        }
        
        $result = FALSE;
        switch ($type) {
            case DEVICE_TYPE_IOS:

                // configure settings..
                $params = array(
                    'gateway' => APPLE_GATEWAY_URL,
                    'cert' => APPLE_CERTIFICATE_FILE_PATH,
                    'passphrase' => APPLE_PASS_PHRASE,
                    'message' => $message,
                    'badge' => $badgeCount                    
                );
                CakeLog::write('info', "\n Badge Status in APNS : $badgeCount", array('clickin'));
                // send push notification via apns
                $result = $this->Apns->sendPushMessage($deviceToken, $params, $payLoadData);

                break;
            case DEVICE_TYPE_ANDROID:

                $params['registrationIds'] = array($deviceToken);
                $payLoadData = array($payLoadData, array('message' => $message));
                // send push notification via gcm
                $result = $this->C2DM->sendMessage(GOOGLE_PLUS_CLIENT_KEY, $params['registrationIds'], $payLoadData);

                break;
            default:
                $result = FALSE;
                break;
        }
        $response = ($result == TRUE) ? 'Message successfully delivered.' : 'Message not delivered.';

        return $response;
    }

}