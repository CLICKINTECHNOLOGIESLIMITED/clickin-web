<?php 
	App::import('Vendor', 'Twilio.Twilio');
	class TwilioComponent extends Component{
	
	public function startup(Controller $controller) 
    { 
        $this->Twilio = new Twilio();
    } 

	function sms($from, $to, $message)
	{
		$response = $this->Twilio->sms($from, $to, $message);
		
		if($response->IsError) {
			$this->log($response, 'debug');
			return false;
		}
		else
			return true;
	}
}