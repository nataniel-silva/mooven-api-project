<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\DefaultInterface;
use App\DefaultTrait;
use App\Utils\Validator;
use App\Exception\DefaultException;
use App\Exception\ApiException;
use Symfony\Component\HttpFoundation\Response;
use App\Exception\BOException;
use App\Utils\Utils;
use App\Utils\CustomValidator\SearchValidator;
use App\Entity\DefaultEntity;
use App\Entity\Security\AuthToken;
use App\Entity\Common\User;
use App\Entity\Common\CompanyGroup;
use App\Utils\Singleton;
use App\Repository\DefaultRepository;
use Doctrine\ORM\Query;
use App\Entity\Premium\Plan;

/**
 * Classe de controller que deverá ser usada como classe pai para
 * todas as demais classes de controller. As classes filhas não
 * deverão possuir construtor. Se precisarem de algo, a injeção de
 * dependências deverá ser realizada diretamente nos métodos, pois
 * esta classe pai é usada para injetar dependências que são comuns
 * a todos os controllers
 */
class DefaultController extends Controller implements DefaultInterface {
	use DefaultTrait;
	
	// Constantes para definir as bags para os parâmetros vindos nas requisições
	public const BODY = 'request';
	public const QUERY = 'query';
	public const HEADER = 'headers';
	public const URL = 'attributes'; // Parâmetros que vem na URL, configurados na rota. Ex: GET user/{id}
	/**
	 *
	 * @var RequestStack
	 */
	protected $requestStack;
	/**
	 *
	 * @var Request
	 */
	protected $request;
	/**
	 *
	 * @var UrlGeneratorInterface
	 */
	protected $router;
	/**
	 * 
	 * @var Validator
	 */
	protected $validator;
	/**
	 * @var array
	 */
	private $_rules;
	/**
	 * @var array
	 */
	private $_extractedData;
	/**
	 * @var array
	 */
	private $_errors;
	/**
	 * @var AuthToken
	 */
	private $_token = null;
	/**
	 * @var string[]
	 */
	private $_requiredPermissions = [];
	/**
	 * @var bool
	 */
	private $_singleEntity = false;
	/**
	 * @var bool
	 */
	private $_checkCompanyGroup = true;
	
	/**
	 * @var string[]
	 */
	private $_requiredRoles = [];
	
	public function __construct(
		RequestStack $requestStack,
		UrlGeneratorInterface $router,
		TranslatorInterface $translator,
		Validator $validator
	) {
		$this->requestStack = $requestStack;
		$this->request = $this->requestStack->getCurrentRequest();
		$this->router = $router;
		$this->validator = $validator;
	}
	
	/**
	 * @see Validator::validate
	 * 
	 * @param array $rules Possui a mesma estrutura do array de regras do Validator
	 * acrescida das seguintes configurações: [
	 *   'from' - Define onde o parâmetro deve vir na requisição. Utilizar constantes desta classe.
	 *   'default' - Valor padrão que deve ser assumido caso o atributo não venha informado.
	 *     Obs: não usar valor default se marcar o campo como obrigatório, pois será considerado
	 *     que na requisição ele veio com o valor default informado
	 * @return self
	 */
	protected function setRequestDataRules(array $rules): self {
		$this->_rules = $rules;
		return $this;
	}
	
	/**
	 * Retorna as regras de validação setadas para requisição
	 * @return array
	 */
	protected function getRequestDataRules(): array {
		return $this->_rules;
	}
	
	/**
	 * Verifica parâmetros passados na requisição que não estão definidos pelas regras da API
	 * @throws DefaultException
	 * @return array Erros (parâmetros desconhecidos) encontrados, se algum
	 */
	private function _validateUnknownParameters(): array {
		$errors = [];
		$defaultBag = $this->getDefaultBagName();
		$rules = $this->getRequestDataRules();
		foreach ([self::BODY, self::QUERY] as $bag) { 
			foreach ($this->request->{$bag}->all() as $attr => $val) {
				try {
					if (!isset($rules[$attr])) {
						throw new DefaultException('request.validator.unknown_parameter');
					} else {
						$ruleBag = $rules[$attr]['from'] ?? $defaultBag;
						if ($bag != $ruleBag) {
							throw new DefaultException('request.validator.parameter_in_wrong_http_portion');
						}
						if ($this->_needCheckUnknownParametersInValue($val, $rules[$attr])) {
							$errors = array_merge(
								$errors,
								$this->_checkUnknownParametersInValue($val, $rules[$attr], $attr)
							);
						}
						
					}
				} catch (DefaultException $e) {
					$errors[$attr] = $e;
				}
			}
		}
		return $errors;
	}
	
