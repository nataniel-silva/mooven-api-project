<?php
namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use App\Exception\ApiException;
use App\Exception\BOException;
use App\Utils\Utils;

/**
 *
 * Listener para processar o corpo da requisição, caso venha em algum formato diferente
 * de HTML
 *
 */
class RequestBodyHandler {
	public function onKernelController(FilterControllerEvent $event) {
		$request = $event->getRequest();
		if (!$request->getContent()) {
			return;
		}
		switch ($request->getContentType()) {
			case 'json':
				$data = (array) json_decode($request->getContent());
				if (json_last_error() !== JSON_ERROR_NONE) {
					throw new ApiException(Response::HTTP_BAD_REQUEST, new BOException('request.invalid_json_body'));
				}
				$request->request->replace(is_array($data) ? $data : array());
				break;
			case 'xml':
				$data = Utils::xmlToArray($request->getContent());
				$request->request->replace(is_array($data) ? $data : array());
				break;
		}
	}
}
