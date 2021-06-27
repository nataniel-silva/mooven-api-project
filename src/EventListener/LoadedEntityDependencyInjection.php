<?php
namespace App\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\DefaultEntity;
use App\Entity\EntityFactory;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 *
 * Listener para tratar a injeção de dependências nas entidades
 * que são carregadas pelo Doctrine (findAll por exemplo), pois
 * ele não usa o EntityFactory para carregá-las
 *
 */
class LoadedEntityDependencyInjection {
	private $_translator;
	private $_ef;
	private $_registry;
	public function __construct(TranslatorInterface $translator, EntityFactory $ef, RegistryInterface $registry) {
		$this->_translator = $translator;
		$this->_ef = $ef;
		$this->_registry = $registry;
	}
	
	public function postLoad(LifecycleEventArgs $args) {
		/**
		 * 
		 * @var DefaultEntity $entity
		 */
		$entity = $args->getEntity();
		if (!($entity instanceof DefaultEntity)) {
			return;
		}
		$schema = explode('\\', get_class($entity))[2];
		$entity->setRegistry($this->_registry);
		$entity->setDefaultEntityManagerName($schema === 'Log' ? 'log' : null);
		$entity->setTranslator($this->_translator);
		$entity->setEntityFactory($this->_ef);
	}
}
