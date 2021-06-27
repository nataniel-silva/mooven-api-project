<?php
namespace App\Repository;

use App\Utils\Validator;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Exception\DefaultException;
use Doctrine\ORM\Query\Expr\Composite;
use App\Controller\DefaultController;
use App\Entity\DefaultEntity;
use App\Utils\CustomValidator\SearchValidator;
use App\Utils\Utils;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use App\Utils\Singleton;


/**
 * Classe de repositório que deverá ser usada como classe pai para
 * todas as demais classes de repositório. Ela identifica automaticamente
 * qual a Entity deve utilizar através do nome da classe de repositório
 */
class DefaultRepository extends EntityRepository implements ServiceEntityRepositoryInterface {
	/**
	 * @var TranslatorInterface
	 */
	protected $translator;
	/**
	 * @var Registry
	 */
	private $_registry;
	
	/**
	 * @param RegistryInterface $registry Obtido por autowiring
	 * @param TranslatorInterface $translator
	 * @throws \Exception Caso encontre algo errado com a classe
	 */
	public function __construct(RegistryInterface $registry, TranslatorInterface $translator) {
		$this->translator = $translator;
		$this->_registry = $registry;
		
		$manager = $this->_determineEntityManager();
		parent::__construct($manager, $manager->getClassMetadata($this->_getEntityClass()));
	}
	
	/**
	 * Atualiza o EntityManager sendo utilizado pela classe
	 */
	public function updateEntityManager() {
		$this->_em = $this->_determineEntityManager();
	}
	
	/**
	 * Determina o EntityManager que deve ser utilizado para o repositório
	 */
	private function _determineEntityManager(): EntityManager {
		$entityClass = $this->_getEntityClass();
		//$manager = $registry->getManagerForClass($entityClass);
		$schema = explode('\\', $entityClass)[2];
		return $this->_registry->getManager($schema === 'Log' ? 'log' : Singleton::getEntityManagerNameToUseInsteadDefault());
	}
	
	/**
	 * Define o nome da entidade que deve ser utilizada
	 * @throws \Exception
	 * @return string Nome da classe da Entity
	 */
	private function _getEntityClass() {
		// O nome da entity tem que ser o mesmo nome do repositório
		$entityClass = str_replace('\\\\', '\\Entity\\', str_replace('Repository', '', get_class($this)));
		if (!class_exists($entityClass, true)) {
			throw new DefaultException('internal.repository.entity_class_not_found', array('%class%' => $entityClass));
		}
		// Todas as entidades deverão extender a mesma classe
		if (!is_subclass_of($entityClass, \App\Entity\DefaultEntity::class)) {
			throw new DefaultException('internal.repository.wrong_entity_class', array('%class%' => $entityClass));
		}
		return $entityClass;
	}
	
	/**
	 * Busca, de forma genérica, entidades da base de dados, retorna sempre array.
	 * Mais informações na função createSearchQuery().
	 *
	 * @param array $conf @see self::createSearchQuery
	 * @param $hydrationMode O hydrator que deseja utilizar
	 * @param bool $useOutputWalker
	 * @return array @see self::applyPaginationToQuery
	 * @throws \Exception
	 */
	public function search(array $conf = [], $hydrationMode = Query::HYDRATE_OBJECT, bool $useOutputWalker = false) : array {
		try {
			return $this->applyPaginationToQuery($this->createSearchQuery($conf), $hydrationMode, $useOutputWalker);
		} catch (\Exception $e) {
			// @todo Fazer log antes
			throw $e;
		}
	}
	
