<?php
namespace App\Utils;

use Doctrine\ORM\Query;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\Common\Util\Debug;
use App\Exception\BOException;
use App\Lib\Encoding\Encoding;
use App\Entity\Common\File;

class Utils {
	public static function isIntStr(string $str): bool {
		$tmp = intval($str);
		if ((string)$tmp === ltrim($str, '+')) {
			return true;
		}
		return false;
	}
	
	public static function isFloatStr(string $str): bool {
		$tmp = floatval($str);
		$hasDot = strpos($str, '.') !== false;
		if ($hasDot) {
			$str = rtrim($str, '0');
			if (substr($str, -1) === '.') { // Ficou só o ponto no final
				$str = substr($str, 0, -1);
			}
		}
		if ((string)$tmp === ltrim($str, '+')) {
			return true;
		}
		return false;
	}
	
	public static function isBoolStr(string $str): bool {
		return in_array($str, ['1', 't', 'true', '0', 'f', 'false']);
	}
	
	public static function getBoolStr(string $str): bool {
		return in_array($str, ['1', 't', 'true']);
	}
	
	public static function isDateStr(string $str, string $fmt = 'Y-m-d'): bool {
		$d = \DateTime::createFromFormat($fmt, $str);
		$e = \DateTime::getLastErrors();
		return $d !== false && $e['warning_count'] === 0 && $e['error_count'] === 0;
	}
	
	/**
	 * Retorna um objeto de data a partir de uma string
	 * Obs: vai sempre retorar uma data truncada (com o tempo em 00:00:00)
	 * e portanto no formato e na string não deve ser passado nenhum elemento de horário
	 * @param string $str
	 * @param string $fmt
	 * @return \DateTime|null
	 */
	public static function getDate(?string $str, $fmt = 'Y-m-d'): ?\DateTime {
		if (!$str) {
			return null;
		}
		$date = \DateTime::createFromFormat($fmt.' H:i:s', $str.' 00:00:00');
		if ($date === false) {
			return null;
		}
		return $date;
	}
	
	/**
	 * Retorna um objeto de data a partir de uma string
	 * @param string $str
	 * @param string $fmt
	 * @return \DateTime|null
	 */
	public static function getDateTime(?string $str, $fmt = 'Y-m-d H:i:s', \DateTimeZone $tz = null): ?\DateTime {
		if (!$str) {
			return null;
		}
		if (!$tz) {
			$tz = new \DateTimeZone('UTC');
		}
		$date = \DateTime::createFromFormat($fmt, $str, $tz);
		if ($date === false) {
			return null;
		}
		return $date;
	}
	
	/**
	 * Retorna se a data é menor que (less than) a data atual
	 * sem levar o horário em consideração
	 * @param \DateTime $date
	 * @return bool
	 */
	public static function isLTCurrentDate(\DateTime $date): bool {
		return intval($date->format('Ymd')) < intval(self::getDateTimeNow()->format('Ymd'));
	}
	
	/**
	 * Retorna se a data é maior que (greater than) a data atual
	 * sem levar o horário em consideração
	 * @param \DateTime $date
	 * @return bool
	 */
	public static function isGTCurrentDate(\DateTime $date): bool {
		return intval($date->format('Ymd')) > intval(self::getDateTimeNow()->format('Ymd'));
	}
	
	/**
	 * Retorna se a data é maior ou igual que (greater than or equal) a data atual
	 * sem levar o horário em consideração
	 * @param \DateTime $date
	 * @return bool
	 */
	public static function isGTECurrentDate(\DateTime $date): bool {
		return intval($date->format('Ymd')) >= intval(self::getDateTimeNow()->format('Ymd'));
	}
	
	/**
	 * Retorna se a data é menor ou igual que (less than or equal) a data atual
	 * sem levar o horário em consideração
	 * @param \DateTime $date
	 * @return bool
	 */
	public static function isLTECurrentDate(\DateTime $date): bool {
		return intval($date->format('Ymd')) <= intval(self::getDateTimeNow()->format('Ymd'));
	}
	
	/**
	 * Retorna se as datas formam um período válido (início <= fim)
	 * sem levar o horário em consideração
	 * @param \DateTime $dateStart
	 * @param \DateTime $dateEnd
	 * @return bool
	 */
	public static function isValidDatePeriod(?\DateTime $dateStart, ?\DateTime $dateEnd): bool {
		if (!$dateStart || !$dateEnd) {
			return false;
		}
		return intval($dateStart->format('Ymd')) <= intval($dateEnd->format('Ymd'));
	}
	
