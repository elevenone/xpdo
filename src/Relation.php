<?php
namespace aphp\XPDO;

abstract class RelationH {
	abstract public function toManyAdd($name, Model $relationModel);
	abstract public function toManyAddAll($name, $relationModels); // name, [ Model ]
	abstract public function toManyRemove($name, Model $relationModel);
	abstract public function toManyRemoveAll($name);
	abstract public function reset();

/*
Magic read
	$relationObjects = $model->relation()->%relationNameToMany%;
	$relationObject = $model->relation()->%relationNameToOne%;
Magic write
	$model->relation()->%relationNameToOne% = $relationObject;
toMany write
	$model->relation()->toManyAdd('%relationNameToMany%', $relationObject);
	$model->relation()->toManyRemove('%relationNameToMany%', $relationObject);
*/
}

class Relation extends RelationH
{
	public $__model = null;
	protected $__functions_get = [];
	protected $__functions_add = [];
	protected $__functions_remove = [];
	protected $__functions_set = [];
	protected $__values = [];
	protected $__modelClass;
	protected $__modelNamespace = null;

	public function __construct($modelClass) 
	{
		$this->__modelClass = $modelClass;
	}

	protected function parseClassName($className) {
		// load namespace
		if ($this->__modelNamespace === null) {
			$r = new \ReflectionClass($this->__modelClass);
			$this->__modelNamespace = $r->getNamespaceName();
		}
		//
		if (strpos($className, '\\') === false) {
			if (
				ModelConfig::$modelClass_relation_namespace == 'auto' &&
				!empty($this->__modelNamespace)
				) {
				return $this->__modelNamespace . '\\' . $className;
			}
			if (
				ModelConfig::$modelClass_relation_namespace != 'auto' &&
				!empty(ModelConfig::$modelClass_relation_namespace)
				) {
				return ModelConfig::$modelClass_relation_namespace . '\\' . $className;
			}
		}
		return $className;
	}

	protected function parseRelationIfNeeded($name)
	{
		if (isset($this->__functions_get[$name])) return;
		$modelClass = $this->__modelClass;
		$relations = $modelClass::relations();
		if (!isset($relations[$name])) return;
		$relation = $relations[$name];

		if (is_array($relation)) {
			// manyToMany
			if (
				count($relation) == 2 &&
				preg_match('~^this->(\w+)\s*\*-\*\*\s*([a-zA-Z0-9_\\\\]+)->(\w+)$~', $relation[0], $match1) &&
				preg_match('~^[a-zA-Z0-9_\\\\]+->(\w+)\s*\*\*\s*([a-zA-Z0-9_\\\\]+)->(\w+)$~', $relation[1], $match2)
			) {
				$s = new \StdClass;
				$s->modelField = $match1[1];

				$relationClass1 = $this->parseClassName($match1[2]);
				$relationClass2 = $this->parseClassName($match2[2]);
				
				$s->relationClass1Field1 = $match1[3];
				$s->relationClass1Field2 = $match2[1];
				
				$s->relationClass2Field = $match2[3];
				$s->db = $modelClass::database();
				$s->modelTable = $modelClass::tableName();
				$s->table1 = $relationClass1::tableName();
				$s->table2 = $relationClass2::tableName();

				$this->__functions_get[$name] = function($model) use($s, $relationClass2) 
				{
					$statement = $s->db->prepare(
"SELECT `{$s->table2}`.* 
FROM `{$s->table1}`, `{$s->table2}` 
WHERE 
	`{$s->table1}`.`{$s->relationClass1Field1}` = :modelField AND
	`{$s->table1}`.`{$s->relationClass1Field2}` = `{$s->table2}`.`{$s->relationClass2Field}`"
					);
					$statement->bindNamedValue('modelField', $model->{$s->modelField});
					$result = $relationClass2::loadAllWithStatement($statement);
					if (is_array($result)) return $result;
					return [];
				};

				$this->__functions_add[$name] = function($model, $relationModel) use($s, $relationClass1) 
				{
					Relation::notNullField( $s->relationClass2Field, $relationModel->{$s->relationClass2Field} );
					Relation::notNullField( $s->modelField, $model->{$s->modelField} );
					//
					$middle = $relationClass1::loadWithWhereQuery(
						"`{$s->relationClass1Field1}` = :modelValue AND `{$s->relationClass1Field2}` = :relationValue",
						['modelValue' => $model->{$s->modelField}, 'relationValue' => $relationModel->{$s->relationClass2Field}]
					);
					if (!$middle) {
						$middle = $relationClass1::newModel();
					}
					$middle->{$s->relationClass1Field1} = $model->{$s->modelField};
					$middle->{$s->relationClass1Field2} = $relationModel->{$s->relationClass2Field};
					$middle->save(); // saved
				};

				$this->__functions_remove[$name] = function($model, $relationModel) use($s) 
				{
					$statement = $s->db->prepare(
"DELETE FROM `{$s->table1}` WHERE `{$s->relationClass1Field1}` = :modelValue AND `{$s->relationClass1Field2}` = :relationValue"
					);
					$statement->bindNamedValues(['modelValue' => $model->{$s->modelField}, 'relationValue' => $relationModel->{$s->relationClass2Field}]);
					$statement->execute(); // saved
				};
			} else {
				throw Relation_Exception::invalidSyntax2($relation);
			}
		} else {
			// this->%id% *-** %\namespace\class%->%field%
			// this->%field% ** %\namespace\class%->%id%
			if (preg_match('~^this->(\w+)\s*(\*\*|\*-\*\*)\s*([a-zA-Z0-9_\\\\]+)->(\w+)$~', $relation, $match)) {
				$field = $match[1];
				$toMany = $match[2] == '*-**';
				$relationClass = $this->parseClassName($match[3]);
				$relationField = $match[4];
				if ($toMany) {
					$this->__functions_get[$name] = function($model) use($field, $relationClass, $relationField) 
					{
						$result = $relationClass::loadAllWithWhereQuery("`$relationField` = :value", ['value' => $model->{$field}]);
						if (is_array($result)) return $result;
						return [];
					};
					$this->__functions_add[$name] = function($model, $relationModel) use($field, $relationField) 
					{
						Relation::notNullField( $field, $model->{$field} );
						//
						$relationModel->{$relationField} = $model->{$field};
						$relationModel->save(); // saved
					};
					$this->__functions_remove[$name] = function($model, $relationModel) use($field, $relationField, $relationClass) 
					{
						// getting default field value via constructor
						$newObj = $relationClass::newModel();
						$relationModel->{$relationField} = isset($newObj->{$relationField}) ? $newObj->{$relationField} : null;
						$relationModel->save(); // saved
					};
				} else {
					// toOne
					$this->__functions_get[$name] = function($model) use($field, $relationClass, $relationField) 
					{
						$result = $relationClass::loadWithField($relationField, $model->{$field});
						return $result;
					};
					$this->__functions_set[$name] = function($model, $relationModel) use($field, $relationField, $modelClass) 
					{
						if ($relationModel) {
							Relation::notNullField( $relationField, $relationModel->{$relationField} );
							//
							$model->{$field} = $relationModel->{$relationField};
							$model->save([ $field ]);
						} else {
							// getting default field value via constructor
							$newObj = $modelClass::newModel();
							$model->{$field} = isset($newObj->{$field}) ? $newObj->{$field} : null;
							$model->save([ $field ]);
						}
					};
				}
			} else {
				throw Relation_Exception::invalidSyntax($relation);
			}
		}
	}

