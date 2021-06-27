<?php
namespace App\Utils\CustomValidator;

use App\Exception\DefaultException;
use App\Utils\Validator;

/**
 * Classe com métodos para validações mais comumente utilizadas
 * em entidades
 */
class EntityValidator {
	private static function _prefix($rule): string {
		return $rule['_prefix'] ?? 'entity.';
	}
	
	public static function validateEnum($attr, $data, $rule) {
		if (!in_array($data[$attr], $rule['enum'])) {
			throw new DefaultException(self::_prefix($rule).'validator.invalid_format', ['%label%' => $rule['label']]);
		}
	}
}