	/**
	 * Retorna se as datas formam um período válido (início <= fim)
	 * levando o horário em consideração
	 * @param \DateTime $dateStart
	 * @param \DateTime $dateEnd
	 * @return bool
	 */
	public static function isValidDateTimePeriod(?\DateTime $dateStart, ?\DateTime $dateEnd): bool {
		if (!$dateStart || !$dateEnd) {
			return false;
		}
		return $dateStart <= $dateEnd;
	}
	
	/**
	 * Retorna se o período informado está vigente sem levar o horário em consideração
	 * @param \DateTime $date
	 * @return bool
	 */
	public static function isCurrentPeriod(?\DateTime $dateStart, ?\DateTime $dateEnd): bool {
		if (!$dateStart && !$dateEnd) { // Sem vigência definida
			return true;
		} elseif (!$dateStart) { // Não tem início de vigência, apenas fim
			return self::isGTECurrentDate($dateEnd);
		} elseif (!$dateEnd) { // Não tem final de vigência, apenas início
			return self::isLTECurrentDate($dateStart);
		}
		return self::isLTECurrentDate($dateStart) && self::isGTECurrentDate($dateEnd);
	}

	/**
	 * Retorna se tem intersecção entre as datas de dois períodos, sem considerar o horário.
	 * Obs: não valida se foi passado período válido. Recomendado fazer isso antes
	 * utilizando self::isValidDatePeriod
	 * @param \DateTime $dateStart1 Data de ínício do primeiro período
	 * @param \DateTime $dateEnd1 Data de fim do primeiro período
	 * @param \DateTime $dateStart2 Data de ínício do segundo período
	 * @param \DateTime $dateEnd2 Data de fim do segundo período
	 * @return bool
	 */
	public static function isOverlapDatePeriod(\DateTime $dateStart1, \DateTime $dateEnd1, \DateTime $dateStart2, \DateTime $dateEnd2): bool {
		$tsDateStart1 = intval($dateStart1->format('Ymd'));
		$tsDateStart2 = intval($dateStart2->format('Ymd'));
		$tsDateEnd1 = intval($dateEnd1->format('Ymd'));
		$tsDateEnd2 = intval($dateEnd2->format('Ymd'));
		return ($tsDateStart1 >= $tsDateStart2 && $tsDateStart1 <= $tsDateEnd2) // Primeiro período começa dentro do segundo
			|| ($tsDateStart2 >= $tsDateStart1 && $tsDateStart2 <= $tsDateEnd1) // Segundo período começa dentro do primeiro
			;
	}
	
	/**
	 * Retorna a data e horário atual do sistema
	 * @return \Datetime
	 */
	public static function getDateTimeNow(string $dateExpr = ''): \DateTime {
		return new \DateTime('now '.$dateExpr, new \DateTimeZone('UTC'));
	}
	
	/**
	 * Retorna a data atual do sistema
	 * @return \Datetime
	 */
	public static function getDateNow(string $dateExpr = ''): \DateTime {
		$d = self::getDateTimeNow($dateExpr);
		$d->setTime(0, 0, 0);
		return $d;
	}
	
	/**
	 * Retorna o SQL com os parâmetros interpretados
	 * @param Query $q
	 */
	public static function getParsedSql(Query $q) {
		// Parâmetros da query, na ordem em que aparecem
		$m = [];
		preg_match_all('/:[^ ,)(]+/', $q->getDQL(), $m);
		$params = $m[0] ?? [];
		
		// Valores dos parâmetros
		$paramsValues = [];
		foreach ($q->getParameters() as $param) {
			$quote = '';
			if (in_array($param->getType(), [\PDO::PARAM_STR, Connection::PARAM_STR_ARRAY, Type::DATETIME])) {
				$quote = "'";
			}
			$val = $param->getValue();
			if ($param->getType() == Type::DATETIME) {
				$val = $val->format('Y-m-d H:i:s');
			}
			if ($val === null) {
				$val = 'null';
			} elseif (is_array($val)) {
				$val = $quote.implode("{$quote},{$quote}", $val).$quote;
			} else {
				$val = $quote.$val.$quote;
			}
			$paramsValues[':'.ltrim($param->getName(), ':')] = $val;
		}
		
		// Parsing dos parâmetros no SQL
		$sqlRet = '';
		$sql = $q->getSQL();
		foreach ($params as $param) {
			$index = strpos($sql, '?');
			$sqlRet .= substr($sql, 0, $index).$paramsValues[$param];
			$sql = substr($sql, $index + 1);
		}
		$sqlRet .= $sql;
		
		return $sqlRet;
	}
	
