<?php
namespace App\Security;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\LogoutException;
use App\Controller\SamlController;
use App\Business\LoginBO;
use Psr\Container\ContainerInterface;

class LogoutHandler implements LogoutSuccessHandlerInterface {

	public function onLogoutSuccess(Request $request) {
		return [];
	}
}