<?php
namespace App\Shell;

use Cake\ORM\TableRegistry;
use Cake\Log\Log;

class AggregateStatisticsShell extends Base
{
    public $statItems = ['cost', 'views', 'clicks', 'calls', 'emails', 'leads', 'orders'];

    public function initialize()
    {
        parent::initialize();

        $this->Projects = TableRegistry::get('Sources');
        $this->Sites = TableRegistry::get('Sites');
        $this->Sources = TableRegistry::get('Sources');
        $this->Campaigns = TableRegistry::get('Campaigns');
        $this->Keywords = TableRegistry::get('Keywords');
        $this->AdGroups = TableRegistry::get('AdGroups');

        $this->AGSD = TableRegistry::get('AdGroupStatisticsDaily');
        $this->SSD = TableRegistry::get('SiteStatisticsDaily');
        $this->SOSD = TableRegistry::get('SourceStatisticsDaily');
        $this->CSD = TableRegistry::get('CampaignStatisticsDaily');
        $this->PSD = TableRegistry::get('ProjectStatisticsDaily');
        $this->KSD = TableRegistry::get('KeywordStatisticsDaily');

        $this->SiteOrders = TableRegistry::get('SiteOrders');
        $this->SiteCalls = TableRegistry::get('SiteCalls');
        $this->SiteEmails = TableRegistry::get('SiteEmails');
        $this->SiteCosts = TableRegistry::get('SiteCosts');
    }

    public function main()
    {
        $date = date('Y-m-d', strtotime('-1 day'));

        Log::write('debug', [$date], ['shell', 'AggregateStatisticsShell', 'main']);

        $this->leads(date('Y-m-d'));
        $this->leads($date);

        $this->sources($date);
        $this->sites($date);
        $this->projects($date);
    }

    public function date($date)
    {
        $this->leads($date);

        $this->sources($date);
        $this->sites($date);
        $this->projects($date);
    }

    public function range($from, $to)
    {
        $date = $from;
        do {
			$this->message("range: starting $date");
            $this->date($date);
            $date = date('Y-m-d', strtotime($date . ' +1 day'));
        } while ($date <= $to);
    }

    public function projects($date = null)
    {
        $projects = $this->Projects->find('all')->all();

        foreach ($projects as $project) {
			$this->message("projects: starting $date for " . $project->id);
            $query = $this->SSD->find('all');

            $sIds = array_map(
                function ($site) {
                    return $site->id;
                },
                $this->Sites->find('all')->select('id')->where(['project_id' => $project->id])->all()->toArray()
            );

            if (empty($sIds)) {
                continue;
            }

            $itemParams = [];
            foreach ($this->statItems as $item) {
                $itemParams[$item] = $query->func()->sum($item);
            }

            $item = $query
                ->select($itemParams)
                ->group('date')
                ->where([
                    'site_id IN' => $sIds,
                    'date' => $date,
                ])
                ->first();

            if (empty($item)) {
                continue;
            }

            $record = $this->PSD->findOrCreateRecord([
                'date' => $date,
                'project_id' => $project->id,
            ]);

            foreach ($this->statItems as $name) {
                $record->$name = $item->$name;
            }

			if ($this->PSD->isRecordFilled($record)) {
            	$this->PSD->save($record);
			}
        }
    }

    public function sites($date = null)
    {
        $sites = $this->Sites->find('all')->all();

        foreach ($sites as $site) {
			$this->message("sites: starting $date for " . $site->id);

            // Orders
            $ordersQuery = $this->SiteOrders->find('all');
            $ordersItem = $ordersQuery->select([
                'count' => $ordersQuery->func()->sum('count')
            ])->where([
                'site_id' => $site->id,
                'time >=' => $date . ' 00:00:00',
                'time <=' => $date . ' 23:59:59',
            ])->first();

            $record = $this->SSD->findOrCreateRecord([
                'date' => $date,
                'site_id' => $site->id,
            ]);

            $sources = $this->Sources->find('all')->select('id')->where(['site_id' => $site->id])->all()->toArray();
            $sIds = array_map(function ($source) {
                return $source->id;
            }, $sources);

            if (!empty($sIds)) {
                $item = $this->SOSD->findSum([
                    'source_id IN' => $sIds,
                    'date' => $date,
                ]);

                if (!empty($item)) {
                    $record->cost = $item->cost;
                    $record->views = $item->views;
                    $record->clicks = $item->clicks;
                }
            }

            $record->orders = $ordersItem->count ? $ordersItem->count : 0;

			if ($this->SSD->isRecordFilled($record)) {
            	$this->SSD->save($record);
			}
        }
    }

