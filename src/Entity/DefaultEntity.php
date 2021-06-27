<?php
namespace App\Entity;

use App\Exception\BOException;
use App\Exception\DefaultException;
use App\DefaultTrait;
use App\DefaultInterface;
use App\Utils\Singleton;
use Doctrine\Common\Collections\Collection;

/**
 * Classe de entidade que deverá ser usada como classe pai para
 * todas as demais classes de entidade
 */
abstract class DefaultEntity implements DefaultInterface {
	use DefaultTrait;
	/**
	 * O índice deve ser o nome do campo e o valor seu respectivo label
	 * @var string[]
	 */
	private $_fieldLabels;
	
	/**
	 * Seta os dados de uma entidade através de um array
	 * onde o índice deve ser o nome do atributo. Este método
	 * vai chamar um método set com o nome do atributo
	 * 
	 * @param array $data
	 * @throws DefaultException
	 */
	public function setDataFromArray(array $data): void {
		foreach ($data as $attr => $value) {
			$this->__set($attr, $value);
		}
	}
	
	/**
	 * Seta o label para os campos através de um array associativo
	 * onde o índice é o nome do campo e o valor o label
	 * @param string[] $labels Labels para os campos
	 */
	public function setFieldsLabels(array $labels) {
		foreach ($labels as $field => $label) {
			$this->setFieldLabel($field, $label);
		}
	}
	
	/**
	 * Seta o label para o campo
	 * @param string $fieldName Nome do campo
	 * @param string $label Label para o campo
	 */
	public function setFieldLabel($fieldName, $label) {
		$this->_fieldLabels[$fieldName] = $label;
	}
	
	/**
	 * Retorna o label para o campo solicitado
	 * @param string $fieldName
	 * @return string
	 */
	public function getFieldLabel($fieldName): string {
		if (isset($this->_fieldLabels[$fieldName])) {
			return $this->_fieldLabels[$fieldName];
		}
		return $fieldName;
	}
	
	/**
	 * Get mágico para permitir fazer get da seguinte forma:
	 * $valor = $entity->attr;
	 * @param string $attr Nome do campo a ter seu valor obtido
	 * @throws DefaultException
	 */
	public function __get($attr) {
		$method = 'get'.ucfirst($attr);
		if (method_exists($this, $method)) {
			return $this->{$method}();
		}
		throw new DefaultException('internal.entity.field_without_get_method', ['%field%' => $attr]);
	}
	
	/**
	 * Set mágico para permitir fazer set da seguinte forma:
	 * $entity->attr = $valor;
	 * @param string $attr Nome do campo a ter seu valor setado
	 * @param mixed $value Valor para o campo
	 * @throws DefaultException
	 */
	public function __set($attr, $value) {
		$method = 'set'.ucfirst($attr);
		if (method_exists($this, $method)) {
			$this->{$method}($value);
			return;
		}
		throw new DefaultException(
			'internal.entity.field_without_set_method',
			['%field%' => $attr, '%class%' => get_class($this)]
		);
	}
	
	/**
	 * Método toString padrão das entidades. Ele
	 * vai sempre retornar o ID da entidade, se achar
	 * algum método com o nome no padrão 'getId' + Nome da Entidade
	 * Se não achar, vai retornar string vazia
	 * @return string
	 */
	public function __toString(): string {
		if (property_exists($this, 'id')) {
			return (string)$this->id;
		}
		return '';
	}
	
