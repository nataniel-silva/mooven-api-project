<?php
namespace App\EventListener;

use App\Entity\EntityFactory;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Utils\Singleton;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

/**
 *
 * Listener para injetar dependências no Singleton
 *
 */
class SingletonDependencyInjection {
	private $_translator;
	private $_kernelEnv;
	
	public function __construct(TranslatorInterface $translator, EntityFactory $ef, $kernelEnv) {
		$this->_translator = $translator;
		$this->_kernelEnv = $kernelEnv;
		
		// Injeto as dependencias aqui já
		Singleton::setTranslator($translator);
		Singleton::setEntityFactory($ef);
		Singleton::setEnv($kernelEnv);
	}
	
	public function onKernelRequest(GetResponseEvent $event) {
		
	}
	
	public function onConsoleCommand(ConsoleCommandEvent $event) {
		
	}
}
