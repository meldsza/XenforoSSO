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
        if ($this->filter('sso', 'str')) {
            return $this->login();
        } else {
            $sso = $this->randomNumber(10);
            $session->set('sso_nounce', $sso);
            $session->save();
            $sso = "nounce=$sso&return_sso_url=".$this->request->getHostUrl().'/login';
            $sso = base64_encode($sso);
            $sig = hash_hmac('sha256', $sso, $this->options()->sso_secret);
            $sso_url = $this->options()->sso_url;
            return $this->redirect("$sso_url?sso=$sso&sig=$sig");
        }
    }
    function randomNumber($length)
    {
        $result = '';
    
        for ($i = 0; $i < $length; $i++) {
            $result .= mt_rand(0, 9);
        }
    
        return $result;
    }
    public function login()
    {
        $session = $this->session();
        $sso = urldecode($this->filter('sso', 'str'));
        $sig = hash_hmac('sha256', $sso, $this->options()->sso_secret);
        if ($sig == $this->filter('sig', 'str')) {
            $sso = base64_decode($sso);
            parse_str($sso, $payload);
            
            $nounce = $session->get('sso_nounce');
            if ($nounce != $payload["nounce"]) {
                return $this->error("NOUNCE MISMATCH", 500);
            }
            $loginPlugin = $this->plugin('XF:Login');
            $field = $this->em()->findOne('XF:UserFieldValue', ['field_id' => $this->options()->sso_external_id, 'field_value' => $payload["external_id"]]);
                
            if (!isset($field)) {
                if (!$this->options()->sso_use_email) {
                    $user = $this->em()->findOne('XF:User', ['username' => $payload["username"]]);
                } else {
                    $user = $this->em()->findOne('XF:User', ['email' => $payload["email"]]);
                }
            } else {
                $field_present = true;
                $user = $this->em()->findOne('XF:User', ['user_id' => $field->user_id]);
            }
            if (!isset($user)) {
                $registration = $this->service('XF:User\Registration');
                $registration->setFromInput($payload);
                $registration->setNoPassword();
                $registration->getUser()->setOption('skip_email_confirm', true);
                $user = $registration->save();
            }
            if (!isset($field) && $this->options()->sso_external_id != 'email') {
                $field = $this->em()->findOne('XF:UserFieldValue', ['field_id' => $this->options()->sso_external_id, 'user_id' => $user->user_id]);
                if (isset($field)) {
                    \XF::db()->update('xf_user_field_value',
                    ['field_id' => $this->options()->sso_external_id,'user_id' =>$payload["external_id"] ],
                    'field_value = ?', $user->user_id
                    );
                } else {
                    \XF::db()->insert('xf_user_field_value',
                    ['field_id' => $this->options()->sso_external_id,'user_id' =>$payload["external_id"] ],
                    'field_value = ?', $user->user_id
                    );
                }
            }
            $loginPlugin->completeLogin($user, false);
            
            if (isset($payload["add_groups"])) {
                $group_finder = \XF::finder('XF:UserGroup');
                $payload["add_groups"] = explode(',', $payload["add_groups"]);
                $payload["add_groups"] = array_map(
                function ($g) use ($group_finder) {
                    $group = $group_finder->where('title', $g)->fetchOne();
                    if (isset($group)) {
                        return $group->user_group_id;
                    } else {
                        return null;
                    }
                }, $payload["add_groups"]
                );
                $this->getUserGroupChangeService()->addUserGroupChange($user->user_id, 'sso_group_add', $payload["add_groups"]);
            }
            
            if (isset($payload["moderator"]) && $payload["moderator"] == "true") {
                $generalModerator = $this->em()->find('XF:Moderator', $user->user_id);
                if (!$generalModerator) {
                    $generalModerator = $this->em()->create('XF:Moderator');
                    $generalModerator->user_id = $user->user_id;
                    $generalModerator->is_super_moderator = true;
                    $generalModerator->save();
                }
            } else {
                $generalModerator = $this->em()->find('XF:Moderator', $user->user_id);
                if ($generalModerator) {
                    $generalModerator->delete();
                }
            }
            if (isset($payload["admin"]) && $payload["admin"] == "true") {
                $superAdmin = $this->em()->find('XF:Admin', $user->user_id);
                if (!$superAdmin) {
                    $superAdmin = $this->em()->create('XF:Admin');
                    $superAdmin->user_id = $user->user_id;
                    $superAdmin->is_super_admin = true;
                    $superAdmin->save();
                }
            } else {
                $superAdmin = $this->em()->find('XF:Admin', $user->user_id);
                if ($superAdmin) {
                    $superAdmin->delete();
                }
            }
            return $this->redirect($this->buildLink('forums'));
        } else {
            return $this->error('SIG MISMATCH', 500);
        }
    }
    protected function getUserGroupChangeService()
    {
        return $this->app()->service('XF:User\UserGroupChange');
    }
}