	/**
	 * Retorna se tem necessidade de varrer o valor em busca de parâmetros desconhecidos
	 * @param mixed $val
	 * @param array $rule
	 * @return boolean
	 */
	private function _needCheckUnknownParametersInValue($val, array $rule) {
		if ( // Verifico se o valor é uma estrutura que tem regras configuradas para ela e se a configuração e o valor fazem sentido para esta verificação
			!isset($rule['rules']) || !(is_array($val) || is_object($val))
			|| !in_array($rule['type'], [Validator::ARRAY, Validator::OBJECT])
			|| (
				isset($rule['of'])
				&& (
					!in_array($rule['of'], [Validator::ARRAY, Validator::OBJECT])
					|| !is_array($val)
				)
			)
		) {
			return false;
		}
		return true;
	}
	
	/**
	 * Verifica se o valor tem algum atributo não especificado na API
	 * @param mixed $val
	 * @param array $rule
	 * @param string $parentAttr
	 * @return array Erros encontrados (parâmetros não definidos na API)
	 */
	private function _checkUnknownParametersInValue($val, array $rule, string $parentAttr = ''): array {
		$errors = [];
		if (!isset($rule['of'])) {
			$val = [$val];
			$isArray = false;
		} else {
			$isArray = true;
		}
		foreach ($val as $ind => $elVal) {
			$nmInd = $isArray ? "[{$ind}]" : '';
			foreach ($elVal as $attr => $v) {
				if (!isset($rule['rules'][$attr])) {
					$errors[$parentAttr."{$nmInd}.".$attr] = new DefaultException('request.validator.unknown_parameter');
				} else {
					if ($this->_needCheckUnknownParametersInValue($v, $rule['rules'][$attr])) {
						$errors = array_merge(
							$errors,
							$this->_checkUnknownParametersInValue($v, $rule['rules'][$attr], $parentAttr.$nmInd.'.'.$attr)
						);
					}
				}
			}
		}
		return $errors;
	}
	
	/**
	 *
	 * @return string
	 */
	protected function getDefaultBagName(): string {
		return $this->request->getMethod() == 'GET' ? self::QUERY : self::BODY;
	}
	
	/**
	 * Extrai e retorna os dados da requisição através das regras setadas
	 * @throws DefaultException
	 * @return array Dados extraídos
	 */
	private function _extractRequestDataFromRules(): array {
		$rules = $this->getRequestDataRules();
		if (!$rules) {
			$this->throwBusinessError('internal.controller.no_rules_to_extract_data');
		}
		// Extraio os dados da requisição de acordo com os campos configurados
		$defaultBag = $this->getDefaultBagName(); 
		$data = [];
		foreach ($rules as $attr => $rule) {
			$bag = $rule['from'] ?? $defaultBag;
			if (!array_key_exists('default', $rule) && !$this->request->{$bag}->has($attr)) {
				continue;
			}
			$data[$attr] = $this->request->{$bag}->get($attr, $rule['default'] ?? null);
			// String vazia coloco como null para evitar problemas com tipos não string
			if ($data[$attr] === '') {
				$data[$attr] = null;
			}
			// Ajusto os tipos de dados, pois pode ser que os valores escalares tenham chegado como strings ou 
			if ($data[$attr] !== null) {
				if (is_string($data[$attr])) {
					if ($rule['type'] === Validator::INTEGER) {
						if (Utils::isIntStr($data[$attr])) {
							$data[$attr] = intval($data[$attr]);
						} // else deixo como está, para dar erro na validação mesmo
					} elseif ($rule['type'] === Validator::FLOAT) {
						if (Utils::isFloatStr($data[$attr])) {
							$data[$attr] = floatval($data[$attr]);
						} // else deixo como está, para dar erro na validação mesmo
					} elseif ($rule['type'] === Validator::BOOLEAN) {
						if (Utils::isBoolStr($data[$attr])) {
							$data[$attr] = Utils::getBoolStr($data[$attr]);
						} // else deixo como está, para dar erro na validação mesmo
					}
				} elseif ($rule['type'] === Validator::OBJECT && is_array($data[$attr])) {
					$data[$attr] = (object)$data[$attr];
				}
			}
			// Removo configurações que não são tratadas no Validator
			//unset($rules[$attr]['from'], $rules[$attr]['default']);
		}
		
		return $this->_extractedData = $data;
	}
	