	/**
	 * @param QueryBuilder $qb
	 * @param $hydrationMode O hydrator que deseja utilizar
	 * @param bool $useOutputWalker
	 * @return array
	 * [
	 *   'totalRecords' - int total de registros buscados
	 *   'records' - array com os resultados buscados
	 * ]
	 */
	public function applyPaginationToQuery(QueryBuilder $qb, $hydrationMode = Query::HYDRATE_OBJECT, bool $useOutputWalker = false): array {
		$query = $qb->getQuery();
		$query->setHydrationMode($hydrationMode);
		
		if ($hydrationMode != Query::HYDRATE_OBJECT) {
			$query->setHint(Query::HINT_INCLUDE_META_COLUMNS, true);
		}
		
		// Se for no modo de ARRAY tive que fazer o meu próprio COUNT, pois o Doctrine não conseguia trabalhar em alguns casos
		if ($hydrationMode == Query::HYDRATE_ARRAY) {
			$records = $query->getResult($hydrationMode);
			$totalRecords = count($query->setMaxResults(null)->setFirstResult(null)->getResult($hydrationMode));
		} else {
			$paginator = new Paginator($query);
			// Necessário false para não dar erro quando seleciona apenas alguns campos em vez da tabela inteira
			// Necessário true quando usa limit e order by por campo de alguma tabela que é múltipla
			// Pelo que entendi, para evitar problemas de performance, o melhor é usar false, então somente deve setar true quando realmente precisar
			$paginator->setUseOutputWalkers($useOutputWalker);
			$totalRecords = $paginator->count();
			$records = $totalRecords ? $paginator->getIterator()->getArrayCopy() : [];
		}
		
		return ['totalRecords' => $totalRecords, 'records' =>  $records];
	}
	
	/**
	 * Retorna um array com as entidades encontradas na busca
	 * @param $hydrationMode O hydrator que deseja utilizar
	 * @param array $conf @see self::createSearchQuery
	 * @return DefaultEntity[]
	 */
	public function findByConf(array $conf = [], $hydrationMode = Query::HYDRATE_OBJECT): array {
		$query = $this->createSearchQuery($conf)->getQuery();
		if ($hydrationMode != Query::HYDRATE_OBJECT) {
			$query->setHint(Query::HINT_INCLUDE_META_COLUMNS, true);
		}
		return $query->getResult($hydrationMode);
	}
	
	/**
	 * Retorna a primeira entidade encontrada na busca
	 * @param array $conf @see self::createSearchQuery
	 * @param $hydrationMode O hydrator que deseja utilizar
	 * @return DefaultEntity|null
	 */
	public function findOneByConf(array $conf = [], $hydrationMode = Query::HYDRATE_OBJECT): ?DefaultEntity {
		$conf['limit'] = 1;
		$conf['offset'] = 0;
		$a = $this->findByConf($conf, $hydrationMode);
		return $a ? current($a) : null;
	}
	
	/**
	 * Retorna um array com os resultados escalares da busca
	 * @param $hydrationMode O hydrator que deseja utilizar
	 * @param array $conf @see self::createSearchQuery
	 * @return array
	 */
	public function getScalarResultByConf(array $conf = [], $hydrationMode = Query::HYDRATE_OBJECT): array {
		$query = $this->createSearchQuery($conf)->getQuery();
		if ($hydrationMode != Query::HYDRATE_OBJECT) {
			$query->setHint(Query::HINT_INCLUDE_META_COLUMNS, true);
		}
		return $query->getScalarResult();
	}
	
	/**
	 * Gera uma QueryBuilder genérica para buscar objetos na base de dados.
	 *
	 * @param array $conf - Configurações extra da query a ser gerada:
	 * [
	 *   'alias' - Alias da tabela principal (tabela do repositório). Default: 't'
	 *   'where' - Composite ou array para ser processado por self::createSearchConditions. Condições do where 
	 *   'params' - array associativo com os parâmetros para bind. Obs: o índice vazio será removido ($a[''])
	 *   'orderBy' - colunas para ordernar
	 *   'limit' - limitar resultados
	 *   'offset' - exibir resultados a partir de (somente utilizado se o limit também estiver informado)
	 *   'select' - Campos a serem selecionados
	 * ]
	 * @return QueryBuilder
	 */
	public function createSearchQuery(array $conf = []) : QueryBuilder {
		$alias = $conf['alias'] ?? 't';
		$select = $conf['select'] ?? $alias;
		$qb = $this->createQueryBuilder($alias)->select($select);
		if ($conf['where'] ?? false) {
			$qb->where(is_array($conf['where']) ? $this->createSearchConditions($conf['where']) : $conf['where']);
		}
		if ($conf['params'] ?? false) {
			if (isset($conf['params'][''])) {
				unset($conf['params']['']);
			}
			$qb->setParameters($conf['params']);
		}
		if ($conf['orderBy'] ?? false) {
			$qb->add('orderBy', $conf['orderBy']);
		}
		if ($conf['limit'] ?? false) {
			$qb->setMaxResults($conf['limit']);
			if ($conf['offset'] ?? false) {
				$qb->setFirstResult($conf['offset']);
			}
		}
		if ($conf['groupBy'] ?? false) {
			$qb->groupBy($conf['groupBy']);
		}
		return $qb;
	}
	
