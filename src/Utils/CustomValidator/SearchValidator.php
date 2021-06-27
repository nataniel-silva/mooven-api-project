<?php
namespace App\Utils\CustomValidator;

use App\Exception\DefaultException;
use App\Utils\Validator;
use App\Utils\Utils;

/**
 * Classe com métodos para validação de parâmetros recebidos em requisições
 * de busca de dados na API
 */
class SearchValidator {
	/**
	 * Prefixo para os ids das mensagens de erros encontradas
	 * @var string
	 */
	private $_prefix = '';
	
	public function __construct(string $prefix = '') {
		$this->_prefix = $prefix;
	}
	
	public function validateEnum($attr, $data, $rule) {
		$vals = $data[$attr];
		if (empty($vals)) {
			return;
		}
		if (isset($rule['list']) && $rule['list']) {
			$vals = explode(',', $vals);
			if (!$vals) {
				throw new DefaultException($this->_prefix.'validator.invalid_format');
			}
		} else {
			$vals = [$vals];
		}
		foreach ($vals as $val) {
			if (!in_array($val, $rule['enum'])) {
				throw new DefaultException($this->_prefix.'validator.invalid_format');
			}
		}
	}
	
	public function validateString($attr, $data, $rule) {
		$vals = $data[$attr];
		if (empty($vals)) {
			return;
		}
		if (isset($rule['list']) && $rule['list']) {
			$vals = str_getcsv($vals, ',', '"');
			if (!$vals) {
				throw new DefaultException($this->_prefix.'validator.invalid_format');
			}
		} else {
			$vals = [$vals];
		}
		
		
		foreach ($vals as $val) {
			if (
				(substr($val, 0, 1) == '%' && !$rule['wildcard'][0])
				|| (substr($val, -1, 1) == '%' && !$rule['wildcard'][1])
			) {
				throw new DefaultException($this->_prefix.'validator.invalid_format');
			}
		}
	}
	
	public function validateInteger($attr, $data, $rule) {
		$this->_validateIntFloatDate($attr, $data, $rule);
	}
	
	public function validateFloat($attr, $data, $rule) {
		$this->_validateIntFloatDate($attr, $data, $rule);
	}
	
	public function validateDate($attr, $data, $rule) {
		$this->_validateIntFloatDate($attr, $data, $rule);
	}
	
	private function _validateIntFloatDate($attr, $data, $rule) {
		$vals = $data[$attr];
		if (empty($vals)) {
			return;
		}
		if (isset($rule['list']) && $rule['list']) {
			$vals = explode(',', $vals);
			if (!$vals) {
				throw new DefaultException($this->_prefix.'validator.invalid_format');
			}
		} else {
			$vals = [$vals];
		}
		
		$fnName = $rule['_type'] == Validator::INTEGER ? 'isIntStr'
			: ($rule['_type'] == Validator::FLOAT ? 'isFloatStr' : 'isDateStr');
		$dateFmt = $rule['dateFmt'] ?? 'Y-m-d';
			
		foreach ($vals as $val) {
			if (isset($rule['range']) && $rule['range'] && strpos($val, '|') !== false) {
				$n = explode('|', $val);
				if (
					count($n) != 2
					|| (
						($fnName == 'isDateStr' && (!Utils::{$fnName}($n[0], $dateFmt) || !Utils::{$fnName}($n[1], $dateFmt)))
						|| ($fnName != 'isDateStr' && (!Utils::{$fnName}($n[0]) || !Utils::{$fnName}($n[1])))
					)
				){
					throw new DefaultException($this->_prefix.'validator.invalid_format');
				}
				/*
				if (intval($n[0]) >= intval($n[1])) {
					throw new DefaultException($this->_prefix.'validator.invalid_range');
				}
				*/
				continue;
			}
			
			$firstAndSecondChar = substr($val, 0, 2);
			$firstChar = substr($val, 0, 1);
			if (in_array($firstAndSecondChar, ['>=', '<='])) {
				if (($firstAndSecondChar == '<=' && !$rule['lt']) || ($firstAndSecondChar == '>=' && !$rule['gt'])) {
					throw new DefaultException($this->_prefix.'validator.invalid_format');
				}
				$val = substr($val, 2);
			} elseif ($firstChar === '!') {
				if (!$rule['negation']) {
					throw new DefaultException($this->_prefix.'validator.invalid_format');
				}
				$val = substr($val, 1);
			} elseif (in_array($firstChar, ['<', '>'])) {
				if (($firstChar == '<' && !$rule['lt']) || ($firstChar == '>' && !$rule['gt'])) {
					throw new DefaultException($this->_prefix.'validator.invalid_format');
				}
				$val = substr($val, 1);
			}
			
			if ($fnName == 'isDateStr') {
				$ok = Utils::{$fnName}($val, $dateFmt);
			} else {
				$ok = Utils::{$fnName}($val);
			}
			if (!$ok) {
				throw new DefaultException($this->_prefix.'validator.invalid_format');
			}
		}
	}
	
	public function validateOrderBy($attr, $data, $rule) {
		if (empty($data[$attr])) {
			return;
		}
		$vals = explode(',', $data[$attr]);
		if (!$vals) {
			throw new DefaultException($this->_prefix.'validator.invalid_format');
		}
		foreach ($vals as $val) {
			$o = explode('|', $val);
			$q = count($o);
			if ($q == 0 || $q > 2) {
				throw new DefaultException($this->_prefix.'validator.invalid_format');
			}
			if (!in_array($o[0], $rule['columns'])) {
				throw new DefaultException($this->_prefix.'validator.invalid_format');
			}
			if ($q == 2 && !in_array(Utils::upper($o[1]), ['ASC', 'DESC'])) {
				throw new DefaultException($this->_prefix.'validator.invalid_format');
			}
		}
	}
	
}