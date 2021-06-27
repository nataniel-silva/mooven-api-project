<?php
namespace App\Utils;

use App\Entity\DefaultEntity;
use App\Exception\DefaultException;

class Validator {
	public const STRING = 'string';
	public const INTEGER = 'integer';
	public const BOOLEAN = 'boolean';
	public const FLOAT = 'double';
	public const OBJECT = 'object';
	public const ARRAY = 'array';
	
	public const REGEX_PHONE_CHARS = '/^[0-9\(\)\-+ ]+$/';
	public const REGEX_EMAIL = '/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD';
	public const REGEX_LOCALES = '/pt_BR|en/';
	public const REGEX_PASSWORD = '/^(?=.*[a-z])(?=.*\d)(?=.*\W).{8,}$/i';
	
	/**
	 * Erros identificados na última validação realizada
	 * @var DefaultException[]
	 */
	private $_errors = array();
	/**
	 * Prefixo para os ids das mensagens de erros encontradas
	 * @var string
	 */
	private $_prefix = '';
	
	private function _resetErrors() {
		$this->_errors = array();
	}
	private function _addError($attr, DefaultException $e) {
		$this->_errors[$attr] = $e;
	}
	
	/**
	 * Seta o prefixo para os ids das mensagens de erros encontradas
	 * @param string $prefix
	 */
	public function setPrefix(string $prefix): self {
		$this->_prefix = $prefix;
		return $this;
	}
	
	/**
	 * Retorna os erros encontrados durante a última validação 
	 * O índice é o nome do atributo validado
	 * @return DefaultException[]
	 */
	public function getErrors(): array {
		return $this->_errors;
	}
	
	/**
	 * @see Validator::validate
	 * Valida os campos de uma entidade através das regras fornecidas.
	 * Obs: valida apenas os campos informados nas regras.
	 * @param DefaultEntity $entity Entidade a ser validada.
	 * @param string[] $rules
	 * @return string[] Retorna os erros encontrados durante a validação.
	 * @throws \Exception
	 */
	public function validateEntityFields (DefaultEntity $entity, array $rules): array {
		$this->_resetErrors();
		if (!$rules) {
			return $this->getErrors();
		}
		$entityData = [];
		foreach ($rules as $attr => $rule) {
			$entityData[$attr] = $entity->{$attr};
			if (!isset($rule['label'])) {
				$rule['label'] = $entity->getFieldLabel($attr);
			}
		}
		return $this->setPrefix('entity.')->validate($entityData, $rules);
	}
	