	/**
	 * Transforma o objeto num array associativo onde os índices
	 * são os atributos mapeados para o banco
	 * @param array $expand Array com os atributos que
	 *   também são entities e que devem ser expandidos (fazer toArray neles).
	 *   Os que não forem configurados para serem expandidos, vai
	 *   apenas colocar o ID no atributo.
	 *   O array é multinível com a seguinte estrutura: no índice deve ter o nome do atributo a ser expandido
	 *   e no valor deve ter um array com os seus atributos que também devem ser expandidos. Se nenhum de seus
	 *   atributos deve ser expandido, então deve possuir um array vazio.
	 *   Ex: ['idAddress' => ['idPlace' => []], 'idFileLogo' => []]
	 *   Na configuração acima, vai expandir o atributo idAddress que dentro dele tem um idPlace, que também será expandido
	 *   Vai também expandir o atributo idFileLogo
	 *   Caso queira remover um atributo, em vez de colocar um array no valor, basta colocar false.
	 *   Ex: ['idAddress' => ['idPlace' => []], 'idFileLogo' => false]
	 *   Na configuração acima, vai expandir o atributo idAddress que dentro dele tem um idPlace, que também será expandido,
	 *   porém o atributo idFileLogo será removido
	 *   Caso queira adicionar um atributo que é removido por padrão, basta colocar true ou informar o array de expansão.
	 *   Ex: ['idAddress' => ['idPlace' => []], 'atributoRemovidoPorPadrao' => true]
	 *   Obs: a remoção/adição serve para qualquer tipo de atributo, ou seja, não precisa ser um objeto
	 *   Caso queira que a lógica seja invertida e informar apenas os campos que devem ser retornados,
	 *   basta informar um atributo de nome '_whiteList' que deve ser um array com os nomes dos atributos
	 *   que podem ser retornados.
	 *   Ex: ['_whiteList' => ['attr1', 'attr2']]
	 *   Na configuração acima, somente vai retornar os atributos de nome 'attr1' e 'attr2'. Obs: se o campo
	 *   tiver removeFromToArray setado para true, ele não vai ser retornado só de adicionar ele na _whiteList.
	 *   Caso queira informar uma lista de campos que não devem ser retornados,
	 *   basta informar um atributo de nome '_blackList' que deve ser um array com os nomes dos atributos
	 *   que não podem ser retornados.
	 *   Ex: ['_blackList' => ['attr1', 'attr2']]
	 *   Na configuração acima, vai retornar todos os atributos configurados para serem retornados,
	 *   exceto 'attr1' e 'attr2'. Obs: essa configuração não é o inverso do whitelist, pois ela
	 *   somente remove campos do retorno. Por exemplo, se tivesse um campo 'attr3' com removeFromToArray
	 *   setado para true, a configuração acima não ia fazer com que ele fosse retornado
	 * @param bool $autoRename Indica se deve fazer renomeio automático dos atributos que
	 *   são entidades expandidas. Por exemplo, dentro digamos que tenha um atributo "idUser"
	 *   que deve ser expandido para a entidar "user". Se esse parâmetro for passado com true,
	 *   em vez de termos como resultado ['idUser' => [...]], teremos como resultado ['user' => [...]],
	 *   indicando que trata-se de uma entidade e não do ID dela
	 *   Por padrão não faz o renomeio automático
	 * @return array
	 */
	public function toArray(array $expand = [], bool $autoRename = false): array {
		$a = [];
		$whiteList = $expand['_whiteList'] ?? false;
		$blackList = $expand['_blackList'] ?? false;
		foreach ($this->getOrmInfo() as $attr => $ormInfo) {
			$inExpand = array_key_exists($attr, $expand);
			if ( // Atributo removido do resultado
				($inExpand && $expand[$attr] === false)
				|| (($ormInfo['removeFromToArray'] ?? false) && !$inExpand)
				|| ($whiteList && !in_array($attr, $whiteList))
				|| ($blackList && in_array($attr, $blackList))
			) {
				continue;
			}
			$mustExpand = $inExpand && is_array($expand[$attr]);
			$a[$attr] = $this->__get($attr);
			if (is_object($a[$attr])) {
				if ($a[$attr] instanceof DefaultEntity) {
					if ($mustExpand) {
						$a[$attr] = $a[$attr]->toArray($expand[$attr], $autoRename);
					} else {
						$a[$attr] = $a[$attr]->getId();
					}
				} elseif ($a[$attr] instanceof \DateTime) {
					$a[$attr] = $a[$attr]->format($ormInfo['dateFormat'] ?? 'Y-m-d H:i:s');
				}
			} elseif (is_array($a[$attr])) { // Atributos n x n, todos os elementos devem ser entidades
				foreach ($a[$attr] as $ind => $val) {
					$a[$attr][$ind] = $val->toArray(
						$inExpand && is_array($expand[$attr]) ? $expand[$attr] : [],
						$autoRename
					);
				}
			}
			if ($mustExpand && $autoRename && substr($attr, 0, 2) === 'id') {
				$newAttrName = lcfirst(substr($attr, 2));
				$a[$newAttrName] = $a[$attr];
				unset($a[$attr]);
			}
		}
		return $a;
	}
	
	/**
	 * Remove as validações de campos que não se deseja validar
	 * @param array $rules Regras de validação onde o índice é o nome do campo
	 * @param array $selectedFields Campos que se deseja validar
	 * @return array Regras filtradas
	 */
	private function _filterValidationRules(array $rules, array $selectedFields): array {
		if ($selectedFields) {
			foreach ($rules as $field => $rule) {
				if (!in_array($field, $selectedFields)) {
					unset($rules[$field]);
				}
			}
		}
		return $rules;
	}
	
	/**
	 * Deve retornar um array com todos os atributos mapeados para o banco
	 * @return array
	 */
	public function getOrmAttributes(): array {
		return array_keys($this->getOrmInfo());
	}
	
	/**
	 * Retorna as regras de validação para os campos selecionados
	 * @param array $fields
	 * @return array
	 */
	public function getValidationRules(array $fields = array()): array {
		$rules = [];
		foreach ($this->getOrmInfo() as $field => $info) {
			if ($info['pk'] || !isset($info['validationRule'])) { // Deve ser gerada automaticamente...
				continue;
			}
			$rules[$field] = $info['validationRule'];
		}
		return $this->_filterValidationRules($rules, $fields);
	}
	
