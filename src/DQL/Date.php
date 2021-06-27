<?php
namespace App\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\SqlWalker;

class Date extends FunctionNode {
	public $timestamp = null;
	public $pattern = null;
	
	public function parse(Parser $parser) {
		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);
		$this->timestamp = $parser->ArithmeticPrimary();
		$parser->match(Lexer::T_CLOSE_PARENTHESIS);
	}
	
	public function getSql(SqlWalker $sqlWalker) {
		return 'date('.$this->timestamp->dispatch($sqlWalker).')';
	}
	
}