	/**
	 * Cria as condições de busca dado um array com as expressões DQL 
	 * @param array $conditions Array onde cada valor é uma expressão DQL. O índice indíca
	 * qual a operação deve realizar entre as operações contidas no array. Ex:
	 * $conditions = [
	 * 		"COALESCE(t.idp, 'ABC') IS NOT NULL",
	 * 		'OR' => [
	 * 			't.successUri IS NULL',
	 * 			't.failureUri IS NULL',
	 * 			'AND' => [
	 * 				't.idSamlSso IS NOT NULL',
	 * 				't.remoteIp IS NULL',
	 * 			]
	 * 		],
	 * 		't.token IS NULL'
	 * 	];
	 * Resultado:
	 *   WHERE COALESCE(t.idp, 'ABC') IS NOT NULL
	 *     AND (
	 *       t.successUri IS NULL
	 *       OR t.failureUri IS NULL
	 *       OR (t.idSamlSso IS NOT NULL AND t.remoteIp IS NULL)
	 *     )
	 *     AND t.token IS NULL
	 * Obs: como pode ser necessário ter mais de um OR ou mais de um AND, podem ser adicionados números
	 * no final. Ex: OR1, OR2, AND2, AND15
	 * @param Composite $composite. É utilizado internamente pelo método que é recursivo.
	 *   Provavelmente não será necessário utilizá-lo.
	 * @return Composite|null Objeto do doctrine com as expressões de filtro. Retorna null
	 * caso nenhuma condição tenha sido passada
	 */
	public function createSearchConditions(array $conditions, ?Composite $composite = null): ?Composite {
		if (!$conditions) {
			return null;
		}
		if (is_null($composite)) {
			$composite = $this->_em->getExpressionBuilder()->andX();
		}
		foreach ($conditions as $ind => $condition) {
			if (empty($condition)) {
				continue;
			}
			$ind = rtrim($ind, '0123456789');
			if ($ind === 'OR' || $ind === 'AND') {
				$newComposite = $this->_em->getExpressionBuilder()->{Utils::lower($ind).'X'}();
				$composite->add($this->createSearchConditions($condition, $newComposite));
				continue;
			}
			$composite->add($condition);
		}
		return $composite;
	}
	
	/**
	 * @param array $data O índice é o nome da coluna sem o alias e o valor, o valor de busca,
	 *   no formato definido pelas regras de valiação
	 * @param array $rules Regras de validação. @see self::prepareRulesForSearch 
	 * @param string $defaultAlias O alias padrão a ser aplicado no nome da coluna
	 * @return array com as seguinte estrutura: 
	 * [
	 *   'conds' => array com as condições geradas em formato aceito pela self::createSearchConditions,
	 *   'params' => array com os parâmetros para prapared statment
	 * ]
	 */
	public static function generateSearchConditionsFromRules(array $data, array $rules, string $defaultAlias = 't') {
		$exclude = ['limit', 'offset', 'orderBy'];
		foreach ($exclude as $param) {
			unset($data[$param]);
		}
		
		$conds = [];
		$params = [];
		$currInd = 0;
		foreach ($data as $param => $val) {
			if ($val === null || !isset($rules[$param]) || ($rules[$param]['ignoreFilter'] ?? false)) {
				continue;
			}
			$field = $rules[$param]['column'] ?? (rtrim($rules[$param]['alias'] ?? $defaultAlias, '.')).'.'.$param;
			$condTmp = self::generateSearchConditionFromRule($val, $field, $rules[$param]);
			$qtCond = count($condTmp);
			$indCond = ($qtCond > 1 ? 'OR' : '').$currInd;
			foreach ($condTmp as $i => $cond) {
				if (isset($cond['params']) && $cond['params']) {
					$params = array_merge($params, $cond['params']);
				}
				$condTmp[$i] = $cond['cond'];
			}
			$conds[$indCond] = ($qtCond > 1 ? $condTmp : current($condTmp));
			$currInd++;
		}
		return ['conds' => $conds, 'params' => $params];
	}
	