	/**
	 * Valida os dados de um array através das regras fornecidas
	 * @param array $data Array com os dados a serem validados. O índice é o nome do campo
	 * e o valor, tem a seguinte estrutura:
	 * [
	 *   'val' - Valor do campo. Obrigatório
	 * ]
	 * @param string[] $rules Regras. O índice é o nome do campo, e o valor tem o formato:
	 * [
	 *   'type' - Um dos tipos definidos na classe. Obrigatório.
	 *   'length' - Array com a seguinte estrutura: - Opcional
	 *     [
	 *       0 - Menor tamanho permitido da string ou menor número permitido. Obrigatório
	 *       1 - Maior tamanho permitido da string ou maior número permitido. Obrigatório
	 *     ]
	 *     Pode ser informado apenas um integer que representa o tamanho fixo do campo.
	 *   'regex' => Expressão regular PHP aceita pela função preg_match. Opcional. Somente
	 *     será utilizado para tipos numéricos e string. Se a expressão não der nenhum match,
	 *     vai lançar erro de formato inválido para o valor, senão, vai considerar válido
	 *   'required' => Booleano indicando se o campo é obrigatório. Opcional. Se não informado,
	 *     o campo não será considerado obrigatório
	 *   'requiredExpr' => Expressão PHP que deve retornar um booleano que vai ser setar o parâmetro de "required".
	 *     Esta expressão pode utilizar a variável $data, que é o array de dados passado para validação.
	 *   'empty' => Booleano indicando se o campo pode ser vazio (nulo, string ou array vazio). Opcional. Se não informado,
	 *     o campo poderá ser vazio
	 *   'requireFilled' => Booleano, atalho para indicar que é obrigatório e se pode ser vazio ou não. 
	 *     Quando for true, significa required => true e empty => false e quando false significa required => true e empty => true
	 *   'choiceGroup' => Array com nome e opção para um grupo de escolha. Tem situações em que deve ser informado um campo ou outro.
	 *     Para resolver essa situação, basta colocar os campos no mesmo grupo de escolha
	 *     O array é de duas posições obrigatórias, sendo a primeira o nome do grupo de escolha e a segunda o número da opção,
	 *     e uma terceira posição opcional que é um booleano representando se o grupo de escolhas é obrigatório.
	 *     Por padrão, todos os grupos de escolha são obrigatórios, então para quando ele for opcional, deve ser
	 *     passado o valor false na terceira posição, apenas no primeiro campo que tem o grupo configurado, pois nos demais
	 *     essa informação será ignorada.
	 *     Para exemplificar, vamos considerar que temos 3 possibilidades de escolha:
	 *       1. Enviar campo id;
	 *       2. Enviar campo cpf;
	 *       3. enviar campo rg, uf e opcionalmente a data de emissão;
	 *     Para isso teríamos uma configuração assim:
	 *     [
	 *       'id' => ['type' => Validator::INTEGER, 'choiceGroup' => ['ident', 1], 'requireFilled' => true],
	 *       'cpf' => ['type' => Validator::INTEGER, 'choiceGroup' => ['ident', 2], 'requireFilled' => true],
	 *       'rg' => ['type' => Validator::INTEGER, 'choiceGroup' => ['ident', 3], 'requireFilled' => true],
	 *       'uf' => ['type' => Validator::STRING, 'choiceGroup' => ['ident', 3], 'requireFilled' => true],
	 *       'emissao' => ['type' => Validator::STRING, 'choiceGroup' => ['ident', 3]],
	 *     ]
	 *     Repare que a regra de obrigatoriedade (requireFillded) somente é validada se o grupo foi enviado. O validador
	 *     vai identificar qual o grupo foi enviado e fazer a validação de required/empty somente para os
	 *     elementos do grupo.
	 *     Obs 1: se o valor passado para choiceGroup não for um array ou não tiver SOMENTE as informações necessárias, 
	 *     vai simplesmente ignorar essa configuração
	 *     Obs 2: para tornar o grupo opcional, utilizando o mesmo exemplo acima, bastaria mudar a configuração
	 *     do primeiro campo, para ficar assim:
	 *       'id' => ['type' => Validator::INTEGER, 'choiceGroup' => ['ident', 1, false], 'requireFilled' => true],
	 *   'custom' => Callable que deve retornar void. Em caso de erro deve lançar DefaultException.
	 *     Os parâmetros enviados para o callable são:
	 *       1. string $field (nome do campo sendo validado)
	 *       2. array $data (dados a serem validados)
	 *       3. array $rule (array com as informações da regra sendo validada)
	 *     Isso permite criar seus próprios parâmetros nas regras de configuração, pois
	 *     eles poderão ser acessados via o parâmetro $rule
	 *   'label' - Label para identificar o campo. Opcional. Se não informado, o label
	 *     será o nome do campo (índice do array)
	 *   'rules' => Pode ser informado para as regras que são do tipo objeto ou array.
	 *     Este atributo é na verdade um outro array de $rules para validar o objeto/array
	 *     Obs: se campo for um array múltiplo, esse atributo irá se aplicar a cada elemento
	 *     do array
	 *   'of' => Pode ser informado apenas para as regras que são to tipo array. Se informado,
	 *     significa que é um array com múltiplos elementos. O valor deste atributo deve ser o
	 *     tipo de dados de cada elemento do array
	 * ]
	 * @param string $parentAttr Esse parâmetro é apenas para uso interno no método. Não
	 *   utilize. Utilizado para as validações de objetos/arrays
	 * @return string[] Retorna os erros encontrados durante a validação. Se estiver tudo
	 * válido, então retornar um array vazio
	 */
	public function validate(array $data, array $rules, string $parentAttr = '') {
		if (empty($parentAttr)) {
			$this->_resetErrors();
		} else {
			$parentAttr .= '.';
		}
		// Validação dos choice groups
		$choiceGroups = []; // O índice vai ser o nome do grupo e o valor será um array com informações da opção selecionada. Inicialmente seto o valor com null
		foreach ($rules as $attr => $rule) {
			if (!isset($rule['label'])) { // Label defaults to the field name
				$rules[$attr]['label'] = $parentAttr.$attr;
			}
			if (isset($rule['choiceGroup'])) {
				$groupId = $rule['choiceGroup'][0];
				if (!array_key_exists($groupId, $choiceGroups)) {
					$choiceGroups[$groupId] = [
						'chosen' => null, 'first_field_chosen' => null,
						'required' => $rule['choiceGroup'][2] ?? true // Por padrão é obrigatório
					];
				}
				if (array_key_exists($attr, $data)) { // Se o campo foi informado, significa que o grupo ao qual pertence foi selecionado
					if ($choiceGroups[$groupId]['chosen'] === null) {
						$choiceGroups[$groupId]['chosen'] = $rule['choiceGroup'][1];
						$choiceGroups[$groupId]['first_field_chosen'] = $attr;
					} elseif ($choiceGroups[$groupId]['chosen'] !== $rule['choiceGroup'][1]) { // Enviou campos de ambos os grupos
						$this->_addError(
							$parentAttr.$attr,
							new DefaultException (
								$this->_prefix.'validator.multiple_options_chosen',
								['%label%' => $rules[$attr]['label'], '%group%' => $groupId]
							)
						);
					}
				}
			}
		}
		foreach ($choiceGroups as $groupId => $groupInfo) {
			if ($groupInfo['chosen'] === null && $groupInfo['required']) {
				$this->_addError(
					$parentAttr.'_choice_group_'.$groupId,
					new DefaultException (
						$this->_prefix.'validator.no_option_chosen',
						['%group%' => $groupId]
					)
				);
			}
		}
		// Validação dos campos
		foreach ($rules as $attr => $rule) {
			if (isset($rule['choiceGroup']) && $choiceGroups[$rule['choiceGroup'][0]]['chosen'] !== $rule['choiceGroup'][1]) {
				continue; // Campo está em um grupo de escolha que não foi escolhido
			}
			try {
				$attrInd = '';
				if (isset($rule['requiredExpr'])) {
					$rule['required'] = eval($rule['requiredExpr']);
				}
				// empty e required sempre prevalecem sobre requireFilled
				if ((isset($rule['required']) || isset($rule['empty'])) && isset($rule['requireFilled'])) {
					unset($rule['requireFilled']);
				}
				$isRequired = (isset($rule['required']) && $rule['required']) 
					|| isset($rule['requireFilled']);
				$isInformed = array_key_exists($attr, $data);
				if ($isRequired && !$isInformed) {
					throw new DefaultException (
						$this->_prefix.'validator.missing_required_info',
						['%label%' => $rule['label']]
					);
				}
				
				$canBeEmpty = (!isset($rule['requireFilled']) && !isset($rule['empty']))
					|| (isset($rule['requireFilled']) && !$rule['requireFilled'])
					|| (isset($rule['empty']) && $rule['empty']);
				$isEmpty = !isset($data[$attr]) || $data[$attr] === '' || $data[$attr] === [];
				if ($isInformed && !$canBeEmpty && $isEmpty) {
					throw new DefaultException (
						$this->_prefix.'validator.empty_info',
						['%label%' => $rule['label']]
					);
				}
				// Mais nada para validar. Se não é obrigatório, então está OK
				if ($isEmpty) {
					continue;
				}
				
				$isNumeric = in_array($rule['type'], array(self::INTEGER, self::FLOAT));
				$isString = $rule['type'] === self::STRING;
				$isArrayMulti = $rule['type'] === self::ARRAY && array_key_exists('of', $rule);
				$isStruct = in_array($rule[($isArrayMulti ? 'of' : 'type')], array(self::OBJECT, self::ARRAY));
				
				$this->_validateType($rule['label'], $data[$attr], $rule['type']);
				if ($isArrayMulti) {
					foreach ($data[$attr] as $ind => $val) {
						$attrInd = "[{$ind}]"; // Validações de cada elemento do atributo
						$this->_validateType($rule['label'].$attrInd, $val, $rule['of'], false);
					}
					$attrInd = ''; // A partir daqui as validações já não são mais referentes aos elementos do atributo
				}
				if (isset($rule['length']) && ($isNumeric || $isString || $isArrayMulti)) {
					if (!is_array($rule['length'])) {
						$rule['length'] = [$rule['length'], $rule['length']];
					}
					$this->_validateLength($rule['label'], $data[$attr], $rule['length'], $rule['type']);
				}
				if (isset($rule['regex']) && ($isNumeric || $isString)) {
					$this->_validateRegex($rule['label'], (string)$data[$attr], $rule['regex']);
				}
				if (isset($rule['custom']) && $rule['custom']) {
					if (!is_callable($rule['custom'])) {
						throw new DefaultException(
							'internal.validator.non_callable_custom', ['%label%' => $rule['label']]
						);
					}
					call_user_func($rule['custom'], $attr, $data, $rule);
				}
				if ($isStruct && isset($rule['rules']) && $rule['rules']) {
					if ($isArrayMulti) {
						foreach ($data[$attr] as $ind => $val) {
							$attrInd = "[{$ind}]"; // Validações de cada elemento do atributo
							$this->validate((array)$val, $rule['rules'], $parentAttr.$attr.$attrInd);
						}
						$attrInd = ''; // A partir daqui as validações já não são mais referentes aos elementos do atributo
					} else {
						$this->validate((array)$data[$attr], $rule['rules'], $parentAttr.$attr);
					}
				}
			} catch (DefaultException $de) {
				$this->_addError($parentAttr.$attr.$attrInd, $de);
			} catch (\Exception $e) { // Exceções inesperadas
				$this->_addError($parentAttr.$attr.$attrInd, new DefaultException('internal.unknown_internal_error', array('%message%' => $e->getMessage())));
			}
		}
		return $this->getErrors();
	}
	
