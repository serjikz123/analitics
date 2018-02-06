<?php
namespace App\Controller\Api;

use Cake\Log\Log;
use Cake\ORM\TableRegistry;

class StatisticsController extends ApiController
{
	public function initialize() {
		parent::initialize();
		$this->loadComponent('Validator');
	}

    public function projects()
    {
		$fields = $this->request->query;
		if (!$this->Validator->required($fields, ['from', 'to', 'project_id'])) {
			$this->sendError($this->Validator->getLastError());
		}

		$this->StatTable = TableRegistry::get('ProjectStatisticsDaily');

		$query = $this->getQuery($fields)
			->select(['key_id' => 'project_id']);

		if(!empty($fields['project_id'])) {
			$query->where([
				'project_id' => $fields['project_id'],
			]);
		}

		$this->completeQuery($query);
		$this->sendData($this->buildResult($query));
	}

    public function sites()
    {
		$fields = $this->request->query;
		if (!$this->Validator->required($fields, ['from', 'to'])) {
			$this->sendError($this->Validator->getLastError());
		}

		$this->StatTable = TableRegistry::get('SiteStatisticsDaily');
		$query = $this->getQuery($fields)
			->select(['key_id' => 'site_id']);

		if(!empty($fields['site_ids'])) {
			if(is_string($fields['site_ids'])) {
				$fields['site_ids'] = explode(',', $fields['site_ids']);
			}
			$query->where([
				'site_id IN' => $fields['site_ids'],
			]);
		}

		if(!empty($fields['project_id'])) {
			$query
				->leftJoinWith('Sites', function ($q) use ($fields) {
			        return $q
						->where(['Sites.project_id' => $fields['project_id']]);
			    });
		}

		$this->completeQuery($query);
		$this->sendData($this->buildResult($query));
	}

    public function campaigns()
    {
		$fields = $this->request->query;
		if (!$this->Validator->required($fields, ['from', 'to'])) {
			$this->sendError($this->Validator->getLastError());
		}

		$this->StatTable = TableRegistry::get('CampaignStatisticsDaily');
		$query = $this->getQuery($fields)
			->select(['key_id' => 'campaign_id']);

		if(!empty($fields['campaign_ids'])) {
			if(is_string($fields['campaign_ids'])) {
				$fields['campaign_ids'] = explode(',', $fields['campaign_ids']);
			}
			$query->where([
				'campaign_id IN' => $fields['campaign_ids'],
			]);
		}

		if(!empty($fields['site_id'])) {
			$query
				->leftJoinWith('Campaigns')
				->where(['Campaigns.site_id' => $fields['site_id']]);
		} else if(!empty($fields['project_id'])) {
			$query
				->leftJoinWith('Campaigns.Sites')
				->where(['Sites.project_id' => $fields['project_id']]);
		}

		$this->completeQuery($query);
		$this->sendData($this->buildResult($query));
	}

    public function ad_groups()
    {
		$fields = $this->request->query;
		if (!$this->Validator->required($fields, ['from', 'to'])) {
			$this->sendError($this->Validator->getLastError());
		}

		$this->StatTable = TableRegistry::get('AdGroupStatisticsDaily');
		$query = $this->getQuery($fields)
			->select(['key_id' => 'ad_group_id']);

		if(!empty($fields['ad_group_ids'])) {
			if(is_string($fields['ad_group_ids'])) {
				$fields['ad_group_ids'] = explode(',', $fields['ad_group_ids']);
			}
			$query->where([
				'ad_group_id IN' => $fields['ad_group_ids'],
			]);
		}

		if(!empty($fields['campaign_id'])) {
			$query
				->leftJoinWith('AdGroups')
				->where(['AdGroups.campaign_id' => $fields['campaign_id']]);
		}

		$this->completeQuery($query);
		$this->sendData($this->buildResult($query));
	}

    public function keywords()
    {
		$fields = $this->request->query;
		if (!$this->Validator->required($fields, ['from', 'to'])) {
			$this->sendError($this->Validator->getLastError());
		}

		$this->StatTable = TableRegistry::get('KeywordStatisticsDaily');
		$query = $this->getQuery($fields)
			->select(['key_id' => 'keyword_id']);

		if(!empty($fields['keyword_ids'])) {
			if(is_string($fields['keyword_ids'])) {
				$fields['keyword_ids'] = explode(',', $fields['keyword_ids']);
			}
			$query->where([
				'keyword_id IN' => $fields['keyword_ids'],
			]);
		}

		if(!empty($fields['campaign_id']) || !empty($fields['ad_group_id'])) {
			$query->leftJoinWith('Keywords');

			if(!empty($fields['campaign_id'])) {
				$query->where(['Keywords.campaign_id' => $fields['campaign_id']]);
			}
			if(!empty($fields['ad_group_id'])) {
				$query->where(['Keywords.ad_group_id' => $fields['ad_group_id']]);
			}
		}

		$this->completeQuery($query);
		$this->sendData($this->buildResult($query));
	}


	private function getQuery($fields)
	{
		$query = $this->StatTable->find('all');

		$query->select([
			'cost' => $query->func()->sum('cost'),
			'views' => $query->func()->sum('views'),
			'clicks' => $query->func()->sum('clicks'),
			'ctr' => $query->func()->avg('ctr'),
			'calls' => $query->func()->sum('calls'),
			'emails' => $query->func()->sum('emails'),
			'leads' => $query->func()->sum('leads'),
			'lead_perc' => $query->func()->avg('lead_perc'),
			'lead_cost' => $query->func()->avg('lead_cost'),
			'orders' => $query->func()->sum('orders'),
			'order_perc' => $query->func()->avg('order_perc'),
			'order_cost' => $query->func()->avg('order_cost'),
		]);

		$query->where([
			'date >=' => $fields['from'],
			'date <=' => $fields['to'],
		]);

		$query->group('key_id');

		return $query;
	}

	private function getItem($item)
	{
		return [
			'key_id' => $item->key_id,
			'cost' => $item->cost,
			'views' => $item->views,
			'clicks' => $item->clicks,
			'ctr' => $item->ctr,
			'calls' => $item->calls,
			'emails' => $item->emails,
			'leads' => $item->leads,
			'lead_perc' => $item->lead_perc,
			'lead_cost' => $item->lead_cost,
			'orders' => $item->orders,
			'order_perc' => $item->order_perc,
			'order_cost' => $item->order_cost,
		];
	}

	private function buildResult($query)
	{
		$result = [];
		foreach ($query as $item) {
		    $result[] = $this->getItem($item);
		}
		return $result;
	}

	private function completeQuery(&$query)
	{
		$this->paginateQuery($query);
		$this->orderQuery($query);
		$this->setQueryCount($query);
	}
}
