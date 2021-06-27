#!/usr/bin/env php
<?php
function getValidatorType($type) {
	switch ($type) {
		case 'string':
			return 'Validator::STRING';
		case 'int':
			return 'Validator::INTEGER';
		case 'bool':
			return 'Validator::BOOLEAN';
		case 'float':
			return 'Validator::FLOAT';
	}
	return 'Validator::OBJECT';
}

try {
	if (count($argv) < 2) {
		throw new Exception("You must provide the files to proccess.\n\nSynopsis: entityFixer.php filename1 filename2 ...");
	}
	$templateFile =  __DIR__.'/templates/repository_template.php';
	$templateRepo = file_get_contents($templateFile);
	if (!$templateRepo) {
		throw new Exception('Error reading repository template file "'.$templateFile.'"');
	}
	foreach ($argv as $indArgv => $file) {
		if ($indArgv == 0) {
			continue;
		}
		// Validações dos arquivos e extração do nome e path para identificar schema e entity
		if (!file_exists($file) || !is_file($file)) {
			throw new Exception('Argument "'.$file.'"is not a file');
		}
		$tmp = explode('/', $file);
		$count = count($tmp);
		if ($count < 1) {
			throw new Exception('Invalid argument "'.$file.'"');
		}
		$fileName = str_replace('"', '', $tmp[$count - 1]); // As vezes gera os arquivos tipo assim: Common."user".php
		if ($count == 1) {
			$filePath = './';
		} else {
			unset($tmp[$count - 1]);
			$filePath = implode('/', $tmp).'/';
		}
		
		$tmp = explode('.', $fileName);
		$count = count($tmp);
		if ($count < 2 || $tmp[$count - 1] != 'php') {
			throw new Exception('File "'.$file.'" is not a .php file');
		} elseif ($count > 3) {
			throw new Exception('File "'.$file.'" has a name in an unexpected format. Expected format is: Schema.entity.php or entity.php');
		}
		$entity = ucfirst($tmp[$count - 2]);
		if ($count == 3) {
			$schema = ucfirst($tmp[0]);
		} else {
			$schema = '';
		}
		
		// Leitura do conteúdo
		$fileContents = file_get_contents($file);
		if (!$fileContents) {
			throw new Exception('Error getting file contents from "'.$file.'" or the file is empty');
		}
		
		// Substitui espaços por TABs
		$fileContents = str_replace('    ', "\t", $fileContents);
		
		// Casos específicos
		$fileContents = preg_replace('#\\\?Common\."user"#', 'Common.user', $fileContents);
		
		// Remove o schema da frente dos nomes das entidades
		$entitiesUse = [];
		$schemasApp = 'Common|Security|Provider|Offer|Alert|Log|Premium|Ad|Finance|Outsource|Training|Markerting'; // Colocar aqui TODOS os schemas da aplicação, exceto o public
		$fileContents = preg_replace_callback(
			'#targetEntity="('.$schemasApp.')\.([a-zA-Z]+)"#',
			function ($m) {
				global $entitiesUse, $schema;
				
				if ($schema != $m[1]) { // Tem que incluir as entidades de outros schemas
					$entitiesUse[$m[1].'|'.$m[2]] = 'use App\Entity\\'.$m[1].'\\'.ucfirst($m[2]).';';
					return 'targetEntity="App\Entity\\'.$m[1].'\\'.ucfirst($m[2]).'"';
				}
				return 'targetEntity="'.ucfirst($m[2]).'"'; // Não precisa do caminho completo
			},
			$fileContents
		);
		$fileContents = preg_replace_callback(
			'#\\\?('.$schemasApp.')\.([a-z])#',
			function ($m) { return ucfirst($m[2]); },
			$fileContents
		);
		
		// Coloca o abre chaves para a linha de cima
		$fileContents = preg_replace("#\n\t*{#", " {", $fileContents);
		
		// Ajusta o nome da sequence
		$m = [];
		$ok = preg_match('#Table\(name="([a-z_.]+)"#', $fileContents, $m);
		if (!$ok) {
			throw new Exception('Cannot get the table name');
		}
		$seqName = $m[1].'_seq';
		$fileContents = preg_replace('#sequenceName="([a-z_.]+)"#', 'sequenceName="'.$seqName.'"', $fileContents);
		
		$entitiesUse = implode("\n", $entitiesUse);
		if (trim($entitiesUse) != '') {
			$entitiesUse = "\n".$entitiesUse;
		}
		// Faz extender a DefaultEntity
		$fileContents = str_replace(
			["class {$entity}", 'as ORM;', "@ORM\Entity\n"],
			[
				"class {$entity} extends DefaultEntity",
				"as ORM;\nuse App\Entity\DefaultEntity;\nuse App\Utils\Validator;".$entitiesUse,
				"@ORM\Entity\n * @ORM\ChangeTrackingPolicy(\"DEFERRED_EXPLICIT\")\n"
			],
			$fileContents
		);
		
		// Obtenho todos os atributos privados e suas configurações
		$m = [];
		$qtAttrs = preg_match_all('#private \$([a-zA-Z0-9]+)#', $fileContents, $m);
		if (!$qtAttrs) {
			throw new Exception('Cannot get attributes in file '.$file);
		}
		$attrs = [];
		foreach ($m[1] as $attr) {
			// Pego o PHPDoc para ver o tipo e se é nullable
			$m2 = [];
			$ok = preg_match('#/\*\*\n((\t \*.*)\n)+\tprivate \$'.$attr.'[ ;]#', $fileContents, $m2);
			if (!$ok) {
				throw new Exception('Cannot get PHPDoc for attribute $'.$attr);
			}
			// Pego o @var
			$m3 = [];
			$ok = preg_match('#@var (.*)#', $m2[0], $m3);
			if (!$ok) {
				throw new Exception('Cannot get var type for attribute $'.$attr);
			}
			$tmp = explode('|', $m3[1]); // Quando é nullable, vem com o tipo|null 
			$attrs[$attr] = [
				'type' => $tmp[0],
				'nullable' => count($tmp) > 1,
				'validatorType' => getValidatorType($tmp[0]),
				'isPK' => strpos($m2[0], '@ORM\Id') !== false,
			];
			
			// Pego o precision, se tiver
			$m3 = [];
			$ok = preg_match('#precision=([0-9]+)#', $m2[0], $m3);
			if ($ok) { // Se achou precision
				$attrs[$attr]['precision'] = $m3[1];
			}
			
			// Pego o scale, se tiver
			$m3 = [];
			$ok = preg_match('#scale=([0-9]+)#', $m2[0], $m3);
			if ($ok) { // Se achou scale
				$attrs[$attr]['scale'] = $m3[1];
			}
			
			// Pego o nome da coluna
			$m3 = [];
			$ok = preg_match('#name="([a-z_]+)"#', $m2[0], $m3);
			if (!$ok) { // Se não  achou name
				throw new Exception('Cannot get column name for attribute $'.$attr);
			}
			$attrs[$attr]['columnName'] = $m3[1];
			
			// Pego o tipo da coluna
			$m3 = [];
			$ok = preg_match('#type="([a-z_]+)"#', $m2[0], $m3);
			if (!$ok) { // Se achou tipo da coluna
				if (strpos($m2[0], 'ORM\JoinColumns') !== false) { // Coluna de ligação seta um objeto e não vem com o tipo no PHPDoc
					$m3[1] = 'integer'; // Pode não ser, mas geralmente vai ser
				} else {
					throw new Exception('Cannot get column type for attribute $'.$attr);
				}
			}
			if ($m3[1] == 'datetime') {
				$m3[1] = 'timestamp';
			}
			$attrs[$attr]['columnType'] = $m3[1];
			
			// Pego o length, se tiver
			$m3 = [];
			$ok = preg_match('#length=([0-9]+)#', $m2[0], $m3);
			if ($ok) { // Se achou length
				$attrs[$attr]['length'] = $m3[1];
			} elseif ($attrs[$attr]['columnType'] === 'integer' && substr($attrs[$attr]['columnName'], 0, 2) !== 'id') {
				$attrs[$attr]['length'] = 2147483647; // Tamanho máximo de um tipo integer no postgres
			} elseif ($attrs[$attr]['columnType'] === 'decimal' && isset($attrs[$attr]['precision']) && isset($attrs[$attr]['scale'])) {
				$attrs[$attr]['length'] = str_pad('', $attrs[$attr]['precision'] - $attrs[$attr]['scale'], '9', STR_PAD_LEFT)
					.'.'.str_pad('', $attrs[$attr]['scale'], '9', STR_PAD_LEFT); // Tamanho máximo definido pelo tipo numeric
			}
		}
		//var_dump($attrs);exit;
		
		// Gero os getters e setters e processo as informações do ORM
		$gs = '';
		$ormInfo = [];
		foreach ($attrs as $attr => $config) {
			// Informações do ORM
			$svlen = isset($config['length']) ? ", 'length' => [".($config['nullable'] || in_array($config['columnType'], ['integer', 'decimal']) ? '0' : '1').", {$config['length']}]" : '';
			$svreq = $config['nullable'] ? '' : ", 'requireFilled' => true";
			$snull = $config['nullable'] ? 'true' : 'false';
			$slen = $config['length'] ?? 'null';
			$sprec = $config['precision'] ?? 'null';
			$sscale = $config['scale'] ?? 'null';
			$spk = $config['isPK'] ? 'true' : 'false';
			$ormInfo[] = "'$attr' => [
				'pk' => {$spk},
				'columnName' => '{$config['columnName']}',
				'columnType' => '{$config['columnType']}',
				'length' => {$slen},
				'precision' => {$sprec},
				'scale' => {$sscale},
				'nullable' => {$snull},
				'validationRule' => ['type' => {$config['validatorType']}{$svlen}{$svreq}]
			]";
			
			// Getters e setters
			$Attr = ucfirst($attr);
			$null = $config['nullable'] ? '?' : '';
			$type = $config['type'];
			$gs .= "
	public function get{$Attr}(): ?{$type} {
		return \$this->{$attr};
	}
	
	public function set{$Attr}({$null}{$type} \${$attr}): self {
		\$this->{$attr} = \${$attr};
		return \$this;
	}
";
		} // End foreach atributo
		
		// Gero métodos que as entidades devem implementar
		$ormMethod = "
	public function getOrmInfo(): array {
		return [
			\${ORM_INFO}
		];
	}
";
		$orm = str_replace('${ORM_INFO}', implode(",\n\t\t\t", $ormInfo).",", $ormMethod);
		
		// Removo linhas em branco desnecessárias no final do arquivo
		$fileContents = str_replace("\n\n\n}\n", "\n}", $fileContents);
		
		// Coloco os métodos gerados no arquivo
		$fileContents = preg_replace('#^}$#m', $orm.$gs.'}', $fileContents);
		
		// Gera o arquivo de repositório
		$contentsRepo = str_replace(array('${SCHEMA}', '${ENTITY}'), array($schema, $entity), $templateRepo);
		
		// Salva os arquivos
		$phpEntity = $filePath.($schema ? '' : '_').$entity.'.php';
		$phpRepo = $filePath.$entity.'Repository.php';
		if (file_exists($phpEntity)) {
			throw new Exception('File already exists "'.$phpEntity.'"');
		}
		if (file_exists($phpRepo)) {
			throw new Exception('File already exists "'.$phpRepo.'"');
		}
		if (!file_put_contents($phpEntity, $fileContents)) {
			throw new Exception('Error saving new entity file "'.$phpEntity.'"');
		}
		if (!file_put_contents($phpRepo, $contentsRepo)) {
			throw new Exception('Error saving new repository file "'.$phpRepo.'"');
		}
	} // End foreach arquivo
	echo "OK\n";
} catch (Exception $e) {
	echo $e->getMessage()."\n";
}