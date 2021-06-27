<?php
namespace App\Security;

use LightSaml\Model\Protocol\Response;
use LightSaml\SpBundle\Security\User\UsernameMapperInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Esta classe faz o mapeamento de qual atributo da resposta SAML
 * equivale ao username do usuário que está logado
 *
 */
class SamlUsernameMapper implements UsernameMapperInterface {
	/**
	 *
	 * @var SessionInterface
	 */
	private $_session;
	
	public function __construct(SessionInterface $session) {
		$this->_session = $session;
	}
	
	public function getUsername(Response $response) {
		switch ($response->getIssuer()->getValue()) {
			case 'IDP_TESTE': // Retorna o atributo e-mail
			case 'IDP_TESTE_DEV':
			case 'https://accounts.esales.com.br':
				//dump($response->getFirstAssertion()->getFirstAttributeStatement());exit;
				$email = $response->getFirstAssertion()->getFirstAttributeStatement()
					->getFirstAttributeByName('email')->getFirstAttributeValue();
				$uuid = $response->getFirstAssertion()->getFirstAttributeStatement()
					->getFirstAttributeByName('uuid')->getFirstAttributeValue();
				break;
				
			default: // Retorna o primeiro atributo
				$email = $response->getFirstAssertion()->getFirstAttributeStatement()
					->getFirstAttributeByName(null)->getFirstAttributeValue();
				$uuid = $response->getFirstAssertion()->getFirstAttributeStatement()
					->getFirstAttributeByName('uuid')->getFirstAttributeValue();
				
		}
		$this->_session->set('_lastInResponseTo', $response->getInResponseTo()); // Se passou por aqui, agora este é o ID mais atual
		$this->_session->set('_lastSsoEmail', $email);
		$this->_session->set('_lastSsoUuid', $uuid);
		return $uuid;
	}
}