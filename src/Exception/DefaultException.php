<?php
namespace App\Exception;

use App\Utils\TranslatorDomain;
use App\Utils\Singleton;
use Symfony\Component\HttpFoundation\Response;

/**
 * Classe de Exception que deve ser utilizada no projeto
 *
 */
class DefaultException extends \RuntimeException implements DefaultExceptionInterface {
	protected $code;
	/**
	 * @var \Throwable
	 */
	protected $mainError = null;
	
	public function __construct(string $msgId, array $transParams = [], string $domain = TranslatorDomain::ERROR) {
		$msg = Singleton::getTranslator()->trans($msgId, $transParams, $domain);
		$this->code = $msgId;
		parent::__construct($msg);
	}
	
	public function toArray() {
		$a = [
			'code' => $this->getCode(),
			'message' => $this->getMessage(),
		];
		if (Singleton::getAppEnv() != 'PROD' && $this->getDebugMessage()) {
			$a['debug'] = $this->getDebugMessage();
		}
		return $a;
	}
	
	public function setMessage(string $msg) {
		$this->message = $msg;
	}
	
	/**
	 * @param \Throwable $error
	 */
	public function setMainError(?\Throwable $error): void {
		$this->mainError = $error;
	}
	
	/**
	 * @return \Throwable
	 */
	public function getMainError(): ?\Throwable {
		return $this->mainError;
	}
	
	public function getDebugMessage(): string {
		if ($this->getMainError()) {
			return $this->getMainError()->getMessage();
		}
		return '';
	}
	
	/**
	 * Usar para lançar erro. Evitar lançar exception diretamente no código
	 * @param string $errorId
	 * @param array $transParams Parâmetros para tradução
	 * @param \Throwable $mainError Throwable do erro para obter maiores detalhes,
	 * @throws \Throwable
	 */
	public static function throwBOError(string $errorId, ?\Throwable $mainError = null, array $transParams = []) {
		if ($mainError instanceof BOException || $mainError instanceof ApiException) {
			throw $mainError;
		}
		throw new BOException($errorId, $transParams, [], $mainError);
	}
	
	/**
	 * Usar para lançar erro de ação não permitida para o usuário logado
	 * @param string $errorId
	 * @param array $transParams
	 * @param string $title Código da mensagem de erro explicativa (somente quando precisar dar mais informações para o usuário sobre o ocorrido)
	 * @throws ApiException
	 */
	public static function throwForbiddenError(string $errorId, array $transParams = [], $title = '') {
		throw new ApiException(Response::HTTP_FORBIDDEN, new BOException($errorId, $transParams), $title);
	}
	
}