	/**
	 * @used-by self::generateSearchConditionsFromRules. Não deve ser usado por nenhum outro método
	 */
	private static function generateSearchConditionFromRule($val, string $field, array $rule) {
		if ($rule['list'] ?? false) {
			if ( // Busca em string
				$rule['_type'] === Validator::STRING
				&& (!isset($rule['enum']) || !$rule['enum'])
				&& (!isset($rule['date']) || !$rule['date'])
			) {
				$val = str_getcsv($val, ',', '"');
			} else { // Qualquer outro tipo de busca
				$val = explode(',', $val);
			}
		}
		
		$conds = [];
		$inVals = [];
		$indParam = 1;
		$paramName = ':'.str_replace('.', '', $field);
		if ($rule['condExpr'] ?? $rule['condExprCallback'] ?? false) {
			if ($rule['condExprCallback'] ?? false) {
				if (!is_callable($rule['condExprCallback'])) {
					throw new DefaultException('internal.repository.non_callable_callback');
				}
				$ret = call_user_func($rule['condExprCallback'], $val, $rule);
				if ($ret) {
					if (!is_array($ret) || !isset($ret['cond'])) {
						throw new DefaultException('internal.repository.invalid_callback_return');
					}
					$conds[] = $ret;
				}
			} elseif (strpos($rule['condExpr'], '{OPERATION_VALUE}') !== false) {
				if (is_array($val) && count($val) == 1) { // Pode chegar aqui como array devido ao tratamento de list
					$val = current($val);
				}
				$params = [];
				$operation = '';
				if (is_array($val) && count($val) > 1) {
					$operation = 'IN ('.$paramName.$indParam.')';
					$params[$paramName.$indParam] = $val;
				} elseif (($rule['range'] ?? false) && strpos($val, '|') !== false) {
					if ($rule['avoidBetweenOperand'] ?? false) {
						$rule['condExpr'] = '{OPERATION_VALUE}'; // Vai usar o operando que está no parâmetro em vez da expressão padrão
						if (is_array($rule['avoidBetweenOperand'])) {
							$firstOperand = $rule['avoidBetweenOperand'][0];
							$secondOperand = $rule['avoidBetweenOperand'][1];
						} else {
							$firstOperand = $secondOperand = $rule['avoidBetweenOperand'];
						}
						$operation = $paramName.$indParam.' <= '.$firstOperand.' AND '.$paramName.($indParam + 1).' >= '.$secondOperand; // Subquery como operador esquerdo não funciona, por isso tive que fazer essa lógica inversa
					} else {
						$operation = 'BETWEEN '.$paramName.$indParam.' AND '.$paramName.($indParam + 1);
					}
					$a = explode('|', $val);
					$params[$paramName.$indParam] = $a[0];
					$params[$paramName.($indParam + 1)] = $a[1];
				} else {
					$firstChar = substr($val, 0, 1);
					$secondChar = substr($val, 1, 1);
					$lastChar = substr($val, -1, 1);
					if (
						(($rule['lt'] ?? false) && $firstChar.$secondChar == '<=')
						|| (($rule['gt'] ?? false) && $firstChar.$secondChar == '>=')
					) {
						$operation = $firstChar.$secondChar.' '.$paramName.$indParam;
						$params[$paramName.$indParam] = substr($val, 2);
					} elseif (
						(($rule['lt'] ?? false) && $firstChar == '<')
						|| (($rule['gt'] ?? false) && $firstChar == '>')
					) {
						$operation = $firstChar.' '.$paramName.$indParam;
						$params[$paramName.$indParam] = substr($val, 1);
					} elseif (($rule['negation'] ?? false) && $firstChar == '!') {
						$operation = '<> '.$paramName . $indParam;
						$params[$paramName.$indParam] = substr($val, 1);
					} elseif (
						($rule['wildcard'] ?? false)
						&& (
							(($rule['wildcard'][0] ?? false) && $firstChar == '%')
							|| (($rule['wildcard'][1] ?? false) && $lastChar == '%')
						)
						&& strlen($val) > 1 // Só trata wildcard se tiver mais de um caracter na string
					) {
						if ($firstChar == '%') {
							$val = substr($val, 1);
						}
						if ($lastChar == '%') {
							$val = substr($val, 0, -1);
						}
						$val = str_replace('%', '\%', $val); // Escapo
						if ($firstChar == '%') {
							$val = '%'.$val;
						}
						if ($lastChar == '%') {
							$val .= '%';
						}
						$operation = 'LIKE UPPER(UNACCENT('.$paramName.$indParam.'))';
						$params[$paramName.$indParam] = $val;
					} else {
						$operation = '= '.$paramName.$indParam;
						$params[$paramName.$indParam] = $val;
					}
				}
				$conds[] = [
					'cond' => str_replace('{OPERATION_VALUE}', $operation, $rule['condExpr']),
					'params' => $params
				];
			} else {
				$conds[] = [
					'cond' => str_replace('{VALUE}', $paramName.$indParam, $rule['condExpr']),
					'params' => strpos($rule['condExpr'], '{VALUE}') === false ? [] : [$paramName.$indParam => $val]
				];
			}
		} elseif ($rule['_type'] === Validator::BOOLEAN) {
			$conds[] = ['cond' => $field.' = '.($val ? 'true' : 'false'), 'params' => []];
		} elseif (isset($rule['enum']) && $rule['enum']) {
			$inVals = is_array($val) ? $val : [$val];
		} else { // Qualquer tipo exceto boolean e enumerado
			if (!is_array($val)) {
				$val = [$val];
			}
			foreach ($val as $vl) {
				if (($rule['range'] ?? false) && strpos($vl, '|') !== false) {
					$a = explode('|', $vl);
					$conds[] = [
						'cond' => $field.' BETWEEN '.$paramName.$indParam.' AND '.$paramName.($indParam + 1),
						'params' => [$paramName.$indParam => $a[0], $paramName.($indParam + 1) => $a[1]]
					];
					$indParam += 2;
				} else {
					$firstChar = substr($vl, 0, 1);
					$secondChar = substr($vl, 1, 1);
					$lastChar = substr($vl, -1, 1);
					if (
						(($rule['lt'] ?? false) && $firstChar.$secondChar == '<=')
						|| (($rule['gt'] ?? false) && $firstChar.$secondChar == '>=')
					) {
						$conds[] = [
							'cond' => $field.' '.$firstChar.$secondChar.' '.$paramName.$indParam,
							'params' => [$paramName.$indParam => substr($vl, 2)]
						];
						$indParam++;
					} elseif (
						(($rule['lt'] ?? false) && $firstChar == '<')
						|| (($rule['gt'] ?? false) && $firstChar == '>')
					) {
						$conds[] = [
							'cond' => $field.' '.$firstChar.' '.$paramName.$indParam,
							'params' => [$paramName.$indParam => substr($vl, 1)]
						];
						$indParam++;
					} elseif (($rule['negation'] ?? false) && !($rule['list'] ?? true) && $firstChar == '!') {
						$conds[] = [
							'cond' => $field . ' <> ' . $paramName . $indParam,
							'params' => [$paramName . $indParam => substr($vl, 1)]
						];
						$indParam++;
					} elseif (
						($rule['wildcard'] ?? false)
						&& (
							(($rule['wildcard'][0] ?? false) && $firstChar == '%')
							|| (($rule['wildcard'][1] ?? false) && $lastChar == '%')
						)
					) {
						if (strlen($vl) > 1) {
							if ($firstChar == '%') {
								$vl = substr($vl, 1);
							}
							if ($lastChar == '%') {
								$vl = substr($vl, 0, -1);
							}
							$vl = str_replace('%', '\%', $vl); // Escapo
							if ($firstChar == '%') {
								$vl = '%'.$vl;
							}
							if ($lastChar == '%') {
								$vl .= '%';
							}
						}
						
						$conds[] = [
							'cond' => "UPPER(UNACCENT({$field})) LIKE UPPER(UNACCENT({$paramName}{$indParam}))",
							'params' => [$paramName.$indParam => $vl]
						];
						$indParam++;
					} else {
						$inVals[] = $vl;
					}
				}
			} // End foreach valor
		} // End else
			
		if ($inVals) {
			$not = '';
			if (($rule['negation'] ?? false) && (substr($inVals[0], 0, 1) === '!')) {
				$not = ' NOT ';
				$inVals[0] = substr($inVals[0], 1);
			}
			$conds[] = [
				'cond' => $field.$not.' IN ('.$paramName.$indParam.')',
				'params' => [$paramName.$indParam => $inVals]
			];
			$indParam++;
		}
		
		return $conds;
	}
	
