<?php
namespace App\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\SqlWalker;

class GetConfVal extends FunctionNode {
	public $confName = null;
	public $companyGroupAlias = null;
	public $companyAlias = null;
	public $isStrict = null;
	public $typeCast = null;
	
	public function parse(Parser $parser) {
		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);
		$this->confName = $parser->ArithmeticPrimary();
		$parser->match(Lexer::T_COMMA);
		$this->companyGroupAlias = $parser->ArithmeticPrimary();
		$parser->match(Lexer::T_COMMA);
		$this->companyAlias = $parser->ArithmeticPrimary();
		$parser->match(Lexer::T_COMMA);
		$this->isStrict = $parser->ArithmeticPrimary();
		$parser->match(Lexer::T_COMMA);
		$this->typeCast = $parser->ArithmeticPrimary();
		$parser->match(Lexer::T_CLOSE_PARENTHESIS);
	}
	
	public function getSql(SqlWalker $sqlWalker) {
		$isStrict = $this->isStrict->dispatch($sqlWalker) === 't';
		return "(
			SELECT cfg.value
			FROM common.config cfg
			WHERE cfg.internal_name = {$this->confName->dispatch($sqlWalker)}
				AND ("
					.($isStrict ? "COALESCE(cfg.id_company, 0) = COALESCE({$this->companyAlias->dispatch($sqlWalker)}, 0)" : "cfg.id_company = {$this->companyAlias->dispatch($sqlWalker)}")
					.($isStrict ? " AND COALESCE(cfg.id_company_group, 0) = COALESCE({$this->companyGroupAlias->dispatch($sqlWalker)}, 0)" : " OR cfg.id_company_group = {$this->companyGroupAlias->dispatch($sqlWalker)}")
					.($isStrict ? "" : " OR (cfg.id_company IS NULL AND cfg.id_company_group IS NULL)")
				.")
			ORDER BY
				CASE WHEN cfg.id_company IS NOT NULL THEN 0
					WHEN cfg.id_company_group IS NULL THEN 1
					ELSE 2
				END ASC
			LIMIT 1
		)::".$this->typeCast->value;
	}
	
}