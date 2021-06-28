<?php
namespace App\Business;

use App\Utils\Validator;
use App\Entity\DefaultEntity;
use App\DefaultTrait;
use App\Utils\TranslatorDomain;
use App\Exception\DefaultException;
use App\Exception\BOException;
use App\Exception\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * 
 * Trait para tratar a injeção de dependências.
 * Ela deve ser usada para implementar a DefaultInterface
 *
 */
trait BOTrait {
	use DefaultTrait;
	/**
	 * 
	 * @var Validator
	 */
	private $validator;
	/**
	 *
	 * @var DefaultException[]
	 */
	private $_errors = [];
	
	public function setValidator(Validator $validator) {
		$this->validator = $validator;
	}
	
	/**
	 * Limpa os erros
	 */
	public function resetErrors(): void {
		$this->_errors = array();
	}
	
	/**
	 *
	 * @param string $attr Atributo relacionado com a mensagem de erro
	 * Obs: o atributo não precisa ter necessariamente relação com alguma
	 * entity.
	 * @param string $attr Atributo para o qual vai setar o erro
	 * @param DefaultException $e Throwable com o erro
	 */
	private function _addError(string $attr, DefaultException $e): void {
		$this->_errors[$attr] = $e;
	}
	
	/**
	 * Adiciona erros a partir de um array indexado
	 * @param DefaultException[] $errors
	 */
	private function _addErrors(array $errors): void {
		foreach ($errors as $attr => $error) {
			$this->_addError($attr, $error);
		}
	}
	
	/**
	 *
	 * @param string $attr Atributo relacionado com a mensagem de erro
	 * Obs: o atributo não precisa ter necessariamente relação com alguma
	 * entity.
	 * @param string $attr Atributo para o qual vai setar o erro
	 * @param string $msgId ID da mensagem de erro para traduzir
	 * @param array $transParams Parâmetros para passar à mensagem a ser traduzida
	 * @param string $domain Domínio da mensagem a ser traduzida
	 */
	private function _addErrorStr(string $attr, string $msgId, array $transParams = [], $domain = TranslatorDomain::ERROR): void {
		$this->_addError($attr, new DefaultException($msgId, $transParams, $domain));
	}
	
	/**
	 * Retorna os erros encontrados durante a última operação
	 * @param $attr Nome do atributo. Se não informado
	 * retorna todos os erros identificados
	 * @return DefaultException[]
	 */
	public function getErrors(string $attr = ''): array {
		if ($attr) {
			return isset($this->_errors[$attr]) ? $this->_errors[$attr] : [];
		}
		return $this->_errors;
	}
	
	/**
	 * Retorna se tem algum erro
	 * @param $attr Nome do atributo para verificar se possui
	 * erro. Se não informado retorna se tem algum erro para
	 * qualquer atributo
	 * @return bool
	 */
	public function hasError(string $attr = ''): bool {
		if ($attr) {
			return isset($this->_errors[$attr]) && $this->_errors[$attr];
		}
		return count($this->_errors) > 0;
	}
	
	
	/**
	 * Valida os dados da entidade a partir das regras de validação
	 * Obs: os erros devem ser sempre obtidos via getErrors
	 * @param DefaultEntity $entity
	 * @throws \Exception
	 */
	private function _validateEntity(DefaultEntity $entity, array $rules): void {
		$this->resetErrors();
		if ($rules) {
			$this->_addErrors($this->validator->validateEntityFields($entity, $rules));
			$this->throwIfError('entity.invalid');
		}
	}
	
	/**
	 * Lança erros, caso existam
	 * @param string $errorId ID do erro principal
	 * @param array $transParams Parâmetros para tradução
	 */
	protected function throwIfError(string $errorId, array $transParams = []) {
		if ($this->hasError()) {
			throw new BOException($errorId, $transParams, $this->getErrors());
		}
	}
	
	/**
	 * Ver documentação de DefaultException::throwBOError.
	 */
	protected function throwError(string $errorId, ?\Throwable $mainError = null, array $transParams = []) {
		DefaultException::throwBOError($errorId, $mainError, $transParams);
	}
	
	/**
	 * Ver documentação de DefaultException::throwForbiddenError
	 */
	protected function throwForbiddenError(string $errorId, array $transParams = [], $title = '') {
		DefaultException::throwForbiddenError($errorId, $transParams, $title);
	}
	