	/**
	 * Valida se a REGEX informada bate com o valor informado
	 * @param string $label Nome do que está sendo validado
	 * @param mixed $val Valor a ser validado
	 * @param string $regex REGEX PHP
	 * @throws DefaultException
	 */
	private function _validateRegex(string $label, $val, string $regex): void {
		$res = preg_match($regex, $val);
		if ($res === false) {
			throw new DefaultException ('internal.validator.invalid_regex', ['%label%' => $label, '%regex%' => $regex]);
		} elseif ($res === 0) {
			throw new DefaultException ($this->_prefix.'validator.invalid_format', ['%label%' => $label]);
		}
	}
	
	/**
	 * Valida o tamanho da string ou os valores da faixa se for número
	 * @param string $label Nome do que está sendo validado
	 * @param mixed $val Valor a ser validado
	 * @param array $lengthRule
	 *   Posição 0 => menor número de caracteres ou menor número
	 *   Posição 1 => maior número de caracteres ou maior número
	 * @param string $type Tipo de dados do elemento
	 * ou como uma string
	 * @throws DefaultException
	 */
	private function _validateLength(string $label, $val, array $lengthRule, string $type): void {
		$isNumeric = $isValid = false;
		if (in_array($type, [self::INTEGER, self::FLOAT])) {
			$len = $val;
			$isNumeric = true;
		} elseif ($type == self::STRING) {
			$len = Utils::length($val);
		} elseif ($type == self::ARRAY) {
			$len = count($val);
		}
		$isValid = ($len >= $lengthRule[0] && $len <= $lengthRule[1]);
		if (!$isValid) {
			throw new DefaultException (
				$this->_prefix.'validator.invalid_'.($isNumeric ? 'numeric' : 'string').'_length',
				['%label%' => $label, '%min%' => $lengthRule[0], '%max%' => $lengthRule[1]]
			);
		}
	}
	
