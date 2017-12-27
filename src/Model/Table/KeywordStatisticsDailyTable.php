<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * KeywordStatisticsDaily Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Keywords
 *
 * @method \App\Model\Entity\KeywordStatisticsDaily get($primaryKey, $options = [])
 * @method \App\Model\Entity\KeywordStatisticsDaily newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\KeywordStatisticsDaily[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\KeywordStatisticsDaily|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\KeywordStatisticsDaily patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\KeywordStatisticsDaily[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\KeywordStatisticsDaily findOrCreate($search, callable $callback = null, $options = [])
 */
class KeywordStatisticsDailyTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('keyword_statistics_daily');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Keywords', [
            'foreignKey' => 'keyword_id',
            'joinType' => 'INNER'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->numeric('cost')
            ->requirePresence('cost', 'create')
            ->notEmpty('cost');

        $validator
            ->integer('views')
            ->requirePresence('views', 'create')
            ->notEmpty('views');

        $validator
            ->integer('clicks')
            ->requirePresence('clicks', 'create')
            ->notEmpty('clicks');

        $validator
            ->date('date')
            ->requirePresence('date', 'create')
            ->notEmpty('date');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['keyword_id'], 'Keywords'));

        return $rules;
    }

	public function saveStatistics($record)
	{
		if($record->cost || $record->views || $record->calls || $record->emails || $record->clicks) {
			$this->save($record);
		}
	}
}
