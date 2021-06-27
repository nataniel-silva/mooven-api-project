<?php
namespace App;

use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\EntityFactory;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\Bundle\DoctrineBundle\Registry;
use App\Utils\Singleton;

/**
 * 
 * Trait para tratar a injeção de dependências.
 * Ela deve ser usada para implementar a DefaultInterface
 *
 */
trait DefaultTrait {
	/**
	 *
	 * @var TranslatorInterface
	 */
	protected $translator;
	/**
	 *
	 * @var Registry
	 */
	private $_registry;
	/**
	 *
	 * @var EntityFactory
	 */
	protected $ef;
	/**
	 * @var string
	 */
	private $_defaultEntityManagerName = null;

	/**
	 * @param TranslatorInterface $translator
	 */
	public function setTranslator(TranslatorInterface $translator) {
		$this->translator = $translator;
	}
	
	/**
	 * @param RegistryInterface $registry
	 */
	public function setRegistry(RegistryInterface $registry) {
		$this->_registry = $registry;
	}

	/**
	 * @param EntityFactory $ef
	 */
	public function setEntityFactory(EntityFactory $ef) {
		$this->ef = $ef;
	}
	
	/**
	 * Retorna o Entity Manager de acordo com seu nome.
	 * Os nomes possíveis ficam no arquivo doctrine.yaml, seção doctrine >> orm >> entity_managers
	 * @param string $name Nome do Entity Manager. Se não informado, utilizará o EM padrão definido
	 *   para a classe através do método self::setDefaultEntityManagerName. Se a classe também não
	 *   tiver um EM padrão definido, utilziará o padrão definido na configuração (default)
	 * @return EntityManager
	 */
	public function em(string $name = null): EntityManager {
		if ($name === null) {
			if ($this->_defaultEntityManagerName) { // Usa o EM padrão setado para classe
				$name = $this->_defaultEntityManagerName;
			} elseif (Singleton::getEntityManagerNameToUseInsteadDefault()) { // Se tiver setado globalmente para usar algum outro EM no lugar do padrão, usa ele 
				$name = Singleton::getEntityManagerNameToUseInsteadDefault();
			}
		}
		return $this->_registry->getManager($name);
	}
	
	/**
	 * Reset o Entity Manager. É útil para casos em que ocorreu algum erro de SQL executado,
	 * pois isso causa o "fechamento" do EM, tornando o mesmo inutilizável. Ao chamar este método
	 * o EM antigo vai ser destruído e um novo será criado. Isso implica que todas as entidades
	 * que estavam sendo gerenciadas pelo antigo passarão a estarem num estado em que não estão
	 * gerenciadas por nenhum EM, logo operações a serem realizadas com elas provavelmente causarão
	 * erro. Para que isso não ocorra, elas devem ser carregadas novamente utilizando o novo EM.
	 * @param string $name @see self::em
	 */
	
	public function resetEntityManager(string $name = null): void {
		if ($name === null && $this->_defaultEntityManagerName) {
			$name = $this->_defaultEntityManagerName;
		}
		$this->_registry->resetManager($name);
	}
	
	/**
	 * Seta o o EM padrão a ser utilizado pela classe
	 * @param string $name @see self::em
	 */
	public function setDefaultEntityManagerName(string $name = null): void {
		$this->_defaultEntityManagerName = $name;
	}
	
	/**
	 * Retorna o EM padrão setado para ser utilizado pela classe
	 * @return string|null
	 */
	public function getDefaultEntityManagerName(): ?string {
		return $this->_defaultEntityManagerName;
	}
	
	/**
	 * Inicia uma transação
	 * Obs: Se já tiver dentro de uma transação, não faz nada
	 * Usar somente em controllers ou em BOs que rodam como job pois eles devem controlar a transação
	 * Em outros lugar no máximo utilizar self::savepoint e self::rollbackTo
	 */
	public function begin(): void {
		if (!$this->inTransaction()) {
			$this->em()->beginTransaction();
		}
	}
	
	/**
	 * Commita uma transação
	 * Usar somente em controllers ou em BOs que rodam como job pois eles devem controlar a transação
	 * Em outros lugar no máximo utilizar self::savepoint e self::rollbackTo
	 */
	public function commit(): void {
		$this->em()->commit();
	}
	
	/**
	 * Desfaz as alterações feitas na transação e termina com ela
	 * Usar somente em controllers ou em BOs que rodam como job pois eles devem controlar a transação
	 * Em outros lugar no máximo utilizar self::savepoint e self::rollbackTo
	 */
	public function rollback(): void {
		$this->em()->rollback();
	}

	/**
	 * Identifica se tem uma transação ativa no banco
	 * @return bool
	 */
	public function inTransaction(): bool {
		return $this->em()->getConnection()->isTransactionActive();
	}
	
	/**
	 * Cria savepoint na transação
	 * @param string $savepoint Nome do savepoint
	 */
	public function savepoint(string $savepoint): void {
		$this->em()->getConnection()->createSavepoint($savepoint);
	}
	
	/**
	 * Faz rollback para um savepoint previamente criado
	 * @param string $savepoint Nome do savepoint
	 */
	public function rollbackTo(string $savepoint): void {
		$this->em()->getConnection()->rollbackSavepoint($savepoint);
	}
}