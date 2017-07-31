<?php
namespace App\Controller\Api;

class SitesController extends ApiController
{
	public function initialize()
	{
		parent::initialize();
		$this->loadComponent('Validator');
	}

	public function index()
    {
		$result = [];

		$query = $this->Sites->find('all', [
			'contain' => false
		]);

		foreach ($query as $row) {
			$result[] = [
				'id' => $row->id,
				'project_id' => $row->project_id,
				'domain' => $row->domain,
			];
		}

		$this->sendData($result);
    }

    public function view($id = null)
    {
        $site = $this->Sites->get($id, [
            'contain' => []
        ]);

		$result = [
			'id' => $site->id,
			'project_id' => $site->project_id,
			'caption' => $site->domain,
		];

		$this->sendData($result);
    }

	public function add()
    {
        if ($this->request->is('post')) {
            $data = $this->request->getData();

            if ($this->Validator->required($data, ['project_id', 'domain'])) {
                $site = $this->Sites->newEntity();
                $site = $this->Sites->patchEntity($site, $data);

                if ($this->Sites->save($site)) {
                    $this->sendData([
                        'id' => $site->id
                    ]);
                }

                $this->sendError(__('Can`t add site'));
            }

            $this->sendError($this->Validator->getLastError());
        }
    }

    public function delete($id = null)
    {
        if ($this->request->is('delete') && $id) {
            $site = $this->Sites->get($id);
            if ($this->Sites->delete($site)) {
                $this->sendData([]);
            } else {
                $this->sendError(__('Can`t delete site'));
            }
        }
    }

	public function edit($id = null)
    {
        if ($this->request->is('put')) {

			$data = $this->request->getData();
            $site = $this->Sites->patchEntity($this->Sites->get($id, ['contain' => []]), $data);

            if ($this->Sites->save($site)) {
				$this->sendData([]);
            }
            $this->sendError(__('Can`t save site details'));
        }
    }
}