	/**
	 * Retorna os dados da requisição já extraídos
	 * @return array
	 */
	protected function getRequestData(): array {
		return $this->_extractedData;
	}
	
	/**
	 * Valida uma requisição de busca, utilizada para busca de formulários ou
	 * outros. É possível adicionar filtros adicionais com o $rules.
	 * 
	 * @param array $rules Regras de validação. @see DefaultRepository::prepareRulesForSearch
	 *   Além das opções adicionadas pelo método citado anteriormente, considerar as opções de self::setRequestDataRules 
	 * @param string|string[]|bool $requiredPermissions @see self::validateRequest
	 * @return array
	 */
	protected function validateSearchRequest(array $rules = [], $requiredPermissions = true): array {
		$sv = new SearchValidator('request.');
		DefaultRepository::prepareRulesForSearch($rules, $sv);
		return $this->validateRequest($rules, $requiredPermissions);
	}
	
	/**
	 * Valida a requisição de acordo com as regras setadas
	 * @param array $rules Regras de validação da requisição
	 * @return array Dados extraídos da requisição, em caso
	 *   da requisição ter passado na validação.
	 *   @see self::_extractRequestDataFromRules
	 * @param string|string[]|bool $requiredPermissions Nome interno da permissão necessária para acesso ao recurso.
	 *   Se nenhuma permissão em específica é necessária mas o usuário precisa estar autenticado deve
	 *   ser passado true (valor padrão). Para requisições em que o usuário não precisa nem estar autenticado,
	 *   deve ser passado explicitamente o valor false
	 * @throws DefaultException
	 * @throws ApiException
	 */
	protected function validateRequest(array $rules, $requiredPermissions = true): array {
		$requireAuthentication = (bool)$requiredPermissions;
		if ($requireAuthentication && !isset($rules['Authorization'])) {
			$rules['Authorization'] = ['type' => $this->validator::STRING, 'requireFilled' => true, 'from' => self::HEADER];
			$needRemoveAuthorization = true;
		} else {
			$needRemoveAuthorization = false;
		}
		if (is_string($requiredPermissions)) {
			$requiredPermissions = [$requiredPermissions];
		}
		if (is_array($requiredPermissions)) {
			$this->setRequiredPermissions($requiredPermissions);
		}
		$data = $this->setRequestDataRules($rules)->_extractRequestDataFromRules();
		if ($needRemoveAuthorization) {
			unset($this->_extractedData['Authorization'], $this->_rules['Authorization']);
		}
		$errors = $this->_validateUnknownParameters();
		if (!$errors) {
			$errors = $this->validator->setPrefix('request.')->validate($data, $rules);
			if (!$errors) {
				// Se chegou aqui é porque está tudo certo na estrutura da requisição
				if ($requireAuthentication) { // Se requer autenticação, carrego os dados do usuário logado a partir do bearer token
					$this->_loadAuthenticationDataFromHeader($data['Authorization']);
				}
				// Verificação padrão de permissão do grupo de empresa realizar a ação
				if (
					(
						isset($data['idCompanyGroup']) && $this->getAuthCompanyGroup()
						&& $data['idCompanyGroup'] != $this->getAuthCompanyGroup()->getId()
						&& !$this->getAuthUser()->hasAdminRole()
						&& $this->getCheckCompanyGroup()
					)
					|| !$this->_validateRequiredPermissions()
				) {
					$this->throwForbidden();
				}
				return $this->getRequestData();
			}
		}
		$this->throwBadRequest($errors);
	}
	
	/**
	 * @param string[] $permissions Array com os nomes internos das permissões
	 */
	protected function setRequiredPermissions(array $permissions): void {
		$this->_requiredPermissions = $permissions;
	}
	
	protected function getRequiredPermissions() {
		return $this->_requiredPermissions;
	}
	
	/**
	 * @param string[] $roles Array com os nomes internos das regras
	 */
	protected function setRequiredRoles(array $roles): void {
		$this->_requiredRoles = $roles;
	}
	
	protected function getRequiredRoles() {
		return $this->_requiredRoles;
	}
	
