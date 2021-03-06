<?php 

/**
 * TurboSMS.ua
 *
 * SOAP gate client
 * Written by Sunra@yandex.ru
 * origginal code: http://turbosms.ua/soap.html
 * -----------------------------------------------------------------
 * Required: SOAP, iconv
 * All communication with gate goes in UTF-8
 *
 * First must register on turbosms.ua and get login, password to gate and sender string
 *
 * @version Version 0.4
 */

namespace Turbosms\Soap;

/**
 * Class Client
 *
 * @package Turbosms\Soap
 */
class Client
{
    private $auth_arr = array(
        'login' => '',
        'password' => ''
    );
    
    const soap_gate = 'http://turbosms.in.ua/api/wsdl.html';
    const gate_encoding = 'utf-8';
    
    private $client_encoding = 'windows-1251';
    private $sender = 'SoapClient';
    
    private $client;
    public $connected = false;

    public $result_text;
    public $sms_id;

    /**
     * Client constructor.
     *
     * @param $login
     * @param $pass
     * @param string $sender
     * @param string $client_encoding
     *
     * @throws \Exception
     */
    public function __construct($login, $pass, $sender='', $client_encoding='')
    {
        if (!function_exists('iconv')) {
            throw new \Exception('ICONV not installed (http://www.php.net/manual/en/iconv.requirements.php)');
        }

        if (!class_exists('SoapClient')) {
            throw new \Exception('SOAP not installed (http://www.php.net/manual/en/soap.installation.php)');
        }
        
        $this->auth_arr['login'] = $login;
        $this->auth_arr['password'] = $pass;
        
        if ($sender) {
            $this->sender = $sender;
        }
        if ($client_encoding) {
            $this->client_encoding = $client_encoding;
        }
        
        return $this->connected = $this->connect();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    private function connect()
    {
        if ($this->connected) {
            return true;
        }
        
        $this->client = new \SoapClient(self::soap_gate);
                
        if (!$this->client) {
            throw new \Exception('Cannot create new Soap client to '.self::soap_gate);
        }
        
        $result = $this->client->Auth($this->auth_arr);
        
        $this->result_text = $this->conv_to_client($result->AuthResult);
        
        $this->connected = $result->AuthResult == 'Вы успешно авторизировались';
        
        if (!$this->connected) {
            throw new \Exception('Failed to Auth on Gate: '.$this->result_text);
        }
        
        return $this->connected;
    }

    /**
     * @param $text
     *
     * @return string
     */
    private function conv_to_client($text)
    {
        return iconv(self::gate_encoding, $this->client_encoding, $text);
    }

    /**
     * @param $text
     *
     * @return string
     */
    private function conv_to_gate($text)
    {
        return iconv($this->client_encoding, self::gate_encoding, $text);
    }

    /**
     * @return mixed
     */
    public function bill()
    {
        $result = $this->client->GetCreditBalance();
        return $result->GetCreditBalanceResult;
    }

    /**
     * Send 1 sms
     *
     * @param $phone full qualified phone number like +380675384547
     * @param $text sms text in UTF-8
     *
     * @link http://turbosms.ua/soap.html
     * @return bool
     */
    public function send($phone, $text)
    {
        $sms = array(
            'sender' => $this->sender,
            'destination' => $phone,
            'text' => $this->conv_to_gate($text)/*,
            'wappush' => 'http://realt5000.com.ua'*/
        );
    
        $SendSMSResult = $this->client->SendSMS($sms);
                
        $this->result_text = $this->conv_to_client($SendSMSResult->SendSMSResult->ResultArray[0]);
        
        if ($SendSMSResult->SendSMSResult->ResultArray[0] == 'Сообщения успешно отправлены') {
            $this->sms_id = $SendSMSResult->SendSMSResult->ResultArray[1];
            
            return true;
        } else {
            $this->sms_id = null;
            throw new \Exception('Failed to send sms throw soap gate: '. $this->result_text. ' (details: http://turbosms.ua/soap.html)');
        }
    }
}
