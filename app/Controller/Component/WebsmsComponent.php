<?php

App::uses('HttpSocket', 'Network/Http');

class WebsmsComponent extends Component {

    function sms($to, $message, $configArr) {
        
        $HttpSocket = new HttpSocket();

        // string data
        $results = $HttpSocket->post(
            'https://login.smsgatewayhub.com/smsapi/pushsms.aspx',
            'user='.$configArr['WEBSMS_USERNAME'].'&pwd='.$configArr['WEBSMS_PASSWORD'].'&to='. $to 
                .'&sid='.$configArr['WEBSMS_SENDER_ID'].'&msg='.$message.'&fl=0&gwid='.$configArr['WEBSMS_GWID']
        );        
        
        if (substr_count('#', $results) > 0) {
            CakeLog::write('info', "\n Received string from WEBSMS Gateway : $results", array('clickin'));
            return false;
        }
        else
            return true;
    }

}