	/**
	 * @param bool $val Determina se deve avaliar a informação idCompanyGroup vinda na requisição (caso exista)
	 *  e verificar se bate com o código da empresa do usuário logado. Por padrão sempre faz essa verificação, exceto para usuários admin
	 */
	protected function setCheckCompanyGroup(bool $val): void {
		$this->_checkCompanyGroup = $val;
	}
	
	protected function getCheckCompanyGroup(): bool {
		return $this->_checkCompanyGroup;
	}

	/**
	 * @return bool Indica se o usuário logado possui as permissões necessárias
	 */
	private function _validateRequiredPermissions(): bool {
		if ($this->getRequiredPermissions()) {
			$user = $this->getAuthUser();
			if (!$user) {
				return false;
			}
			return $user->hasPermissionByInternalName($this->getRequiredPermissions());
		}
		if ($this->getRequiredRoles()) {
			$user = $this->getAuthUser();
			if (!$user) {
				return false;
			}
			return $user->hasRoleByInternalName($this->getRequiredRoles());
		}
		return true;
	}
	
	private function setAuthToken(AuthToken $token) {
		$this->_token = $token;
		Singleton::setAuthToken($token);
	}
	
	/**
	 * Valida as permissões do usuário. Método para utiliza apenas depois de já ter
	 * autenticado o usuário via self::validateRequest
	 * @throws ApiException
	 */
	public function validateRequiredPermissions(array $requiredPermissions): void {
		$this->setRequiredPermissions($requiredPermissions);
		if (!$this->getAuthUser()->hasAdminRole() && !$this->_validateRequiredPermissions()) {
			$this->throwForbidden();
		}
	}
	
	/**
	 * Token autenticado
	 * @return AuthToken|NULL
	 */
	public function getAuthToken(): ?AuthToken {
		return $this->_token;
	}
	
	/**
	 * Usuário logado
	 * @return User|NULL
	 */
	public function getAuthUser(): ?User {
		if ($this->_token) {
			return $this->_token->getIdUser();
		}
		return null;
	}
	
	/**
	 * Grupo de empresa do usuário logado
	 * @return CompanyGroup
	 */
	public function getAuthCompanyGroup(): ?CompanyGroup {
		if ($this->getAuthUser()) {
			return $this->getAuthUser()->getIdCompanyGroup();
		}
		return null;
	}
	
	/**
	 * Plano vigente para grupo de empresa do usuário logado
	 * @return Plan
	 */
	public function getAuthPlan(): ?Plan {
		if ($this->getAuthCompanyGroup()) {
			return $this->getAuthCompanyGroup()->getIdPlan();
		}
		return null;
	}
	
	/**
	 * Carrega o token de autenticação e dados relacionados a partir do
	 * header de autentticação
	 * @param string $authHeader Valor do header HTTP Autorization
	 * @throws BOException
	 */
	private function _loadAuthenticationDataFromHeader(string $authHeader) {
		// Validação do bearer token
		if (Utils::lower(substr($authHeader, 0, 6)) != 'bearer') {
			$this->throwBusinessError('request.login.unsupported_authorization_method');
		}
		// Valido o bearerToken
		$bearerToken = trim(substr($authHeader, 6));
		$tokenRepo = $this->ef->getRepo(AuthToken::class);
		$conf = [
			'alias' => 'at',
			'select' => 'at, u, cg, ur, r, plan',
			'where' => [
				'at.token = :token',
				'at.active = true',
				'at.expirationDate > :currDate',
				'u.active = TRUE',
				'cg.active = TRUE'
			],
			'params' => [':token' => $bearerToken, ':currDate' => Utils::getDateTimeNow()]
		];
		$token = $tokenRepo->findToken($conf);
		if (!$token) {
			$this->throwBusinessError('request.login.invalid_bearer_token');
		}
		$this->setAuthToken(current($token));
	}
	
	protected function throwForbidden() {
		throw new ApiException(Response::HTTP_FORBIDDEN, new BOException('api.forbidden'));
	}
	
	protected function throwBadRequest($errors) {
		throw new ApiException (Response::HTTP_BAD_REQUEST, new BOException('request.invalid_format', [], $errors));
	}
	
	protected function throwBusinessError(string $errorId) {
		throw new BOException($errorId);
	}
	
