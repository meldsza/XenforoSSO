<?php

namespace Meldsza\XenforoSSO;

use XF\ControllerPlugin\Error;

class ErrorExtension extends Error
{
    public function actionRegistrationRequired()
    {
        return $this->error(\XF::phrase('login_required'), 403);
    }
}
