<?php
namespace App\Exception;

use App\Utils\Singleton;

/**
 * Classe de Exception que deve ser lançada por objetos de negócio
 *
 */
final class BOException extends DefaultException {
	/**
	 * @var DefaultException[]
	 */
	protected $subErrors;
	
	/**
	 * 
	 * @param string $errorId
	 * @param array $transParams Parâmetros utilizados na tradução de mensagens
	 * @param array DefaultException[]
	 * @param \Throwable $mainError Throwable de um erro principal, caso seja necessária.
	 * Sua principal utilidade é para obter mais informações a respeito do erro. É como
	 * se fosse o previous exception
	 */
	public function __construct(string $errorId, array $transParams = [], array $subErrors = [], ?\Throwable $mainError = null) {
		$this->subErrors = $subErrors;
		$this->setMainError($mainError);
		parent::__construct($errorId, $transParams);
	}
	
	public function getSubErrors(): array {
		return $this->subErrors;
	}
	
	/**
	 * Retorna as mensagens dos sub erros concatenadas
	 * @return string
	 */
	public function getSubErrorsMessage(): string {
		$messages = [];
		foreach ($this->subErrors as $e) {
			$messages[] = $e->getMessage();
		}
		return implode('; ', $messages);
	}
	
	public function toArray() {
		if ($this->getCode()) {
			$a = parent::toArray();
		} else {
			$e = new DefaultException('api.internal_server_error');
			$a = $e->toArray();
		}
		return $a;
	}
}