<?php namespace Vinelab\NeoEloquent\Eloquent\Relations\Hybrid;

use Illuminate\Database\Eloquent\Model;

trait HybridRelations
{
    public function hasOneHybrid($related, $foreignKey = null, $localKey = null)
    {
        //TO make relation from non-relational to relational
        if (!is_subclass_of($related, 'Vinelab\NeoEloquent\Eloquent\Model')) {
            return Model::hasOne($related, $foreignKey, $localKey);
        }

        //TO make relation from relational to non-relational
        $relation = $this->guessBelongsToRelation();
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();
        $instance = $this->newRelatedInstance($related);

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey, $relation);
    }

    public function belongsToHybrid($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        //TO make relation from non-relational to relational
        if (!is_subclass_of($related, 'Vinelab\NeoEloquent\Eloquent\Model')) {
            return Model::belongsTo($related, $foreignKey, $ownerKey, $relation);
        }

        //TO make relation from relational to non-relational

        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $ownerKey = $ownerKey ?: $this->getKeyName();
        $instance = $this->newRelatedInstance($related);

        return new BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey, $relation);
    }
}