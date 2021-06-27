<?php
namespace App\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\SqlWalker;

class Round extends FunctionNode {
	
	private $firstExpression = null;
	private $secondExpression = null;
	
	public function parse(Parser $parser) {
		$lexer = $parser->getLexer();
		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);
		$this->firstExpression = $parser->ArithmeticPrimary();
		
		if (Lexer::T_COMMA === $lexer->lookahead['type']) {
			$parser->match(Lexer::T_COMMA);
			$this->secondExpression = $parser->ArithmeticPrimary();
		}
		
		$parser->match(Lexer::T_CLOSE_PARENTHESIS);
	}
	
	public function getSql(SqlWalker $sqlWalker) {
		if (null !== $this->secondExpression) {
			return 'ROUND('
				. $this->firstExpression->dispatch($sqlWalker)
				. ', '
				. $this->secondExpression->dispatch($sqlWalker)
				. ')';
		}
		
		return 'ROUND(' . $this->firstExpression->dispatch($sqlWalker) . ')';
	}
	
}