	/**
	 * Valida se a variável contém um valor que esteja de acordo com o tipo esperado
	 * @param string $label Nome do que está sendo validado
	 * @param mixed $val Valor a ser validado
	 * @param string $type Utilizar as constantes definidas na classe
	 * @param bool $allowNull Indica se aceita o valor null ou não
	 * @throws DefaultException
	 */
	private function _validateType(string $label, $val, string $type, bool $allowNull = true): void {
		if ($val === null && $allowNull) { // null pode ser atribuído a qualquer tipo
			return;
		}
		$isValid = false;
		switch ($type) {
			case self::STRING:
				$isValid = is_string($val);
				break;
			case self::INTEGER:
				$isValid = is_int($val) || (is_float($val) && (intval($val) == $val));
				break;
			case self::BOOLEAN:
				$isValid = is_bool($val);
				break;
			case self::FLOAT:
				$isValid = is_float($val) || is_int($val);
				break;
			case self::OBJECT:
				$isValid = is_object($val);
				break;
			case self::ARRAY:
				$isValid = is_array($val);
				break;
			default:
				throw new DefaultException (
					'internal.validator.unsupported_type',
					['%label%' => $label, '%type%' => $type]
				);
		}
		if (!$isValid) {
			throw new DefaultException (
				$this->_prefix.'validator.wrong_type',
				['%label%' => $label, '%get%' => gettype($val), '%expected%' => $type]
			);
		}
	}
}