    public function sources($date = null)
    {
        $sources = $this->Sources->find('all')->all();

        foreach ($sources as $source) {
			$this->message("sources: starting $date for " . $source->id);

            $campaigns = $this->Campaigns->find('all')->select('id')->where(['source_id' => $source->id])->all()->toArray();
            $cIds = array_map(function ($campaign) {
                return $campaign->id;
            }, $campaigns);

            if (empty($cIds)) {
                continue;
            }

            $item = $this->CSD->findSum([
                'campaign_id IN' => $cIds,
                'date' => $date,
            ]);

            if (empty($item)) {
                continue;
            }

            $record = $this->SOSD->findOrCreateRecord([
                'date' => $date,
                'source_id' => $source->id,
            ]);

            $siteCosts = $this->SiteCosts->find();
            $sc = $siteCosts
                ->where(['SiteCosts.source_id' => $source->id, 'SiteCosts.time LIKE' => $date . '%'])
                ->select(['costs' => $siteCosts->func()->sum('SiteCosts.cost')])
                ->first();

            $record->cost = $item->cost + $sc->costs;
            $record->views = $item->views;
            $record->clicks = $item->clicks;

			if ($this->SOSD->isRecordFilled($record)) {
	            $this->SOSD->save($record);
			}
        }
    }

    public function campaigns($date = null)
    {
    }

    public function adgroups($date = null)
    {
    }

    public function keywords($date = null)
    {
    }

    public function leads($date = null)
    {
        $from = $date . ' 00:00:00';
        $to = $date . ' 23:59:59';

        $src = [
            [
				'target' => 'keywords',
                'table' => $this->Keywords->find()->contain(['Campaigns'])->where(['Campaigns.deleted !=' => 1]),
                'statistics' => $this->KSD,
                'key' => 'keyword_id',
            ], [
				'target' => 'ad_groups',
                'table' => $this->AdGroups->find()->contain(['Campaigns'])->where(['Campaigns.deleted !=' => 1]),
                'statistics' => $this->AGSD,
                'key' => 'ad_group_id',
            ], [
				'target' => 'campaigns',
                'table' => $this->Campaigns->find()->where(['deleted !=' => 1]),
                'statistics' => $this->CSD,
                'key' => 'campaign_id',
            ], [
				'target' => 'sources',
                'table' => $this->Sources->find()->where(['deleted !=' => 1]),
                'statistics' => $this->SOSD,
                'key' => 'source_id',
            ], [
				'target' => 'sites',
                'table' => $this->Sites->find()->where(['deleted !=' => 1]),
                'statistics' => $this->SSD,
                'key' => 'site_id',
            ],
        ];

        foreach ($src as $table) {
			$this->message("leads: starting $date for " . $table['target']);

            $items = $table['table']->all();

            foreach ($items as $item) {
                $recordWhere = ['date' => $date];
                $recordWhere[$table['key']] = $item->id;
                $record = $table['statistics']->find('all')->where($recordWhere)->first();
                if (empty($record)) {
                    $record = $table['statistics']->newEntity();
                    $record->{$table['key']} = $item->id;
                    $record->date = $date;
                }
                $conditions = [
                    'time >=' => $from,
                    'time <=' => $to,
                ];
                $conditions[$table['key']] = $item->id;
                $record->calls = $this->SiteCalls->findCountBy($conditions);
                $record->emails = $this->SiteEmails->findCountBy($conditions);
                $record->leads = $record->emails + $record->calls;

				if ($table['statistics']->isRecordFilled($record)) {
                	$table['statistics']->save($record);
				}
            }
        }
    }
}


/*
public function today()
{
    $this->forDate(date('Y-m-d'));
}

public function yesterday()
{
    $this->forDate(date('Y-m-d', strtotime('-1 day')));
}

public function month()
{
    $startDate = date('Y-m-d', strtotime('-1 month'));
    $today = date('Y-m-d');
    while ($startDate < $today) {
        $this->forDate($startDate);
        $startDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
    }
}

public function doublemonth()
{
    $startDate = date('Y-m-d', strtotime('-2 month'));
    $today = date('Y-m-d');
    while ($startDate < $today) {
        $this->forDate($startDate);
        $startDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
    }
}

public function quarterly()
{
    $startDate = date('Y-m-d', strtotime('-3 month'));
    $today = date('Y-m-d');
    while ($startDate < $today) {
        $this->forDate($startDate);
        $startDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
    }
}

public function semiannually()
{
    $startDate = date('Y-m-d', strtotime('-6 month'));
    $today = date('Y-m-d');
    while ($startDate < $today) {
        $this->forDate($startDate);
        $startDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
    }
}
 */
