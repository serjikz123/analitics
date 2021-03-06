<?php
namespace App\Controller\Api\Projects;

class CampaignsController extends \App\Controller\Api\ApiController
{

    public function index()
    {
		$result = [];

		$query = $this->Campaigns->find()
			->where([
				'Sites.project_id' => $this->request->params['project_id']
			])
			->select(['id' => 'Campaigns.id', 'site_id' => 'Campaigns.site_id', 'caption' => 'Campaigns.caption'])
			->contain(['Sites',]);

		$this->prepareApiQuery($query);
		$query = $query->all();

		foreach ($query as $row) {
			$result[] = [
				'id' => $row->id,
				'site_id' => $row->site_id,
				'caption' => $row->caption,
			];
		}

		$this->sendData($result);
    }

}
