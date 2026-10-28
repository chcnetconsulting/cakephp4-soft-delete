<?php
declare(strict_types=1);

namespace SoftDelete\ORM;

use Cake\ORM\Query\SelectQuery as CakeSelectQuery;

class SelectQuery extends CakeSelectQuery
{
    /**
     * Cake\ORM\Query\SelectQuery::triggerBeforeFind overwritten to add the condition `deleted IS NULL`
     * to every find request in order not to return soft deleted records.
     * If the query carries the option `withDeleted` the condition is not applied:
     * `find('all', withDeleted: true)`, `find('withDeleted')` or `->applyOptions(['withDeleted' => true])`.
     */
    public function triggerBeforeFind(): void
    {
        if (!$this->_beforeFindFired) {
            parent::triggerBeforeFind();

            $options = $this->getOptions();
            $withDeleted = !empty($options['withDeleted']) || in_array('withDeleted', $options, true);
            if (!$withDeleted) {
                $repository = $this->getRepository();
                $this->andWhere($repository->aliasField($repository->getSoftDeleteField()) . ' IS NULL');
            }
        }
    }
}