	public function toManyAdd($name, Model $relationModel) {
		$this->parseRelationIfNeeded($name);
		if (isset($this->__functions_add[$name])) {
			$this->__functions_add[$name]($this->__model, $relationModel);
			$this->reset();
			return;
		}
		throw Relation_Exception::undefined($name);
	}

	public function toManyAddAll($name, $relationModels) {
		$model = $this->__model;
		if (is_array($relationModels)) {
			foreach ($relationModels as $relationModel) {
				$this->__model = $model;
				$this->toManyAdd($name, $relationModel);
			}
		}
	}

	public function toManyRemove($name, Model $relationModel) {
		$this->parseRelationIfNeeded($name);
		if (isset($this->__functions_remove[$name])) {
			$this->__functions_remove[$name]($this->__model, $relationModel);
			$this->reset();
			return;
		}
		throw Relation_Exception::undefined($name);
	}

	public function toManyRemoveAll($name) {
		$model = $this->__model;
		$models = $this->{$name};
		if (is_array($models)) {
			foreach ($models as $m) {
				$this->__model = $model;
				$this->toManyRemove($name, $m);
			}
			return;
		}
		throw Relation_Exception::undefined($name);
	}

	public function reset() {
		$this->__values = [];
	}

// Magic methods
	public function __set ( $name , $value ) 
	{
		$this->parseRelationIfNeeded($name);
		if (isset($this->__functions_get[$name])) {
			if (isset($this->__functions_set[$name])) {
				$this->__functions_set[$name]($this->__model, $value);
				$this->__values[$name] = $value;
			} else {
				throw Relation_Exception::toManyRelationIsReadonly($this->__modelClass, $name);
			}
			return;
		}
		throw Relation_Exception::undefined($name);
	}
	public function __get ( $name ) 
	{
		if (isset($this->__values[$name])) {
			return $this->__values[$name];
		}
		$this->parseRelationIfNeeded($name);
		if (isset($this->__functions_get[$name])) {
			$result = $this->__functions_get[$name]($this->__model);
			$this->__values[$name] = $result;
			return $result;
		}
		throw Relation_Exception::undefined($name);
		return null;
	}
// Exceptions
	static function notNullField($field, $value) {
		if (empty($value) || $value == null) {
			throw Relation_Exception::nullField($field);
		}
	}
}
