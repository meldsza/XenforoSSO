<?php

namespace Meldsza\XenforoSSO;


use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
	public function install(array $stepParams = [])
	{
		$db = \XF::db();
		$db->query("INSERT IGNORE INTO xf_connected_account_provider (provider_id, provider_class, display_order, options)
		VALUES ('Meldsza', 'Meldsza\\\\XenforoSSO\\\\ConnectedAccount\\\\Provider\\\\Meldsza', 5, '')");

	}

	public function upgrade(array $stepParams = [])
	{
		// TODO: Implement upgrade() method.
	}

	public function uninstall(array $stepParams = [])
	{
		$db->query("DELETE FROM xf_connected_account_provider where provider_id = 'Meldsza'");
	}
}