	/**
	 * Retorna um string com o order by corretamente a partir de uma
	 * string no seguinte formato:
	 * coluna1|direcao,coluna2|direcao,....
	 * @param string $rawOrder
	 * @param array $rules Regras de validação. @see DefaultController::validateSearchRequest
	 * @param string $defaultAlias O alias padrão a ser aplicado no nome da coluna,
	 *   caso não encontre qual nome de alias utilizar nas regras
	 * @return string|null Retorna o order by ou então Null se não o order estiver vazio
	 */
	public static function extractOrderByFromRules(?string $rawOrder, array $rules, string $defaultAlias = 't') {
		if (!$rawOrder) {
			return null;
		}
		$defaultAlias = rtrim($defaultAlias, '.');
		
		$fields = explode(',', $rawOrder);
		foreach ($fields as $ind => $val) {
			$tmp = explode('|', $val);
			$field = $tmp[0];
			if (count($tmp) == 2) {
				$direction = $tmp[1];
			} else {
				$direction = 'ASC';
			}
			if ($rules[$field] ?? false) {
				if ($rules[$field]['sortExpr'] ?? false) {
					$field = str_replace(
						['{DIRECTION}', '{INVERSE_DIRECTION}'],
						[$direction, $direction == 'ASC' ? 'DESC' : 'ASC'],
						$rules[$field]['sortExpr']
					);
					$direction = '';
				} elseif ($rules[$field]['column'] ?? false) {
					$field = $rules[$field]['column'];
				} elseif ($rules[$field]['alias'] ?? false) {
					$field = rtrim($rules[$field]['alias'], '.').'.'.$field;
				} else {
					$field = $defaultAlias.'.'.$field;
				}
			} else {
				$indCol = array_search($field, $rules['orderBy']['columns']); // Se o índice não for numérico, então ele contém a coluna da ordenação
				if (!is_numeric($indCol)) {
					$field = $indCol;
				} else { 
					$field = $defaultAlias.'.'.$field;
				}
			}
			$fields[$ind] = $field.' '.$direction;
		}
		return implode(', ', $fields);
	}
	