	/**
	 * Prepara array de entidades para retornar como resposta da requisição
	 * @param array $data Dados em resposta para a requisição (pode ser dados paginados ou não)
	 * @param array $expand Será passado ao toArray de cada entidade @see DefaultEntity::toArray
	 * @param array|bool $rename Regras de renomeio ou booleano indicando se deve fazer renomeio automático ou não.
	 *   Se for um array, será aplicado a cada entidade @see Utils::renameStruct
	 * @return JsonResponse
	 */
	protected function prepareEntityArrayForResponse(array $data, array $expand = [], $rename = false): array {
		if (isset($data['records'])) { // Retorno paginado
			$entities = &$data['records'];
		} else {
			$entities = &$data;
		}
		foreach ($entities as $ind => $entity) {
			if (method_exists($entity, 'toArray')) {
				$entities[$ind] = $entity->toArray($expand, is_bool($rename) ? $rename : false);
			}
			
			if ($rename && is_array($rename)) {
				Utils::renameStruct($entities[$ind], $rename);
			}
		}
		return $data;
	}
	
	/**
	 * Gera a resposta HTTP. Todas as actions devem usar este método
	 * para manter padronizado o retorno da API
	 * @param mixed $data Dados em resposta para a requisição
	 * @param array $rename @see Utils::renameStruct
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 */
	protected function generateResponse($data, array $rename = []): JsonResponse {
		if ($rename) {
			Utils::renameStruct($data, $rename);
		}
		return $this->json([
			'status' => Response::HTTP_OK,
			'type' => 'ok',
			'data' => $data,
		]);
	}
	
	/**
	 * 
	 * @param array $data Dados em resposta para a requisição (pode ser dados paginados ou não)
	 * @param array|bool $rename Regras de renomeio ou booleano indicando se deve fazer renomeio automático ou não.
	 *   Se for um array, será aplicado a cada entidade @see Utils::renameStruct
	 * @param array $expand Será passado ao toArray de cada entidade @see DefaultEntity::toArray
	 * @return JsonResponse
	 */
	protected function generateEntityArrayResponse(array $data, $rename = false, array $expand = []): JsonResponse {
		return $this->generateResponse($this->prepareEntityArrayForResponse($data, $expand, $rename));
	}
	
	/**
	 * Converte uma resposta de múltiplas entidades em uma resposta de uma entidade única,
	 * lançando exceção se não tem nenhuma entidade no retorno
	 * @param JsonResponse $searchRet
	 * @return JsonResponse
	 * @throws BOException
	 */
	protected function entityArrayResponseToSingle(JsonResponse $searchRet): JsonResponse {
		$data = json_decode($searchRet->getContent(), true);
		if ($data['data']['totalRecords'] == 0) {
			$this->throwBusinessError('entity.not_found');
		}
		$data['data'] = current($data['data']['records']);
		$searchRet->setData($data);
		return $searchRet;
	}
	
	/**
	 * Gera a resposta HTTP para actions de busca de dados (consultas)
	 * Deve sempre ser utilizado, pois faz tratamentos de dados de retorno
	 * @param array $data Dados retornados pela busca com a seguinte estrutura:
	 * [
	 *   'totalRecords' - int - Total de registros da consulta (consulta sem paginação)
	 *   'records' - array com as informações de cada registro (considerando a paginação)
	 * ]
	 * @param array $rename Array associativo onde o índice é a coluna da consulta que deve
	 *   ser renomeada e o valor é o novo nome que deve assumir
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 * @deprecated Nenhum lugar está utilizando. Só vou manter pois em alguma geração de relatório
	 *   mais específica pode ser útil
	 */
	protected function generateSearchResponse(array $data = [], array $rename = []): JsonResponse {
		foreach ($data['records'] as $indRec => $rec) {
			foreach ($rec as $field => $val) {
				if ($val instanceof \DateTime) {
					$data['records'][$indRec][$field] = $val->format('Y-m-d H:i:s');
				}
				if (isset($rename[$field])) {
					$data['records'][$indRec][$rename[$field]] = $data['records'][$indRec][$field];
					unset($data['records'][$indRec][$field]);
				}
			}
		}
		return $this->generateResponse($data);
	}
	