	/**
	 * Renomeia atributos internos de uma estrutura (array/objeto).
	 * Obs: trabalha por referência, altera o conteúdo da variável que lhe for passada
	 * @param mixed &$data Estrutura para ter seus membros renomeados
	 * @param array $rename Array com as regras para renomeio, onde o índice deve ser o caminho
	 *   completo para o atributo a ser renomeado e o valor o seu novo nome.
	 *   Obs1: os renomeios são realizados na ordem em que são passados, então se o renomeio for feito
	 *   em um atributo pai, a regra seguinte já deve usar o novo nome do atributo pai. Ex:
	 *	 ['attr3' => 'attr_3', 'attr_3.sub2' => 'sub_2', 'attr_3.sub_2.subsub1' => 'sub_sub_1']
	 *   Obs2: Caso o renomeio deva ser feito em alementos de um atributo/posição que é um array,
	 *   deve ser colocado "[]" após o nome deste atributo/posição. Ex:
	 *   ['documents[].idApproval' => 'approval', 'documents[].approval.idUser' => 'user']
	 * @param string $pathSep Separador de path. Default: .
	 * @return void
	 */
	public static function renameStruct(&$data, array $rename, string $pathSep = '.'): void {
		foreach ($rename as $path => $newName) { // Processo cada regra de renomeio
			$parent = &$data;
			$path = explode($pathSep, $path);
			$count = count($path) - 1;
			for ($i = 0; $i < $count; $i++) { // Vou percorrendo o path até chegar no elemento em que o renomeio realmente deve ser feito
				if (is_array($parent) && array_key_exists($path[$i], $parent)) {
					$parent = &$parent[$path[$i]];
				} elseif (is_object($parent) && property_exists($parent, $path[$i])) {
					$parent = &$parent->{$path[$i]};
				} elseif (substr($path[$i], -2, 2) == '[]') { // Tratamento para quando o elemento é na verdade um array
					$path[$i] = substr($path[$i], 0, -2);
					if (is_array($parent) && array_key_exists($path[$i], $parent)) {
						$arrayField = &$parent[$path[$i]];
					} elseif (is_object($parent) && property_exists($parent, $path[$i])) {
						$arrayField = &$parent->{$path[$i]};
					} else {
						continue 2; // Não encontrou o atributo/índice, nada mais a fazer nesta regra
					}
					for ($j = $i; $j >= 0; $j--) { // Removo todos os caminhos anteriores
						unset($path[$j]);
					}
					foreach ($arrayField as &$element) { // Renomeio cada objeto individualmente
						self::renameStruct($element, [implode($pathSep, $path) => $newName]);
					}
					continue 2; // Mais nada a fazer
				} else {
					continue 2; // Vou para a próxima regra de rename, pois não encontrou nada no caminho informado
				}
			}
			$oldName = $path[$count];
			if ($oldName === $newName) { // Não faz sentido renomear para o mesmo nome
				continue;
			}
			if (is_array($parent) && array_key_exists($oldName, $parent)) {
				$parent[$newName] = $parent[$oldName];
				unset($parent[$oldName]);
			} elseif (is_object($parent) && property_exists($parent, $oldName)) {
				$parent->{$newName} = $parent->{$oldName};
				unset($parent->{$oldName});
			}
		} // End foreach regra de renomeio
	}
	
	/**
	 * Escreve o conteúdo (ou o dump) da variável em um arquivo
	 * @param mixed $var
	 * @param string $file
	 * @param bool $dump Indica se deve fazer var_dump ou não na variável.
	 *   Default: true
	 * @param string $mode Modo de abertura do arquivo. Default: ab
	 */
	public static function fileDump($var, string $file = null, bool $dump = true, string $mode = 'ab') {
		if ($dump) {
			ob_start();
			var_dump($var);
			$content = ob_get_clean();
			ob_end_flush();
		} else {
			$content = $var;
		}
		$rs = fopen($file, $mode);
		if ($rs) {
			fwrite($rs, $content);
			fclose($rs);
		}
	}
	
	/**
	 * Faz dump de uma váriável gerando html de retorno e controlando o máximo de
	 * variáveis anihadas que vai processar no dump
	 * @param mixed $var
	 * @param int $maxDepth
	 * @return string
	 */
	public static function dumpString($var, int $maxDepth = 4): string {
		return Debug::dump($var, $maxDepth, false, false);
	}
	
