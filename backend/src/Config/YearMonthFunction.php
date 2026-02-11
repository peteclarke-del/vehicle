<?php

namespace App\Config;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Custom DQL function: YEAR_MONTH(dateExpr)
 * Returns 'YYYY-MM' string from a date/datetime column.
 *
 * Works on both MySQL and PostgreSQL:
 *   MySQL:      DATE_FORMAT(date, '%Y-%m')
 *   PostgreSQL: TO_CHAR(date, 'YYYY-MM')
 */
class YearMonthFunction extends FunctionNode
{
    private Node $dateExpression;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->dateExpression = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();
        $dateExpr = $this->dateExpression->dispatch($sqlWalker);

        $className = get_class($platform);

        if (str_contains($className, 'PostgreSQL') || str_contains($className, 'Postgre')) {
            return sprintf("TO_CHAR(%s, 'YYYY-MM')", $dateExpr);
        }

        // MySQL / MariaDB / SQLite fallback
        return sprintf("DATE_FORMAT(%s, '%%Y-%%m')", $dateExpr);
    }
}