	/**
	 * Prepara uma regra de validação para processar como parâmetro de busca
	 * @param array $rule Configuração da regra para o campo
	 * @param SearchValidator $sv
	 */
	private static function prepareRuleForSearch(&$rule, SearchValidator $sv = null) {
		if (isset($rule['_type'])) { // Se já esta informação é porque já deve ter sido processado
			return;
		}
		$rule['_type'] = $rule['type']; // Mantém o tipo original para poder verificar nos métodos de validação
		if ($rule['type'] != Validator::BOOLEAN) {
			$rule['type'] = Validator::STRING; // Sempre trata como string
		}
		
		// Valores default
		$rule['wildcard'] = $rule['wildcard'] ?? [true, true];
		if (!is_array($rule['wildcard'])) {
			$rule['wildcard'] = [$rule['wildcard'], $rule['wildcard']];
		}
		$rule['list'] = $rule['list'] ?? true;
		$rule['range'] = $rule['range'] ?? true;
		$rule['gt'] = $rule['gt'] ?? true;
		$rule['lt'] = $rule['lt'] ?? true;
		$rule['negation'] = $rule['negation'] ?? true;
		if (!isset($rule['sortable'])) {
			$rule['sortable'] = true;
		}
		
		// Callback para validação
		$call = null;
		if (isset($rule['enum']) && $rule['enum']) {
			$call = 'validateEnum';
		} elseif (isset($rule['date']) && $rule['date']) {
			$call = 'validateDate';
		} else {
			switch ($rule['_type']) {
				case Validator::STRING:
					$call = 'validateString';
					break;
				case Validator::INTEGER:
					$call = 'validateInteger';
					break;
				case Validator::FLOAT:
					$call = 'validateFloat';
					break;
			}
		}
		if ($call && $sv) {
			$rule['custom'] = [$sv, $call];
		}

		// Remove configurações não suportadas para cada tipo
		$nonString = $rule['_type'] != Validator::STRING || ($rule['enum'] ?? $rule['date'] ?? false);
		if ($nonString) { // Não é string
			unset($rule['wildcard']);
		}
		if (!$nonString || $rule['_type'] == Validator::BOOLEAN) { // String ou boolean
			unset($rule['range'], $rule['gt'], $rule['lt']);
			if ($rule['_type'] == Validator::BOOLEAN) {
				unset($rule['list']);
			}
		}
	}
	