	/**
	 * Salva uma entidade no banco
	 * @param DefaultEntity $entity
	 * @param array $fieldsValidate Campos que devem ser validados antes de salvar
	 *   Passar null caso nenhuma validação deva ser feita. Se passar array
	 *   vazio, vai validar todos os campos
	 * @param bool $flush Determina se deve fazer flush (salvar no banco de fato)
	 * @throws \Exception
	 */
	public function saveEntity(DefaultEntity $entity, ?array $fieldsValidate = [], bool $flush = true): void {
		if (is_array($fieldsValidate)) {
			$this->_validateEntity($entity, $entity->getValidationRules($fieldsValidate));
		}
		$operation = empty($entity->getIdOrNull()) ? DefaultEntity::TRIGGER_OPERATION_INSERT : DefaultEntity::TRIGGER_OPERATION_UPDATE;
		$entity = $this->_applyTriggerSaveRules($entity, DefaultEntity::TRIGGER_WHEN_BEFORE, $operation);
		try {
			$this->em()->persist($entity);
			if ($flush) {
				$this->flush();
			}
			$this->_applyTriggerSaveRules($entity, DefaultEntity::TRIGGER_WHEN_AFTER, $operation);
		} catch (\Exception $e) {
			if ($e instanceof BOException) {
				throw $e;
			}
			dump($e->getMessage());
			$this->throwError('entity.fails_to_save', $e);
		}
	}
	
	/**
	 * Aplicara as triggers. Ver DefaultEntity::getTriggerSaveRules.
	 *
	 * @param DefaultEntity $entity
	 * @param string $when
	 * @param string $op
	 *
	 * @return DefaultEntity
	 */
	private function _applyTriggerSaveRules(DefaultEntity $entity, string $when, string $op): DefaultEntity {
		$rules = $when == DefaultEntity::TRIGGER_WHEN_AFTER ? $entity->getTriggersAfterSaveRules() : $entity->getTriggersBeforeSaveRules();
		foreach ($rules as $rule) {
			// Verifica se eh insert, ou update ou nenhum dos dois
			$triggerOperation = $rule[DefaultEntity::TRIGGER_OPERATION] ?? null;
			if (!empty($triggerOperation) && $op != $triggerOperation) {
				continue;
			}
			
			$actualBO = get_class($this);$triggerBO = null;
			if ($actualBO == $rule[DefaultEntity::TRIGGER_SAVE_BO]) {
				$triggerBO = $this;
			}
			if (empty($triggerBO)) {
				foreach (get_class_vars($actualBO) as $prop => $propInfo) {
					if ($this->$prop instanceof $rule[DefaultEntity::TRIGGER_SAVE_BO]) {
						$triggerBO = $this->$prop;
						break 1;
					}
				}
			}
			if (empty($triggerBO)) {
				$this->throwError('entity.trigger.invalid_bo', null, [
					'%expected_bo%' => $rule[DefaultEntity::TRIGGER_SAVE_BO],
					'%actual_bo%' => $actualBO, '%entity%' => get_class($entity),
				]);
			}
			
			$function = $rule[DefaultEntity::TRIGGER_SAVE_FUNCTION];
			$entity = $triggerBO->$function($entity);
		}
		return $entity;
	}
	
	/**
	 * Persiste uma entidade do Doctrine
	 * @param DefaultEntity $entity @see self::saveEntity
	 * @param array $fieldsValidate @see self::saveEntity
	 * @throws \Exception
	 */
	public function persistEntity(DefaultEntity $entity, ?array $fieldsValidate = []) {
		$this->saveEntity($entity, $fieldsValidate, false);
	}
	
	/**
	 * Envia os comandos de entidades inseridas/atualizadas/removidas ao banco de dados
	 * @throws BOException|\Exception
	 */
	public function flush() {
		try {
			$this->em()->flush();
		} catch (\Exception $e) {
			if ($e instanceof UniqueConstraintViolationException) {
				$m = [];
				preg_match('/"uk_[a-zA-Z_]+"/i', $e->getMessage(), $m);
				if ($m) {
					$info = $m[0];
				} else {
					preg_match('/INSERT INTO ([a-zA-Z_.]+)/i', $e->getMessage(), $m);
					if ($m) {
						$info = $m[1];
					} else {
						$info = substr($e->getMessage(), 0, 40).'...';
					}
				}
				$this->throwError('entity.unique_constraint_failed', null, ['%info%' => $info]);
			}
			throw $e;
		}
	}
	
