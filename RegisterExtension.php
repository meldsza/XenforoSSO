<?php

namespace Meldsza\XenforoSSO;

use XF\ConnectedAccount\Provider\AbstractProvider;
use XF\ConnectedAccount\ProviderData\AbstractProviderData;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\Register;

class RegisterExtension extends Register
{
	public function actionIndex()
	{
		return $this->redirect($this->options()->sso_registration);
	}
}