	/**
	 * Retorna uma response com arquivo binário para download
	 * @param string $file O arquivo binário em si
	 * @param string $fileName O nome do arquivo para download
	 * @param string $mimeType O mime type a ser utilizado na resposta
	 * @param boolean $forceUTF8BOM Força o arquivo ser UTF-8 BOM
	 * @param boolean $base64Decode Se é um base64 que precisa ser decodificado
	 * @return Response
	 */
	protected function generateFileResponse(string $file, string $fileName, string $mimeType, bool $forceUTF8BOM = false, bool $base64Decode = false): Response {
		$response = new Response();
		$response->headers->set('Cache-Control', 'private');
		$response->headers->set('Content-type', $mimeType);
		$response->headers->set('Content-Disposition', 'attachment; filename="'.$fileName.'";');
		if ($base64Decode){
			$response->setContent(base64_decode($file));
		} else {
			$response->headers->set('Content-length',  strlen($file));
			if ($forceUTF8BOM) {
				echo "\xEF\xBB\xBF"; // UTF-8 BOM
			}
			$response->setContent($file);
		}
		return $response;
	}
	
	/**
	 * @return string Locale do usuário logado ou da requisição
	 */
	public function getLocale() {
		if ($this->getAuthUser()) {
			return $this->getAuthUser()->getIdLocale();
		}
		return $this->request->getLocale();
	}
	
	/**
	 * Mesmos atributos de DefaultController->searchRequest, so que em forma de array de $conf.
	 *
	 * @param array $conf
	 * @return JsonResponse
	 */
	protected function searchRequestConf(array $conf) {
		return $this->searchRequest(
			$conf['repositoryCallback'], $conf['alias'], $conf['rules'] ?? [], $conf['requiredPermissions'] ?? true,
			$conf['expand'] ?? [], $conf['rename'] ?? false, $conf['select'] ?? '',
			$conf['beforeSearchCallback'] ?? null, $conf['repositoryCallbackExtraParameters'] ?? [],
			$conf['groupBy'] ?? null
		);
	}
	
	/**
	 * Método que processa requisições de busca mais simples
	 * @param callable $repositoryCallback
	 * @param string $alias
	 * @param array $rules
	 * @param string|boolean|array $requiredPermissions @see self::validateRequest
	 * @param array $expand
	 * @param array|boolean $rename Regras de renomeio ou booleano indicando se deve fazer renomeio automático ou não
	 * @param string $select
	 * @param callable $beforeSearchCallback
	 * @param array $repositoryCallbackExtraParameters Parâmetros que devem ser passados para o método
	 *   de busca além da configuração da busca (que é sempre o primeiro parâmetro), ou seja, parâmetros
	 *   do segundo em diante
	 * @param string $groupBy
	 * @return JsonResponse
	 */
	protected function searchRequest(
		callable $repositoryCallback, string $alias, array $rules = [], $requiredPermissions = true,
		array $expand = [], $rename = false, string $select = '', callable $beforeSearchCallback = null,
		array $repositoryCallbackExtraParameters = [], string $groupBy = null
	) {
		if (!$select) {
			$select = $alias;
		}
		$data = $this->validateSearchRequest($rules, $requiredPermissions);
		$rules = $this->getRequestDataRules();
		$conds = DefaultRepository::generateSearchConditionsFromRules($data, $rules, $alias);
		if ($beforeSearchCallback) {
			call_user_func(
				$beforeSearchCallback,
				['conds' => &$conds, 'data' => &$data, 'rules' => &$rules, 'controller' => $this]
			);
		}
		$queryConf = [
			'alias' => $alias,
			'select' => $select,
			'where' => $conds['conds'],
			'params' => $conds['params'],
			'limit' => $data['limit'] ?? null,
			'offset' => $data['offset'] ?? null,
			'orderBy' => DefaultRepository::extractOrderByFromRules($data['orderBy'] ?? null, $rules, $alias),
			'groupBy' => $groupBy,
		];
		
		$records = call_user_func_array($repositoryCallback, array_merge([$queryConf], $repositoryCallbackExtraParameters));
		
		$ret = $this->generateEntityArrayResponse($records, $rename, $expand);
		
		if ($this->_singleEntity) {
			$ret = $this->entityArrayResponseToSingle($ret);
			$this->_singleEntity = false;
		}
		
		return $ret;
	}
	
	/**
	 * Seta que a próxima resposta utilizando o método self::searchRequest deverá
	 * ser convertida para uma resposta de uma única entidade
	 * Obs: isso só vale para a próxima chamada da searchRequest, todas as subsequentes
	 * voltarão a retornar um array de entidades
	 */
	protected function setNextResponseAsSingleEntity() {
		$this->_singleEntity = true;
	}
}