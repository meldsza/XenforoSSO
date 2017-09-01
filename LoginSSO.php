<?php

namespace Meldsza\XenforoSSO;

use XF\ConnectedAccount\Provider\AbstractProvider;
use XF\ConnectedAccount\ProviderData\AbstractProviderData;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\Login;

class LoginSSO extends Login
{
	public function actionIndex()
	{
        $session = $this->session();
        if($this->filter('sso','str'))
        {
            return $this->login();
        }
        else
        {
            $sso = $this->randomNumber(10);
            $session->set('sso_nounce',$sso);
            $session->save();
            $sso = "nounce=$sso&return_sso_url=".$this->request->getHostUrl().'/login';
            $sso = base64_encode($sso);
            $sig = hash_hmac('sha256', $sso, $this->options()->sso_secret);  
            $sso_url = $this->options()->sso_url;          
            return $this->redirect("$sso_url?sso=$sso&sig=$sig");
        }
    }
    function randomNumber($length) {
        $result = '';
    
        for($i = 0; $i < $length; $i++) {
            $result .= mt_rand(0, 9);
        }
    
        return $result;
    }
    public function login()
    {
        $session = $this->session();
        $sso = urldecode($this->filter('sso','str'));
        $sig = hash_hmac('sha256', $sso, $this->options()->sso_secret);
        if($sig == $this->filter('sig','str'))
        {
            $sso = base64_decode($sso);
            parse_str($sso, $payload);
            
            $nounce = $session->get('sso_nounce');
            if($nounce != $payload["nounce"] )
                return $this->error("NOUNCE MISMATCH", 500);
            $loginPlugin = $this->plugin('XF:Login');
            $user = $this->em()->findOne('XF:User', ['email' => $payload["email"]]);
            if (!isset($user))
            {
                $registration = $this->service('XF:User\Registration');
                $registration->setFromInput($payload);
                $registration->setNoPassword();
                $registration->getUser()->setOption('skip_email_confirm', true);
                $user = $registration->save();
            }
            $loginPlugin->completeLogin($user, false);
            return $this->redirect($this->buildLink('forums'));
        }
        else
        {
            return $this->error('SIG MISMATCH', 500);
        }
    }
}