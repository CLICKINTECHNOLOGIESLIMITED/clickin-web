<?php

App::uses('Component', 'Controller');

class SmsComponent extends Component {

    // load component..
    public $components = array('Twilio.Twilio', 'Websms');

    /**
     * sendSms method
     * 
     * @param string $phone_no
     * @param string $message
     */
    public function sendSms($phone_no, $message) {

        // extract country code from phone no..
        $smsGatewayType = '';
        $countryCodeArray = Configure::read('SMS');
        if (is_array($countryCodeArray)) {
            foreach ($countryCodeArray as $key => $value) {
                if (substr_count($phone_no, $key) > 0) {
                    $smsGatewayType = $value;
                    break;
                }
            }
        }
        else
            $smsGatewayType = '';
        
        $result = FALSE;
        switch ($smsGatewayType) {
            case 'Twilio':
                $from = Configure::read('Twilio.number');
                // send sms via twilio...
                $result = $this->Twilio->sms($from, $phone_no, $message);
                CakeLog::write('info', "\n Send Status of Twilio Gateway : $result", array('clickin'));
                break;
            case 'WEBSMS':
                $configArr = array(
                    'WEBSMS_USERNAME' => Configure::read('WEBSMS_USERNAME'),
                    'WEBSMS_PASSWORD' => Configure::read('WEBSMS_PASSWORD'),
                    'WEBSMS_SENDER_ID' => Configure::read('WEBSMS_SENDER_ID'),
                    'WEBSMS_GWID' => Configure::read('WEBSMS_GWID'),
                );
                // send sms via Websms...
                $result = $this->Websms->sms($phone_no, $message, $configArr);
                CakeLog::write('info', "\n Send Status of WEBSMS Gateway : $result", array('clickin'));
                break;
            default:
                $from = Configure::read('Twilio.number');
                $result = $this->Twilio->sms($from, $phone_no, $message);
                break;
        }
        return $result;
    }

}