	public static function isValidCNPJ(string $cnpj) {
		$cnpj = preg_replace('/[^0-9]/', '', $cnpj);
		if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) { // Tamanho inválido ou sequência de dígitos todos iguais
			return false;
		}
		// Valida primeiro dígito verificador
		for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
			$soma += $cnpj[$i] * $j;
			$j = ($j == 2) ? 9 : $j - 1;
		}
		$resto = $soma % 11;
		if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
			return false;
		}
		// Valida segundo dígito verificador
		for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
			$soma += $cnpj[$i] * $j;
			$j = ($j == 2) ? 9 : $j - 1;
		}
		$resto = $soma % 11;
		return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
	}
	
	public static function isValidCPF(string $cpf) {
		$cpf = preg_replace('/[^0-9]/', '', $cpf);
		if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) { // Tamanho inválido ou sequência de dígitos todos iguais
			return false;
		}
		// Calcula e confere primeiro dígito verificador
		for ($i = 0, $j = 10, $soma = 0; $i < 9; $i++, $j--) {
			$soma += $cpf[$i] * $j;
		}
		$resto = $soma % 11;
		if ($cpf[9] != ($resto < 2 ? 0 : 11 - $resto)) {
			return false;
		}
		// Calcula e confere segundo dígito verificador
		for ($i = 0, $j = 11, $soma = 0; $i < 10; $i++, $j--) {
			$soma += $cpf[$i] * $j;
		}
		$resto = $soma % 11;
		return $cpf[10] == ($resto < 2 ? 0 : 11 - $resto);
	}
	
	/**
	 * Realiza uma requisição HTTP com os dados informados
	 * @param string $method Método HTTP a ser utilizado
	 * @param string $url URL para a qual a requisição será feita
	 * @param array|string $data Dados da requisição em um array associativo onde o
	 *   índice é o nome do campo ou então a string que deve ser enviada, em sua forma bruta
	 * @param array $headers Cabeçalhos da requisição em um array, onde o valor
	 *   de cada elemento do array deve conter um header completo
	 * @return string Retornará a resposta da requisição ou false em caso
	 *   de qualquer erro ocorrido
	 * @throws \Exception caso qualquer erro
	 */
	public static function httpRequest(string $method, string $url, $data = [], array $headers = []) {
		$curl = curl_init();
		if (!$curl) {
			throw new \Exception('Failed to initialize curl');
		}
		
		$opts = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true];
		if ($data) {
			if ($method == 'GET') {
				$url .= (strpos($url, '?') === false ? '?' : '&').http_build_query($data);
			} else {
				$opts[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
			}
		}
		if ($headers) {
			$opts[CURLOPT_HTTPHEADER] = $headers;
		}
		$opts[CURLOPT_URL] = $url;
		
		if (!curl_setopt_array($curl, $opts)) {
			$msg = curl_error($curl);
			curl_close($curl);
			throw new \Exception($msg);
		}
		
		$result = curl_exec($curl);
		if ($result === false) {
			$msg = curl_error($curl);
			curl_close($curl);
			throw new \Exception($msg);
		}
		curl_close($curl);
	
		return $result;
	}
	
	/**
	 * Convert um XML em um array associativo
	 * @param string|SimpleXMLElement $xml
	 * @return array|null
	 */
	public static function xmlToArray($xml, $out = []): ?array {
		$xmlObject = is_object($xml) ? $xml : simplexml_load_string($xml);
		if (!$xmlObject) {
			return null;
		}
		foreach ((array)$xmlObject as $index => $node) {
			$out[$index] = (is_object($node) || is_array($node)) ? self::xmlToArray($node) : $node;
		}
		return $out;
	}
	
	/**
	 * Utilizar para passar verificar o tamanho de uma string.
	 *
	 * @param string|null $s
	 * @param string $encoding
	 * @return int
	 */
	public static function length(?string $s, string $encoding = 'UTF-8'): int {
		return empty($s) ? 0 : mb_strlen($s, $encoding) ?? 0;
	}
	
	/**
	 * Utilizar para passar uma estring para minúsculo
	 * @param string $s
	 * @param string $encoding
	 * @return string|NULL
	 */
	public static function lower(?string $s, string $encoding = 'UTF-8'): ?string {
		return mb_strtolower($s, $encoding);
	}
	
	/**
	 * Utilizar para passar uma estring para maiúsculo
	 * @param string $s
	 * @param string $encoding
	 * @return string|NULL
	 */
	public static function upper(?string $s, string $encoding = 'UTF-8'): ?string {
		return mb_strtoupper($s, $encoding);
	}
	
	/**
	 * Utilizar para remover espaços em branco desnecessários de uma string, assim como quebras de linha
	 * @param string $s
	 * @return string|NULL
	 */
	public static function sanitizeString(?string $s): ?string {
		return str_replace(["\r", "\n"], ["", " "], preg_replace('/ {2,}/', ' ', $s));
	}
	
	/**
	 * Remove os caracteres mais comumente utilizados para formatar informações
	 * @param string $s
	 * @return string
	 */
	public static function removeFormatting(?string $s): string {
		return str_replace(['.', '-', '/', ' ', '(', ')'], '', $s);
	}
	
	/**
	 * Remove acentuação de uma string da forma mais segura para a codificação UTF-8
	 * @param string $s
	 * @return string|NULL
	 */
	public static function stripAccents(?string $s): ?string {
		if (!preg_match('/[\x80-\xff]/', $s)) { // Nenhum caracter especial
			return $s;
		}
		$chars = [
			// Decompositions for Latin-1 Supplement
			chr(194).chr(170) => 'a', chr(194).chr(186) => 'o',
			chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
			chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
			chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
			chr(195).chr(134) => 'AE',chr(195).chr(135) => 'C',
			chr(195).chr(136) => 'E', chr(195).chr(137) => 'E',
			chr(195).chr(138) => 'E', chr(195).chr(139) => 'E',
			chr(195).chr(140) => 'I', chr(195).chr(141) => 'I',
			chr(195).chr(142) => 'I', chr(195).chr(143) => 'I',
			chr(195).chr(144) => 'D', chr(195).chr(145) => 'N',
			chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
			chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
			chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
			chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
			chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
			chr(195).chr(158) => 'TH',chr(195).chr(159) => 's',
			chr(195).chr(160) => 'a', chr(195).chr(161) => 'a',
			chr(195).chr(162) => 'a', chr(195).chr(163) => 'a',
			chr(195).chr(164) => 'a', chr(195).chr(165) => 'a',
			chr(195).chr(166) => 'ae',chr(195).chr(167) => 'c',
			chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
			chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
			chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
			chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
			chr(195).chr(176) => 'd', chr(195).chr(177) => 'n',
			chr(195).chr(178) => 'o', chr(195).chr(179) => 'o',
			chr(195).chr(180) => 'o', chr(195).chr(181) => 'o',
			chr(195).chr(182) => 'o', chr(195).chr(184) => 'o',
			chr(195).chr(185) => 'u', chr(195).chr(186) => 'u',
			chr(195).chr(187) => 'u', chr(195).chr(188) => 'u',
			chr(195).chr(189) => 'y', chr(195).chr(190) => 'th',
			chr(195).chr(191) => 'y', chr(195).chr(152) => 'O',
			// Decompositions for Latin Extended-A
			chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
			chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
			chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
			chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
			chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
			chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
			chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
			chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
			chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
			chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
			chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
			chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
			chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
			chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
			chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
			chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
			chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
			chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
			chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
			chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
			chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
			chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
			chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
			chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
			chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
			chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
			chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
			chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
			chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
			chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
			chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
			chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
			chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
			chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
			chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
			chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
			chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
			chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
			chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
			chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
			chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
			chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
			chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
			chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
			chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
			chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
			chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
			chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
			chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
			chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
			chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
			chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
			chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
			chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
			chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
			chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
			chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
			chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
			chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
			chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
			chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
			chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
			chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
			chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
			// Decompositions for Latin Extended-B
			chr(200).chr(152) => 'S', chr(200).chr(153) => 's',
			chr(200).chr(154) => 'T', chr(200).chr(155) => 't',
			// Euro Sign
			chr(226).chr(130).chr(172) => 'E',
			// GBP (Pound) Sign
			chr(194).chr(163) => '',
			// Vowels with diacritic (Vietnamese)
			// unmarked
			chr(198).chr(160) => 'O', chr(198).chr(161) => 'o',
			chr(198).chr(175) => 'U', chr(198).chr(176) => 'u',
			// grave accent
			chr(225).chr(186).chr(166) => 'A', chr(225).chr(186).chr(167) => 'a',
			chr(225).chr(186).chr(176) => 'A', chr(225).chr(186).chr(177) => 'a',
			chr(225).chr(187).chr(128) => 'E', chr(225).chr(187).chr(129) => 'e',
			chr(225).chr(187).chr(146) => 'O', chr(225).chr(187).chr(147) => 'o',
			chr(225).chr(187).chr(156) => 'O', chr(225).chr(187).chr(157) => 'o',
			chr(225).chr(187).chr(170) => 'U', chr(225).chr(187).chr(171) => 'u',
			chr(225).chr(187).chr(178) => 'Y', chr(225).chr(187).chr(179) => 'y',
			// hook
			chr(225).chr(186).chr(162) => 'A', chr(225).chr(186).chr(163) => 'a',
			chr(225).chr(186).chr(168) => 'A', chr(225).chr(186).chr(169) => 'a',
			chr(225).chr(186).chr(178) => 'A', chr(225).chr(186).chr(179) => 'a',
			chr(225).chr(186).chr(186) => 'E', chr(225).chr(186).chr(187) => 'e',
			chr(225).chr(187).chr(130) => 'E', chr(225).chr(187).chr(131) => 'e',
			chr(225).chr(187).chr(136) => 'I', chr(225).chr(187).chr(137) => 'i',
			chr(225).chr(187).chr(142) => 'O', chr(225).chr(187).chr(143) => 'o',
			chr(225).chr(187).chr(148) => 'O', chr(225).chr(187).chr(149) => 'o',
			chr(225).chr(187).chr(158) => 'O', chr(225).chr(187).chr(159) => 'o',
			chr(225).chr(187).chr(166) => 'U', chr(225).chr(187).chr(167) => 'u',
			chr(225).chr(187).chr(172) => 'U', chr(225).chr(187).chr(173) => 'u',
			chr(225).chr(187).chr(182) => 'Y', chr(225).chr(187).chr(183) => 'y',
			// tilde
			chr(225).chr(186).chr(170) => 'A', chr(225).chr(186).chr(171) => 'a',
			chr(225).chr(186).chr(180) => 'A', chr(225).chr(186).chr(181) => 'a',
			chr(225).chr(186).chr(188) => 'E', chr(225).chr(186).chr(189) => 'e',
			chr(225).chr(187).chr(132) => 'E', chr(225).chr(187).chr(133) => 'e',
			chr(225).chr(187).chr(150) => 'O', chr(225).chr(187).chr(151) => 'o',
			chr(225).chr(187).chr(160) => 'O', chr(225).chr(187).chr(161) => 'o',
			chr(225).chr(187).chr(174) => 'U', chr(225).chr(187).chr(175) => 'u',
			chr(225).chr(187).chr(184) => 'Y', chr(225).chr(187).chr(185) => 'y',
			// acute accent
			chr(225).chr(186).chr(164) => 'A', chr(225).chr(186).chr(165) => 'a',
			chr(225).chr(186).chr(174) => 'A', chr(225).chr(186).chr(175) => 'a',
			chr(225).chr(186).chr(190) => 'E', chr(225).chr(186).chr(191) => 'e',
			chr(225).chr(187).chr(144) => 'O', chr(225).chr(187).chr(145) => 'o',
			chr(225).chr(187).chr(154) => 'O', chr(225).chr(187).chr(155) => 'o',
			chr(225).chr(187).chr(168) => 'U', chr(225).chr(187).chr(169) => 'u',
			// dot below
			chr(225).chr(186).chr(160) => 'A', chr(225).chr(186).chr(161) => 'a',
			chr(225).chr(186).chr(172) => 'A', chr(225).chr(186).chr(173) => 'a',
			chr(225).chr(186).chr(182) => 'A', chr(225).chr(186).chr(183) => 'a',
			chr(225).chr(186).chr(184) => 'E', chr(225).chr(186).chr(185) => 'e',
			chr(225).chr(187).chr(134) => 'E', chr(225).chr(187).chr(135) => 'e',
			chr(225).chr(187).chr(138) => 'I', chr(225).chr(187).chr(139) => 'i',
			chr(225).chr(187).chr(140) => 'O', chr(225).chr(187).chr(141) => 'o',
			chr(225).chr(187).chr(152) => 'O', chr(225).chr(187).chr(153) => 'o',
			chr(225).chr(187).chr(162) => 'O', chr(225).chr(187).chr(163) => 'o',
			chr(225).chr(187).chr(164) => 'U', chr(225).chr(187).chr(165) => 'u',
			chr(225).chr(187).chr(176) => 'U', chr(225).chr(187).chr(177) => 'u',
			chr(225).chr(187).chr(180) => 'Y', chr(225).chr(187).chr(181) => 'y',
			// Vowels with diacritic (Chinese, Hanyu Pinyin)
			chr(201).chr(145) => 'a',
			// macron
			chr(199).chr(149) => 'U', chr(199).chr(150) => 'u',
			// acute accent
			chr(199).chr(151) => 'U', chr(199).chr(152) => 'u',
			// caron
			chr(199).chr(141) => 'A', chr(199).chr(142) => 'a',
			chr(199).chr(143) => 'I', chr(199).chr(144) => 'i',
			chr(199).chr(145) => 'O', chr(199).chr(146) => 'o',
			chr(199).chr(147) => 'U', chr(199).chr(148) => 'u',
			chr(199).chr(153) => 'U', chr(199).chr(154) => 'u',
			// grave accent
			chr(199).chr(155) => 'U', chr(199).chr(156) => 'u',
		];
		
		return strtr($s, $chars);
	}
	
	/**
	 * Faz trim em todos os elementos do array, por referência
	 * @param array &$a
	 */
	public static function trimArrayRef(array &$a): void {
		array_walk($a, function (&$v) { $v = trim($v); });
	}
	
	/**
	 * Retorna o número de casas decimais que um float está utilizando
	 * @param float $n
	 * @return int
	 */
	public static function numberOfDecimals(float $n): int {
		for ($decimals = 0; $n != round($n, $decimals); $decimals++) {
			if ($decimals > 18) { // Evitar loop infinito
				break;
			}
		}
		return $decimals;
	}
	
	/**
	 * Cria um arquivo temporário através de uma string
	 * @param string $s
	 * @return resource|null Retornará null caso qualquer problema ocorra
	 */
	public static function createTmpFile(string $s) {
		$rs = tmpfile();
		if ($rs) {
			if (fwrite($rs, $s) === false) {
				fclose($rs);
				return null;
			}
			if (!rewind($rs)) {
				fclose($rs);
				return null;
			}
			return $rs;
		}
		return null;
	}
	
	/**
	 * Retorna a mensagem de erro da exceção junto com informações de debug
	 * @param \Throwable $e
	 * @return string
	 */
	public static function getMessageWithDebugInfo(\Throwable $e): string {
		$extra = '';
		if ($e instanceof BOException) {
			$extra .= ' (debug: '.$e->getDebugMessage().')';
		}
		$extra .= '<br><br>Exception:<br>'.Utils::dumpString($e).'<br><br>';
		return $e->getMessage().$extra;
	}
	
	/**
	 * Verifica se em um array unidimensional com valores escalares, tem valores repetidos para as índices
	 * que formam uma chave composta
	 * @param array $array
	 * @param array $indexesToCheck Se não informado, verifica no array como um todo, batendo posição por posição
	 * @param bool $ignoreNulls Se true, vai ignorar posições do array em que a chave não tiver setada.
	 *   Seria comportamento semelhante ao de bancos de dados, em que null = null retorna sempre false
	 */
	public static function hasDuplicatesInArray(array $array, array $compoundKeyIndexes = [], bool $ignoreNulls = false) {
		if (!$compoundKeyIndexes) {
			return count($array) !== count(array_unique($array));
		}
		$elementsHashes = [];
		foreach ($array as $el) {
			$el = (array)$el;
			$hash = '';
			foreach ($compoundKeyIndexes as $keyIndex) {
				if (!isset($el[$keyIndex]) && $ignoreNulls) { // Null é sempre diferente de null, então se não tem alguma das chaves, só ignora
					continue 2;
				}
				$hash .= ($el[$keyIndex] ?? '').'|';
			}
			$elementsHashes[] = $hash;
		}
		return count($elementsHashes) !== count(array_unique($elementsHashes));
	}
	
	/**
	 * @param array $rows
	 * @param string $fileName
	 * @return string
	 */
	public static function generateCSVFile(array $rows, string $fileName = 'cvs_export.csv') : string {
		$pathFileName = '../tmp/' . $fileName;
		$fp = fopen($pathFileName, 'w');
		foreach ($rows as $row) {
			fputcsv($fp, $row, ";");
		}
		fclose($fp);
		return $pathFileName;
	}
	
	/**
	 * Retorna uma string random de 5 letras, bom para usar com o doctrine.
	 *
	 * @return string
	 */
	public static function getRandomString(): string {
		return substr(md5(microtime()),rand(0,26),5);
	}
	
	/**
	 * Formata uma data externa do sistema
	 * @param string $date
	 * @param string $format
	 * @return string
	 * @throws \Exception
	 */
	public static function formatDate(string $date, string $format = 'd/m/Y'): string {
		$date = new \DateTime($date);
		return $date->format($format);
	}

	/**
	 * Retorna apenas o int a partir de uma string.
	 *
	 * @param int $value
	 * @return int|null
	 * @throws \Exception
	 */
	public static function onlyNumbers($value): ?int {
		if(empty($value)){
			return null;
		}
		$int = preg_replace('/[^0-9]/', "", $value);
		return !empty($int) ? intval($int) : null;
	}
	
	/**
	 * Recebe um valor de quantidade e o transforma para KG.
	 *
	 * e.g.: Se possuir 2 ou menos números inteiros deve ser convertido: * 1000
	 * (muito difícil ocorrer uma carga de 99KG apenas, assumimemos que é TON ao invés de KG).
	 *
	 * @param float $value
	 * @return float|null
	 */
	public static function weightFormat(float $value): ?float {
		if (empty($value)) {
			return null;
		}
		$intLength = strlen(intval($value));
		if ($intLength <= 2) {
			$value = $value * 1000;
		}
		return $value;
	}
	
	/**
	 * Carrega uma string para uma estrutura de xml.
	 *
	 * @param string $content
	 * @return \SimpleXMLElement|null
	 */
	public static function getSimpleXmlFromString(?string $content): ?\SimpleXMLElement {
		if (empty($content)) {
			return null;
		}
		
		libxml_use_internal_errors(true);
		
		$xml = simplexml_load_string($content);
		if (!$xml) {
			libxml_clear_errors(); // $xml_errors = libxml_get_errors();
			$xml = null;
		}
		
		return $xml;
	}

	/**
	 * Extrai o CNPJ raiz do documento (primeiros 8 digitos)
	 *
	 * @param string $document
	 * @return string
	 */
	public static function getRootCNPJ(string $document): string {
		return substr($document, 0, 8);
	}
	
	/**
	 * Obtêm os dados do arquivo, formatando da forma correta para leitura.
	 *
	 * @param File $file
	 * @param bool $applyUTF8
	 *
	 * @return string
	 */
	public static function getFileContent(File $file, bool $applyUTF8 = true): string {
		$content = $file->getFile(false);
		if ($applyUTF8) {
			$content = Encoding::toUTF8($content);
		}
		return Encoding::removeBOM($content);
	}

	/**
	 * Separa um nome em primeiro e último nome para nomes de empresa
	 * @param array ['first', 'last']
	 */
	public static function splitFirstLastNameCompany(string $name): array {
		$f = $l = '';
		$name = trim(preg_replace('/\s+/', ' ', str_replace('-', '', $name)));

		if (strlen($name) <= 40) {
			$f = $name;
			$l = '-';
		} else {
			$a = explode(' ', $name);
			// Primeiro nome
			foreach ($a as $i => $n) {
				$n = trim($n);
				if (strlen($f.' '.$n) <= 40) { // Continuo conseguindo encaixar nomes
					$f = trim($f.' '.$n);
					unset($a[$i]); // Removo nome já utilizado
				} elseif (strlen($n) > 40 && $i === 0) { // O primeiro nome é muito grande
					$f = substr($n, 0, 40);
					unset($a[$i]); // Removo nome já utilizado
				} else { // Preciso colocar o resto no último nome
					break;
				}
			}
			// Último nome
			if (count($a) === 0) { // Ficou tudo no primeiro nome
				$l = '-';
			} else {
				$l = substr(implode(' ', $a), 0, 80); // Pego o máximo que der dos nomes que sobraram
			}
		}
		return ['first' => $f, 'last' => $l];
	}

