<?php
namespace App\Utils;

use App\Entity\EntityFactory;
use App\Entity\Security\AuthToken;
use App\Entity\Common\CompanyGroup;
use App\Entity\Common\User;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\Premium\Plan;
use App\Entity\Common\FileProc;

/**
 * Singleton para acesso a recursos comumente usados em vários lugares
 * da aplicação
 * @author tonyfarney
 *
 */
class Singleton {
	private static $_resources = [];
	
	/**
	 * Seta um recurso para poder ser acessado globalmente via este singleton
	 * @param string $resourceName Nome do recurso
	 * @param mixed $resource O recurso em si
	 */
	public static function set(string $resourceName, $resource): void {
		self::$_resources[$resourceName] = $resource;
	}
	
	/**
	 * Retorna o recurso ou NULL se não estiver disponível
	 * @param string $resourceName Nome do recurso
	 * @return NULL|mixed
	 */
	public static function get(string $resourceName) {
		return isset(self::$_resources[$resourceName]) ? self::$_resources[$resourceName] : null;
	}
	
	public static function setAuthToken(AuthToken $token): void {
		self::set('authToken', $token);
		self::set('authUser', $token->getIdUser());
		self::set('authCompanyGroup', $token->getIdUser()->getIdCompanyGroup());
		self::set('authPlan', $token->getIdUser()->getIdCompanyGroup()->getIdPlan());
	}
	public static function setAuthInfoByFileProc(FileProc $fileProc): void {
		self::set('authUser', $fileProc->getIdUser());
		self::set('authCompanyGroup', $fileProc->getIdCompanyGroup());
	}
	public static function getAuthToken(): ?AuthToken {
		return self::get('authToken');
	}
	public static function getAuthUser(): ?User {
		return self::get('authUser');
	}
	public static function getAuthCompanyGroup(): ?CompanyGroup {
		return self::get('authCompanyGroup');
	}
	public static function getAuthPlan(): ?Plan {
		return self::get('authPlan');
	}
	
	public static function setEnv(string $env): void {
		self::set('env', $env);
	}
	public static function getEnv(): string {
		return self::get('env');
	}
	
	public static function setTranslator(TranslatorInterface $translator): void {
		self::set('translator', $translator);
	}
	public static function getTranslator(): TranslatorInterface {
		return self::get('translator');
	}
	
	public static function setAppEnv(string $appEnv): void {
		self::set('appEnv', $appEnv);
	}
	/**
	 * Utilizar esse método para saber se é produção. Quando for produção, retornará "PROD"
	 * @return string
	 */
	public static function getAppEnv():? string {
		return self::get('appEnv');
	}
	
	public static function setBaseUrl(string $baseUrl): void {
		self::set('baseUrl', $baseUrl);
	}
	public static function getBaseUrl(): string {
		return self::get('baseUrl');
	}
	
	/**
	 * Email para o qual deve ser enviado quando ocorrer algum erro inesperado no sistema
	 * @param string $errorEmail
	 */
	public static function setErrorEmail(string $errorEmail): void {
		self::set('errorEmail', $errorEmail);
	}
	public static function getErrorEmail(): string {
		return self::get('errorEmail');
	}
	
	public static function setSharedBaseUrl(string $sharedBaseUrl): void {
		self::set('sharedBaseUrl', $sharedBaseUrl);
	}
	public static function getSharedBaseUrl(): string {
		return self::get('sharedBaseUrl');
	}
	
	public static function setEntityManagerNameToUseInsteadDefault(?string $name): void {
		self::set('entityManagerNameToUseInsteadDefault', $name);
	}
	public static function getEntityManagerNameToUseInsteadDefault(): ?string {
		return self::get('entityManagerNameToUseInsteadDefault');
	}
	
	public static function setEntityFactory(EntityFactory $ef): void {
		self::set('entityFactory', $ef);
	}
	public static function getEntityFactory(): EntityFactory {
		return self::get('entityFactory');
	}
}