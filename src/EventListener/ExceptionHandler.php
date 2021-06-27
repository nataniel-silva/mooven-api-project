<?php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use App\Exception\ApiException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Exception\BOException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Exception\DefaultException;

/**
 *
 * Listener para tratar as exceptions lançadas no sistema
 *
 */
class ExceptionHandler {

	public function onKernelException(ExceptionEvent $event) {
		$e = $event->getThrowable();
		if ($e instanceof BOException) {
			$e = new ApiException(Response::HTTP_UNPROCESSABLE_ENTITY, $e);
		} elseif ($e instanceof DefaultException) {
			$httpStatus = substr($e->getCode(), 0, 9) == 'internal.' ?
				Response::HTTP_INTERNAL_SERVER_ERROR : Response::HTTP_UNPROCESSABLE_ENTITY;
			$boe = new BOException($e->getCode(), [], [], $e->getMainError());
			$boe->setMessage($e->getMessage()); // Preciso fazer isso porque caso tenha parâmetros de tradução na mensagem original, eles eu não tenho mais neste ponto
			$e = new ApiException(Response::HTTP_UNPROCESSABLE_ENTITY, $boe);
		} elseif (!($e instanceof ApiException)) {
			$httpStatus = Response::HTTP_INTERNAL_SERVER_ERROR;
			$errorId = 'api.internal_server_error';
			if ($e instanceof MethodNotAllowedHttpException) {
				$httpStatus = Response::HTTP_METHOD_NOT_ALLOWED;
				$errorId = 'api.method_not_allowed';
			} elseif ($e instanceof NotFoundHttpException) {
				$httpStatus = Response::HTTP_NOT_FOUND;
				$errorId = 'api.not_found';
			}
			$e = new ApiException($httpStatus, new BOException($errorId, [], [], $e));
		}
//		 $event->setResponse(new JsonResponse($e->toArray(), $e->getStatusCode()));
	}
}
