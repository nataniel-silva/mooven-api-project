<?php
namespace App\Exception;

use App\Utils\Singleton;
use App\Utils\TranslatorDomain;
use LightSaml\Model\Protocol\Status;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Classe de Exception que deve ser utilizada para armazenar
 * os erros encontrados durante a requisição. Ela SEMPRE deve
 * ser usada para gerar o JSON de retorno de erros 
 *
 */
class ApiException extends \RuntimeException implements DefaultExceptionInterface, HttpExceptionInterface {
	/**
	 * 
	 * @var BOException
	 */
	private $_error;
	/**
	 * 
	 * @var int
	 */
	private $_statusCode;
	/**
	 * 
	 * @var string
	 */
	private $_title;
	/**
	 * 
	 * @var string
	 */
	private $_type;
	
	// Implementação da interface
	public function getHeaders(): array {
		return [];
	}
	
	/**
	 * 
	 * @param int $statusCode Código HTTP do erro
	 * @param BOException $error
	 * @param string $title Título do erro. Algo que ajude o entendimento do erro
	 */
	public function __construct(int $statusCode, BOException $error, string $title = '') {
		$this->_statusCode = $statusCode;
		$this->_error = $error;
		switch ($statusCode) {
			case Response::HTTP_BAD_REQUEST:
				$this->_type = 'bad_request';
				break;
			case Response::HTTP_UNAUTHORIZED:
				$this->_type = 'unauthorized';
				break;
			case Response::HTTP_FORBIDDEN:
				$this->_type = 'forbidden';
				break;
			case Response::HTTP_NOT_FOUND:
				$this->_type = 'not_found';
				break;
			case Response::HTTP_METHOD_NOT_ALLOWED:
				$this->_type = 'method_not_allowed';
				break;
			case Response::HTTP_UNPROCESSABLE_ENTITY:
				$this->_type = 'unprocessable_entity';
				break;
			case Response::HTTP_INTERNAL_SERVER_ERROR:
				$this->_type = 'internal_server_error';
				break;
			default:
				$this->_type = (string)$statusCode;
		}
		if (!$title && $this->_type != (string)$this->_statusCode) {
			$title = 'api.'.$this->_type;
		}
		$this->_title = Singleton::getTranslator()->trans($title, [], TranslatorDomain::ERROR);
		$this->message = $this->_title;
	}
	
	public function getStatusCode(): int {
		return $this->_statusCode;
	}
	
	public function toArray(): array {
		$ret = [
			'status' => $this->_statusCode,
			'type' => $this->_type,
			'title' => $this->_title,
			'errors' => []
		];
		$ret['errors']['main'] = $this->_error->toArray();
		foreach ($this->_error->getSubErrors() as $attr => $error) {
			if (is_int($attr)) {
				$attr = 'undefined';
			}
			
			if ($error instanceof DefaultException) {
				$errorInfo = $error->toArray();
			} else { // A aplicação deve tentar evitar ao máximo de acontecer isso...
				$e = new DefaultException('internal.unknown_internal_error');
				$errorInfo = $e->toArray();
				if (Singleton::getAppEnv() != 'PROD') {
					if ($error instanceof \Exception) {
						$errorInfo['debug'] = $e->getMessage();
					} elseif (is_scalar($error) || is_object($error)) {
						$errorInfo['debug'] = is_scalar($error) ? $error : $error->__toString();
					} else { // WTF?!
						$errorInfo['debug'] = 'undefined';
					}
				}
			}
			$ret['errors'][$attr] = $errorInfo;
		}
		return $ret;
	}
}