	/**
	 * Cria e salva no banco uma nova entidade
	 * a partir de um array associativo com as informações
	 * @param array $data
	 * @param string $entityClass Classe da entidade
	 * @param bool $flush Determina se deve fazer flush da operação
	 * @return DefaultEntity
	 * @throws \Exception
	 */
	public function createEntityFromArray(array $data, string $entityClass, bool $flush = true): DefaultEntity {
		$entity = $this->ef->get($entityClass);
		$entity->setDataFromArray($data);
		$this->saveEntity($entity, [], $flush);
		return $entity;
	}
	
	/**
	 * Ver documentação de DefaultEntity::getEntity.
	 */
	public function getEntity($id, string $entityClass, array $extraCriteria = []): DefaultEntity {
		return DefaultEntity::getEntity($id, $entityClass, $extraCriteria);
	}
	
	/**
	 * Atualiza informações de uma entidade
	 * a partir de um array associativo com as informações
	 * @param array $data O índice do array é a informação a ser atualizada
	 *   Obs: é necessário ter o índice "id" com a PK da entity
	 * @param array $fields Campos que podem ser atualizados
	 * @param string $entityClass Classe da entidade
	 * @param bool $flush Determina se deve fazer flush da operação
	 * @return DefaultEntity
	 * @throws \Exception
	 */
	public function updateEntityFromArray(array $data, array $fields, string $entityClass, bool $flush = true): DefaultEntity {
		$entity = $this->getEntity($data['id'], $entityClass);
		foreach ($fields as $field) {
			if (array_key_exists($field, $data)) {
				$entity->__set($field, $data[$field]);
			}
		}
		$this->saveEntity($entity, $fields, $flush);
		return $entity;
	}
	
	/**
	 * Salva informações de uma entidade. Cria novo registro se o ID não for informado,
	 * senão apenas atualiza as informações
	 * @param array $data Dados para criar/atualizar o registro
	 * @param array $fieldsUpdate Lista dos campos que podem ser atualizados
	 * @param string $entityClass Classe da entidade que vai salvar
	 * @param bool $flush Determina se deve fazer flush da operação
	 * @return DefaultEntity Entidade salva
	 * @throws BOException
	 */
	public function saveEntityFromArray(array $data, array $fieldsUpdate, string $entityClass, bool $flush = true): DefaultEntity {
		try {
			if ($data['id'] ?? null) {
				$entity = $this->updateEntityFromArray($data, $fieldsUpdate, $entityClass, $flush);
			} else {
				$entity = $this->createEntityFromArray($data, $entityClass, $flush);
			}
			return $entity;
		} catch (\Exception $e) {
			$this->throwError('entity.fails_to_save', $e);
		}
	}
	
	/**
	 * Remove registro de uma entidade
	 * @param mixed $entityOrId A entidade em si ou então seu ID
	 * @param string $entityClass
	 * @param bool $flush Determina se deve fazer flush da operação
	 * @throws BOException
	 */
	public function deleteEntity($entityOrId, string $entityClass, bool $flush = true): void {
		try {
			if (!$entityOrId) {
				return;
			}
			if ($entityOrId instanceof DefaultEntity) {
				if (!($entityOrId instanceof $entityClass)) {
					$this->throwError('internal.entity.unsupported_class');
				}
				$entity = $entityOrId;
			} else {
				$entity = $this->getEntity($entityOrId, $entityClass);
			}
			$this->em()->remove($entity);
			if ($flush) {
				$this->flush();
			}
		} catch (\Exception $e) {
			$this->throwError('entity.fails_to_delete', $e);
		}
	}

	/**
	 * Faz lock de uma entidade no banco (select for update basicamente)
	 * Serve para resolver problema de concomitância de operações em uma tabela
	 * @param DefaultEntity $entity
	 * @param bool $read Se false, vai bloquear apenas para escrita, senão bloqueará também para leitura
	 */
	public function lock(DefaultEntity $entity, bool $lockRead = false) {
		$this->em()->lock($entity, $lockRead ? \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE : \Doctrine\DBAL\LockMode::PESSIMISTIC_READ);
	}
}
