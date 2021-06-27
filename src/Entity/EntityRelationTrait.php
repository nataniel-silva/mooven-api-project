<?php
namespace App\Entity;

use App\Entity\DefaultEntity;
use Doctrine\Common\Collections\Collection;

/**
 * Trait para usar em entidades que possuem relação do tipo N pra N
 * Obs: exclusivamente para uso em entidades DefaultEntity
 */
trait EntityRelationTrait {
	/**
	 * @var bool
	 */
	private $_entityRelationFlushEnabled = true;
	
	/**
	 * @param bool $vl
	 */
	protected function setEntityRelationFlushEnabled(bool $vl = true) {
		$this->_entityRelationFlushEnabled = $vl;
	}
	
	/**
	 * @param DefaultEntity $childEntity
	 * @param Collection|DefaultEntity[] $relationEntities
	 * @param string $relationFieldNameOwned Nome do campo que tem o ID da tabela owned da relação
	 *   Informar apenas se não seguir o padrão de ter o mesmo nome da tabela referenciada
	 * @return DefaultEntity|NULL
	 */
	protected function getRelationEntityByChildEntity(
		DefaultEntity $childEntity, $relationEntities, $relationFieldNameOwned = ''
	): ?DefaultEntity {
		$method = $relationFieldNameOwned ?
			'get'.ucfirst($relationFieldNameOwned) : 'getId'.$childEntity->getEntityName();
		$idChildEntity = $childEntity->getId();
		foreach ($relationEntities as $relationEntity) {
			if ($relationEntity->{$method}()->getId() == $idChildEntity) {
				return $relationEntity;
			}
		}
		return null;
	}

	/**
	 * Retorna as entidades filhas da relação
	 * @param string $entityName Nome da entidade filha que se deseja
	 * @param Collection $relationEntities Entidades da relação
	 * @return DefaultEntity[]
	 */
	protected function getChildrenEntitiesFromRelation(string $entityName, Collection $relationEntities): array {
		$method = 'getId'.$entityName;
		$entities = [];
		foreach ($relationEntities as $relationEntity) {
			if (
				(!method_exists($relationEntity, 'getHas') || $relationEntity->getHas())
				&& (!method_exists($relationEntity, 'getActive') || $relationEntity->getActive())
			) {
				$entities[] = $relationEntity->{$method}();
			}
		}
		return $entities;
	}
	
	/**
	 * @param DefaultEntity|int $entityOrId
	 * @param DefaultEntity[] $entities
	 * @return DefaultEntity|null
	 */
	protected function getEntityFromArray($entityOrId, array $entities): ?DefaultEntity {
		$idEntity = is_int($entityOrId) ? $entityOrId : $entityOrId->getId();
		foreach ($entities as $entity) {
			if ($entity->getId() == $idEntity) {
				return $entity;
			}
		}
		return null;
	}
	
	/**
	 * @param DefaultEntity|int $entityOrId
	 * @param DefaultEntity[] $entities
	 * @return bool
	 */
	protected function hasEntityInArray($entityOrId, array $entities): bool {
		return (bool)$this->getEntityFromArray($entityOrId, $entities);
	}

	/**
	 * Adiciona entidade filha em uma relação
	 * @param DefaultEntity $childEntity Entidade filha a ser vinculada
	 * @param Collection $relationEntities Coleção com todas as entidades de relação já existentes
	 * @param string $relationEntityClass Classe da entidade de relação
	 * @param string $relationFieldNameOwner Nome do campo que tem o ID da tabela owner da relação
	 *   Informar apenas se não seguir o padrão de ter o mesmo nome da tabela referenciada
	 * @param string $relationFieldNameOwned Nome do campo que tem o ID da tabela owned da relação
	 *   Informar apenas se não seguir o padrão de ter o mesmo nome da tabela referenciada
	 * @throws Exception
	 * @return DefaultEntity A entidade da relação adicionada
	 */
	protected function addChildEntityInRelation(
		DefaultEntity $childEntity, Collection $relationEntities, string $relationEntityClass,
		$relationFieldNameOwner = '', $relationFieldNameOwned = ''
	): DefaultEntity {
		$relationEntity = $this->getRelationEntityByChildEntity($childEntity, $relationEntities, $relationFieldNameOwned);
		$hasHasMethod = method_exists($relationEntity, 'getHas');
		if ($relationEntity && (!$hasHasMethod || $relationEntity->getHas())) { // Já está vinculada
			return $relationEntity;
		} elseif (!$relationEntity) {
			$relationEntity = $this->ef->get($relationEntityClass);
			$method = $relationFieldNameOwner ?
				'set'.ucfirst($relationFieldNameOwner) : 'setId'.$this->getEntityName();
			$relationEntity->{$method}($this);
			$method = $relationFieldNameOwned ?
				'set'.ucfirst($relationFieldNameOwned) : 'setId'.$childEntity->getEntityName();
			$relationEntity->{$method}($childEntity);
			$hasHasMethod = method_exists($relationEntity, 'getHas');
		}
		if ($hasHasMethod) {
			$relationEntity->setHas(true);
		}
		try {
			$this->em()->persist($relationEntity);
			if ($this->_entityRelationFlushEnabled) {
				$this->em()->flush();
			}
		} catch (\Exception $e) {
			if ($hasHasMethod) {
				$relationEntity->setHas(false); // Só desfaço o que tinha alterado
			}
			throw $e;
		}
		if (!$relationEntities->contains($relationEntity)) {
			$relationEntities->add($relationEntity);
		}
		return $relationEntity;
	}
	
	/**
	 * Remove uma entidade filha de uma relação (caso esteja mesmo nesta relação)
	 * @param DefaultEntity $childEntity
	 * @param Collection $relationEntities
	 * @param string $relationFieldNameOwned Nome do campo que tem o ID da tabela owned da relação
	 *   Informar apenas se não seguir o padrão de ter o mesmo nome da tabela referenciada
	 * @throws Exception
	 * @return DefaultEntity|null A entidade da relação removida. Retorna null caso já não exista a relação
	 */
	protected function removeChildEntityFromRelation(
		DefaultEntity $childEntity, Collection $relationEntities, $relationFieldNameOwned = ''
	): ?DefaultEntity {
		$relationEntity = $this->getRelationEntityByChildEntity($childEntity, $relationEntities, $relationFieldNameOwned);
		$hasHasMethod = method_exists($relationEntity, 'getHas');
		if (!$relationEntity || ($hasHasMethod && !$relationEntity->getHas())) {
			return $relationEntity;
		}
		try {
			if ($hasHasMethod) {
				$relationEntity->setHas(false);
				$this->em()->persist($relationEntity);
			} else {
				$this->em()->remove($relationEntity);
			}
			if ($this->_entityRelationFlushEnabled) {
				$this->em()->flush();
			}
		} catch (\Exception $e) {
			if ($hasHasMethod) {
				$relationEntity->setHas(true); // Só desfaço o que tinha alterado
			}
			throw $e;
		}
		return $relationEntity;
	}
}