<?php
namespace App\Entity;

use App\DefaultTrait;
use App\Exception\DefaultException;
use App\Repository\DefaultRepository;
use App\Utils\Singleton;

/**
 * 
 * Factory para tratar a injeção de dependências nas entidades
 * Toda e qualquer Entity deve ser obtida através deste Factory
 *
 */
class EntityFactory {
	use DefaultTrait; // Ele não implementa a DefaultInterface porque dá problema de dependência circular. Olhse em services.yaml para mais informações
	
	/**
	 * Singleton dos repositórios já instanciados. O índice do array
	 * é o nome da classe
	 * @var DefaultRepository[]
	 */
	private static $_cacheRepo = [];
	
	/**
	 * Retorna uma nova instância da entidade
	 * @param string $entityClass
	 * @return \App\Entity\DefaultEntity
	 */
	public function get($entityClass): DefaultEntity {
		if (!class_exists($entityClass)) {
			throw new DefaultException('internal.entityfactory.entity_class_not_found', ['%class%' => $entityClass]);
		}
		/**
		 * @var $e DefaultEntity
		 */
		$entity = new $entityClass();
		if (!($entity instanceof DefaultEntity)) {
			throw new DefaultException('internal.entityfactory.invalid_entity_class', ['%class%' => $entityClass]);
		}
		//$e->setEntityManager(Singleton::getEntityManager()); // Por algum motivo, o EntityManager injetado aqui pelo Symfony era diferente do injetado nos demais lugares, o que causava problema quando usava os dois em uma mesma transação
		$schema = explode('\\', $entityClass)[2];
		$entity->setRegistry($this->_registry);
		$entity->setDefaultEntityManagerName($schema === 'Log' ? 'log' : null);
		$entity->setTranslator($this->translator);
		$entity->setEntityFactory($this);
		return $entity;
	}
	
	/**
	 * Retorna o repositório a partir da classe de sua respectiva entity
	 * @param string $entityClass
	 * @throws DefaultException
	 * @return DefaultRepository
	 */
	public function getRepo(string $entityClass): DefaultRepository {
		$repositoryClass = str_replace('\\Entity\\', '\\Repository\\', $entityClass).'Repository';
		if (isset(self::$_cacheRepo[$repositoryClass])) {
			// Faço isso para garantir que se o Entity Manager antigo foi fechado e resetado, agora ele vai ter o novo EM injetado
			self::$_cacheRepo[$repositoryClass]->updateEntityManager();
			return self::$_cacheRepo[$repositoryClass];
		}
		
		if (!class_exists($repositoryClass)) {
			throw new DefaultException('internal.entityfactory.repository_class_not_found', ['%class%' => $repositoryClass]);
		}
		/**
		 * @var $f DefaultRepository
		 */
		$f = new $repositoryClass($this->_registry, $this->translator);
		if (!($f instanceof DefaultRepository)) {
			throw new DefaultException('internal.entityfactory.invalid_repository_class', ['%class%' => $repositoryClass]);
		}
		self::$_cacheRepo[$repositoryClass] = $f;
		
		return self::$_cacheRepo[$repositoryClass];
	}
}