	/**
	 * Prepara as regras de validação para uma requisição de busca.
	 * 
	 * @param array $rules Regras de validação. @see Validator::validate. Além das configurações suportadas
	 * originalmente, é possível validar os filtros com as seguintes regras:
	 *  Obs: o valor padrão assumido para cada atributo é o que aparece primeiro na lista abaixo, caso não seja informado
	 *  [
	 *    'type' => Validator::STRING, 'wildcard' => [true|false, true|false]|(true|false), 'list' => true|false, 'negation' => true|false,
	 *    'type' => Validator::INTEGER, 'list' => true|false, 'range' => true|false, 'gt' => true|false, 'lt' => true|false,
	 *    'type' => Validator::BOOLEAN,
	 *    'type' => Validator::STRING|Validator::INTEGER, 'enum' => [val1, val2, val3, ...], 'list' => true|false,  // Enumerado
	 *    'type' => Validator::STRING, 'date' => true, 'list' => true|false, 'range' => true|false, 'gt' => true|false, 'lt' => true|false, 'dateFmt' => 'Y-m-d' // Data
	 *    'type' => Validator::FLOAT, 'list' => true|false, 'range' => true|false, 'gt' => true|false, 'lt' => true|false,
	 *  ]
	 *  Para todos os tipos acima é possível informar as seguintes configurações:
	 *    1. 'sortable' => true|false. Se não informado, assume true
	 *    
	 *  Configurações utilizadas apenas para geração das condições da query (não são usadas para a validação)
	 *    1. 'column' => O nome do campo a ser selecionado (com o alias). Ex: t.fieldName
	 *    2. 'alias' => O alias a ser utilizado caso não informado 'column' e o alias para o campo não seja o padrão
	 *    3. 'condExpr' => Expressão a ser utilizada como condição do where.
	 *      Nela pode ser informado a variável {VALUE}, que será substituído pelo valor da busca informado, sem nenhum tratamento (exceto list),
	 *      ou pode informar a variável {OPERATION_VALUE} que será substituído pela operação (lt, gt, list, range, wildcard) e valor.
	 *      Obs: ao informar esta configuração, algumas das outras configurações não são tratadas:
	 *        Nenhuma exceto list para {VALUE} e operações dentro de uma list para {OPERATION_VALUE}.
	 *        Exemplo de operação dentro de uma list:
	 *        1,>5,3 - Neste caso vai dar problema porque o >5 não vai ser tratado antes de passar o valor para a query
	 *    4. 'condExprCallback' => Função que deve retornar um array com a estrutura:
	 *        [
	 *          'cond' => string com o SQL,
	 *          'params' => array associativo com os parâmetros da query
	 *        ]
	 *        Parâmetros de entrada da função:
	 *          mixed $value - Valor do campo/filtro,
	 *          array $rule - Regra do campo
	 *    5. 'ignoreFilter' => Boolean que se setado vai ignorar o campo, não gerando filtro para ele
	 *    6. 'sortExpr' => Expressão a ser utilizada para ordenação do campo. Nela, podem ser informadas
	 *      as variáveis {DIRECTION} e {INVERSE_DIRECTION}, que serão substituídas pela direção da ordenação
	 *      e a direção inversa, respectivamente
	 *    7. 'avoidBetweenOperand' => Pode ser utilizado junto com a 'condExpr'. Serve para mudar a forma que faz between (range).
	 *        Necessário quando o valor à esquerda do between é algo calculado ou uma subquery, pois o Doctrine não suporta isso,
	 *        então em vez de fazer o campo between valor1 and valor 2, tem que gerar valor1 <= campo and valor2 >= campo.
	 *        O valor para este parâmetro de ver o campo que vai ser utilizado no between. Se precisar usar dois campos diferentes
	 *        (um para comparar com o menor valor e outro para comparar com o maior) deve ser passado um array com as posições 0 e 1 
	 *    
	 *  Exemplos de utilização de:
	 *    1. range
	 *    1.a - Inteiro: 0|100, -5|5
	 *    1.b - Float: -1.15|25.5
	 *    1.c - Data: 2018-01-20|2018-10-15
	 *    Obs: em ranges, não é permitido utilizar valores com sinais de lt nem de gt
	 *    
	 *    2. list
	 *    2.a - Inteiro: 1,3,6-10,15
	 *    2.b - Float: 1.3,1.4,1.6|2.0
	 *    2.c - Data: 2018-01-20,2018-10-15
	 *    2.d - String: name1,name2,"name,with,comma"
	 *    
	 *    3. lt
	 *    3.a - Inteiro: <-1, <34
	 *    3.b - Float: <10.0
	 *    3.c - Data: <2018-05-25
	 *    3.d - Considerar valor informado (menor ou igual a): <=10
	 *
	 *    4. gt
	 *    4.a - Inteiro: >-1, <34
	 *    4.b - Float: >10.0
	 *    4.c - Data: >2018-05-25
	 *    4.d - Considerar valor informado (maior ou igual a): >=50
	 *
	 *    5. wildcard - %nome%, nome%, %nome
	 *    Obs: % usado em qualquer lugar que não seja no início ou no final do texto será
	 *    tratado como o caracter literal
	 *
	 *    6. enum
	 *    6.a String -> ['valor1', 'valor2', 'valor3']
	 *    6.b Inteiro -> [1, 2, 3]
	 *
	 *    7. negation
	 *    7.a String -> !12
	 *    7.b String -> ['!valor1', 'valor2']
	 *
	 *  Obs: os parâmetros padrões esperados em qualquer search request também podem ser
	 *  sobrescritos, basta enviá-los no array de $rules. são eles:
	 *    - limit
	 *    - offset
	 *    - orderBy - Esse valida quais as possíveis colunas de se fazer ordenação através da configuração
	 *        "columns". As colunas são automaticamente detectdas pelo parâmetro 'sortable'. Se for passado
	 *        algum valor para "columns", será feito um merge entre os valores já identificados pelo parâmetro
	 *        'sortable' e os valores passados
	 *        Obs: se no índice do array "columns" for uma string, esta será usada como a coluna real a ser
	 *        utilizada na ordenação
	 *  @see self::validateRequest
	 *  @param string $requiredPermissions @see self::validateRequest
	 *  @return array
	 */
	public static function prepareRulesForSearch(array &$rules, SearchValidator $sv = null) {
		$sortableFields = [];
		foreach ($rules as $field => $rule) {
			if (in_array($field, ['orderBy', 'limit', 'offset'])) {
				continue;
			}
			self::prepareRuleForSearch($rules[$field], $sv);
			// Campos ordenáveis
			if ($rules[$field]['sortable']) {
				$sortableFields[] = $field;
			}
		} // End foreach regra
		
		// Regras de campos padrões para search requests
		$rules = array_merge(
			[
				'limit' => ['type' => Validator::INTEGER],
				'offset' => ['type' => Validator::INTEGER],
			], $rules
		);
		if (!isset($rules['orderBy'])) {
			$rules['orderBy'] = ['type' => Validator::STRING];
		}
		if (!isset($rules['orderBy']['custom']) && $sv) {
			$rules['orderBy']['custom'] = [$sv, 'validateOrderBy'];
		}
		if (isset($rules['orderBy']['columns'])) { // Merge
			$sortableFields = array_merge($sortableFields, $rules['orderBy']['columns']);
		}
		$rules['orderBy']['columns'] = $sortableFields;
	}
	
	/**
	* Executa uma consulta no banco, se passado o array de parâmetros será ubstituído no padrão :nome_parametro com o valor valor_parametro.
	* Feito dessa forma para que se necessário utilizar o mesmo valor de um parâmetro em mais de uma ocasião, ser possível e não ter que escrever ele novamente com outro nome.
	* Ex.: SELECT * FROM XXX WHERE :nome_parametro1 IS NULL OR :nome_parametro1 = 1;
	*
	* @param $sql
	* @param array $parameters [0 => [nome_parametro1 => valor_parametro],
	*							1 => [nome_parametro1 => valor_parametro]]
	* @return mixed[]
	* @throws \Doctrine\DBAL\DBALException
	*/
	public function executeQuery(string $sql, array $parameters = []) {
		$em = $this->_determineEntityManager();
		$conn = $em->getConnection();
		$query = $conn->prepare($sql);
		
		if ($parameters) {
			foreach ($parameters as $value) {
				$column = current(array_keys($value));
				$columnValue = current(array_values($value));
				$query->bindValue(":{$column}", $columnValue);
			}
		}
		
		$query->execute();
		return $query->fetchAll();
	}
}