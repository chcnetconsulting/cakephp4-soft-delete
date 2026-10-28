<?php
declare(strict_types=1);

namespace SoftDelete\Model\Table;

use ArrayObject;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query\SelectQuery as CakeSelectQuery;
use Cake\ORM\RulesChecker;
use Closure;
use DateTimeInterface;
use SoftDelete\Error\MissingColumnException;
use SoftDelete\ORM\SelectQuery;

trait SoftDeleteTrait
{
    /**
     * Get the configured deletion field
     *
     * @return string
     * @throws \SoftDelete\Error\MissingColumnException
     */
    public function getSoftDeleteField(): string
    {
        if (isset($this->softDeleteField)) {
            $field = $this->softDeleteField;
        } else {
            $field = 'deleted';
        }

        if ($this->getSchema()->getColumn($field) === null) {
            throw new MissingColumnException(sprintf(
                'Configured field `%s` is missing from the table `%s`.',
                $field,
                $this->getAlias()
            ));
        }

        return $field;
    }

    /**
     * Every select query of this table filters soft deleted records (see SoftDelete\ORM\SelectQuery).
     * CakePHP 5 builds `find()` and `get()` on `selectQuery()`.
     */
    public function selectQuery(): CakeSelectQuery
    {
        return new SelectQuery($this);
    }

    /**
     * Finder that includes soft deleted records: `$table->find('withDeleted')`.
     */
    public function findWithDeleted(CakeSelectQuery $query): CakeSelectQuery
    {
        return $query->applyOptions(['withDeleted' => true]);
    }

    /**
     * Perform the delete operation.
     *
     * Will soft delete the entity provided. Will remove rows from any
     * dependent associations, and clear out join tables for BelongsToMany associations.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to soft delete.
     * @param \ArrayObject $options The options for the delete.
     * @throws \InvalidArgumentException if there are no primary key values of the
     * passed entity
     * @return bool success
     */
    protected function _processDelete(EntityInterface $entity, ArrayObject $options): bool
    {
        if ($entity->isNew()) {
            return false;
        }

        $primaryKey = (array)$this->getPrimaryKey();
        if (!$entity->has($primaryKey)) {
            $msg = 'Deleting requires all primary key values.';
            throw new \InvalidArgumentException($msg);
        }

        if ($options['checkRules'] && !$this->checkRules($entity, RulesChecker::DELETE, $options)) {
            return false;
        }
        $event = $this->dispatchEvent(
            'Model.beforeDelete',
            [
                'entity' => $entity,
                'options' => $options,
            ]
        );

        if ($event->isStopped()) {
            return (bool)$event->getResult();
        }

        $this->_associations->cascadeDelete(
            $entity,
            ['_primary' => false] + $options->getArrayCopy()
        );

        $conditions = (array)$entity->extract($primaryKey);
        $statement = $this->updateQuery()
            ->set([$this->getSoftDeleteField() => date('Y-m-d H:i:s')])
            ->where($conditions)
            ->execute();

        $success = $statement->rowCount() > 0;
        if (!$success) {
            return $success;
        }

        $this->dispatchEvent(
            'Model.afterDelete',
            [
                'entity' => $entity,
                'options' => $options,
            ]
        );

        return $success;
    }

    /**
     * Soft deletes all records matching `$conditions`.
     *
     * @return int number of affected rows.
     */
    public function deleteAll(QueryExpression|Closure|array|string|null $conditions): int
    {
        $query = $this->updateQuery()
            ->set([$this->getSoftDeleteField() => date('Y-m-d H:i:s')])
            ->where($conditions);
        $statement = $query->execute();
        $statement->closeCursor();

        return $statement->rowCount();
    }

    /**
     * Hard deletes the given $entity.
     *
     * @return bool true in case of success, false otherwise.
     */
    public function hardDelete(EntityInterface $entity): bool
    {
        if (!$this->delete($entity)) {
            return false;
        }
        $primaryKey = (array)$this->getPrimaryKey();
        $conditions = (array)$entity->extract($primaryKey);
        $statement = $this->deleteQuery()
            ->where($conditions)
            ->execute();

        return $statement->rowCount() > 0;
    }

    /**
     * Hard deletes all records that were soft deleted before a given date.
     *
     * @param \DateTimeInterface $until Date until which soft deleted records must be hard deleted.
     * @return int number of affected rows.
     */
    public function hardDeleteAll(DateTimeInterface $until): int
    {
        $query = $this->deleteQuery()
            ->where(
                [
                    $this->getSoftDeleteField() . ' IS NOT NULL',
                    $this->getSoftDeleteField() . ' <=' => $until->format('Y-m-d H:i:s'),
                ]
            );
        $statement = $query->execute();
        $statement->closeCursor();

        return $statement->rowCount();
    }

    /**
     * Restore a soft deleted entity into an active state.
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity to be restored.
     * @return \Cake\Datasource\EntityInterface|false the saved entity, false otherwise.
     */
    public function restore(EntityInterface $entity): EntityInterface|false
    {
        $softDeleteField = $this->getSoftDeleteField();
        $entity->set($softDeleteField, null);

        return $this->save($entity);
    }
}
