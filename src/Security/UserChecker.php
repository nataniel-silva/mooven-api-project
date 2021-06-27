<?php
namespace App\Security;

use App\Entity\Common\User;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Exception\DefaultException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Exception\LoginException;
use Symfony\Component\Config\Definition\Exception\Exception;

class UserChecker implements UserCheckerInterface {
	/**
	 * 
	 * @var SessionInterface
	 */
	private $_session;
	
	public function __construct(SessionInterface $session) {
		$this->_session = $session;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Security\Core\User\UserCheckerInterface::checkPreAuth()
	 * @param User $user
	 */
	public function checkPreAuth(UserInterface $user) {
		if (!$user instanceof User) {
			return;
		}
		if (!$user->getActive()) {
			throw new LoginException(new DefaultException('login.inactive_user'));
		}
		if ($user->getUuidIdp() != $this->_session->get('_lastSsoUuid')) {
			throw new LoginException(new DefaultException('login.unexpected_user'));
		}
		if (!$user->getIdCompanyGroup()->getActive()) {
			throw new LoginException(new DefaultException('login.sso_user_not_allowed'));
		}
		if ($user->getEmail() != $this->_session->get('_lastSsoEmail')) { // Preciso atualizar o email do usuário, pois foi alterado no IDP
			try {
				$user->setEmail($this->_session->get('_lastSsoEmail'));
				$user->getEntityManager()->persist($entity);
				$user->getEntityManager()->flush();
			} catch (\Exception $e) {
				throw new LoginException(
					new DefaultException('login.user_email_update_fail', ['%msg%' => $e->getMessage()])
				);
			}
		}
	}
	
	public function checkPostAuth(UserInterface $user) {
		if (!$user instanceof User) {
			return;
		}
		$this->_session->set('_userMustBeLoggedIn', true);
	}
}