	/**
	 * Deve retornar as um array com as informações dos campos mapeados para o banco
	 * @return array
	 */
	public abstract function getOrmInfo(): array;
		
	/**
	 * Deve retornar um array associativo onde o índice é o nome do campo e o valor
	 * o label. Todas as classes filhas devem implementar este método.
	 * Estes labels servem de auxílio para validação de dados da entidade,
	 * pois não é legal expor o nome do atributo na entidade.
	 * Obs: é possível implementar a internacionalização dos labels de forma automatizada
	 * diretamente no método getFieldLabel, basta padronizar os IDs para tradução utilizando
	 * o esquema + nome da entidade + nome do campo
	 * @return string[]
	 */
	//abstract protected function myFieldsLabels (): array;
	
	/**
	 * @return string Nome da classe da entity
	 */
	public function getEntityName() {
		$path = explode('\\', get_class($this));
		return array_pop($path);
	}
	
	/**
	 * 
	 * @param Collection $collection
	 * @return DefaultEntity[]
	 */
	protected function extractEntitiesFromDoctrineCollection(Collection $collection) {
		$entities = [];
		foreach ($collection as $entity) {
			$entities[] = $entity;
		}
		return $entities;
	}
	
	/**
	 * Busca uma entidade pelo ID
	 * @param unknown $id ID da entidade
	 * @param string $entityClass Classe da entidade
	 * @param array $extraCriteria Condições adicionais para a busca
	 * @return DefaultEntity
	 * @throws \Throwable Se não encontrar a entidade
	 */
	public static function getEntity($id, string $entityClass, array $extraCriteria = []): DefaultEntity {
		$repo = Singleton::getEntityFactory()->getRepo($entityClass);
		if ($extraCriteria) {
			$entity = $repo->findOneBy(array_merge(['id' => $id], $extraCriteria));
		} else { // Isso faz uso do cache do Doctrine, o comando acima não
			$entity = $repo->find($id);
		}
		if (!$entity) {
			DefaultException::throwBOError('entity.not_found');
		}
		return $entity;
	}
	
	/**
	 * Caminho completo da classe BO que contera a function da regra de negocio.
	 */
	public const TRIGGER_SAVE_BO = 'BO';
	
	/**
	 * Nome da funcao que sera chamada a partir do BO.
	 */
	public const TRIGGER_SAVE_FUNCTION = 'fc';
	
	/**
	 * Quando que a operacao deve ser feita, ANTES ou DEPOIS.
	 */
	public const TRIGGER_WHEN = 'when'; // Operacoes abaixo
	public const TRIGGER_WHEN_BEFORE = 'before';
	public const TRIGGER_WHEN_AFTER = 'after';
	
	/**
	 * Em qual operacao aplicar a trigger, ATUALIZAR ou INSERIR.
	 * Sem suporte a DELETE, nao eh uma pratica recomendada.
	 * Senao informado, vai fazer nas duas operacoes.
	 */
	public const TRIGGER_OPERATION = 'op';
	public const TRIGGER_OPERATION_INSERT = 'insert';
	public const TRIGGER_OPERATION_UPDATE = 'update';
	
	/**
	 * Informar um array de regras para serem aplicadas ao salvar o registro.
	 *
	 * Usar as constantes TRIGGER_SAVE_* como chaves do array:
	 * [
	 * 	[
	 * 		self::TRIGGER_SAVE_BO => 'App\\Business\\BusinessBO', self::TRIGGER_SAVE_FUNCTION => 'updateSomethingOnSomething',
	 * 		self::TRIGGER_WHEN => self::TRIGGER_WHEN_AFTER, self::TRIGGER_OPERATION => self::TRIGGER_OPERATION_INSERT
	 * ]
	 *
	 * @return array
	 */
	public function getTriggersSaveRules(): array {
		return [];
	}
	
	public function getTriggersBeforeSaveRules(): array {
		return $this->_getTriggersForSave(self::TRIGGER_WHEN_BEFORE);
	}
	
	public function getTriggersAfterSaveRules(): array {
		return $this->_getTriggersForSave(self::TRIGGER_WHEN_AFTER);
	}
	
	private function _getTriggersForSave(string $operation): array {
		return array_filter($this->getTriggersSaveRules(), function ($v) use ($operation) { return $v[self::TRIGGER_WHEN] == $operation; });
	}
	
	/**
	 * Busca o id, sem dar erro quando a entidade tiver o getId(): int (int obrigatorio).
	 *
	 * @return
	 */
	public function getIdOrNull() {
		return empty($this->id) ? null : $this->id;
	}
	
}