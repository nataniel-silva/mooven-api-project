<?php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 * Listener para tratar o problema de CORS ao acessar a API via navegador
 * (Cross-Origin Resource Sharing)
 *
 */
class CORSHandler {
	public function onKernelResponse(FilterResponseEvent $event) {
		$response = $event->getResponse();
		$responseHeaders = $response->headers;
		$responseHeaders->set('Access-Control-Allow-Headers', 'origin, content-type, accept, authorization, x-date');
		$responseHeaders->set('Access-Control-Allow-Origin', $event->getRequest()->headers->get('Origin'));
		$responseHeaders->set('Access-Control-Allow-Credentials', 'true');
		$responseHeaders->set('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, OPTIONS');
		$responseHeaders->set('Access-Control-Expose-Headers', 'Content-Disposition');
		if ($event->getRequest()->getMethod() == 'OPTIONS') { // Requisição realizada apenas para verificar se o servidor vai aceitar a requisição real
			return $response->setContent('')->setStatusCode(Response::HTTP_OK);
		}
	}
}
