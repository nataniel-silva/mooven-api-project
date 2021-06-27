<?php
namespace App\Utils\CustomValidator;

use App\Exception\DefaultException;
use App\Utils\Validator;
use App\Utils\Utils;

/**
 * Classe com métodos para validação mais comumente utilizadas
 */
class CommonValidator {
	/**
	 * Prefixo para os ids das mensagens de erros encontradas
	 * @var string
	 */
	private $_prefix = '';
	
	/**
	 * @param string $prefix Prefixo dos códigos de erro
	 */
	public function __construct(string $prefix = 'request.') {
		$this->_prefix = $prefix;
	}

	public function validateDate($attr, $data, $rule) {
		if (empty($data[$attr])) {
			return;
		}
		$fmt = $rule['dateFmt'] ?? 'Y-m-d';
		if (!Utils::isDateStr($data[$attr], $fmt)) {
			throw new DefaultException($this->_prefix.'validator.invalid_format');
		}
	}
	
	public function validateEnum($attr, $data, $rule) {
		if (empty($data[$attr])) {
			return;
		}
		if (!in_array($data[$attr], $rule['enum'])) {
			throw new DefaultException($this->_prefix.'validator.invalid_format');
		}
	}
}