/**
	 * Redimensiona uma imagem a partir do base64 se a altura for maior do que o valor informado (ou 300). cc retorna o base64
	 *
	 * @param array $data = [
	 * 		'base64' => imagem em base64
	 * 		'maxSize' => tamanho máximo de altura que a imagem pode ter para não ser redimensionada
	 * 		'extension' => extensão do imagem, png/jpg...
	 * ]
	 * @return String
	 */
	public static function resizeImage(array $data): string {
		$base64 = str_replace("data:image/".$data['extension'].";base64,", "", $data['base64']);
		$imageData = base64_decode($base64);
		$src = imagecreatefromstring($imageData);

		$width = imagesx($src);
		$height = imagesy($src);
		$aspect_ratio = $height/$width;
		if ($height <= $data['maxSize']) {
			return $data['base64'];
		}

		$new_w = $data['maxSize'];
		$new_h = abs($new_w * $aspect_ratio);
		$img = imagecreatetruecolor($new_w,$new_h);
		imagecopyresized($img, $src, 0, 0, 0, 0, $new_w, $new_h, $width, $height);

		ob_start();
		if ($data['extension'] == "jpg" || $data['extension'] == "jpeg") {
			imagejpeg($img);
		} else if ($data['extension'] == "png") {
			imagepng($img);
		}
		$resizedImage = ob_get_contents();
		ob_end_clean();

		return "data:image/".$data['extension'].";base64," . base64_encode($resizedImage);
	}

	public static function prepareEntityArrayForResponse(array $data, array $expand = [], $rename = false): array {
		if (isset($data['records'])) {
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
	 * Adiciona a máscara em número de cnpj.
	 *
	 * @param string $cnpj
	 * @return string
	 */
	public static function formatCnpj(string $cnpj): string {
		return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $cnpj);
	}
}
