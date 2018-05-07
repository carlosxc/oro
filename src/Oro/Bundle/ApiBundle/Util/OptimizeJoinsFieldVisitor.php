<?php

namespace Oro\Bundle\ApiBundle\Util;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor;
use Doctrine\Common\Collections\Expr\Value;

/**
 * Walks an expression graph and collects fields are used in all comparison expressions
 * that allow to convert LEFT joins to INNER joins.
 */
class OptimizeJoinsFieldVisitor extends ExpressionVisitor
{
    /** @var array */
    private $fields = [];

    /**
     * Gets all fields are used in a visited expression graph and allow optimization of joins.
     *
     * @return string[]
     */
    public function getFields()
    {
        $fields = [];
        foreach ($this->fields as $field => $value) {
            if ($value) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function walkComparison(Comparison $comparison)
    {
        $field = $comparison->getField();
        if (!isset($this->fields[$field])) {
            $this->fields[$field] = $this->isOptimizationSupported($comparison);
        } elseif ($this->fields[$field] && !$this->isOptimizationSupported($comparison)) {
            $this->fields[$field] = false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function walkValue(Value $value)
    {
        return $value->getValue();
    }

    /**
     * {@inheritdoc}
     */
    public function walkCompositeExpression(CompositeExpression $expr)
    {
        $expressionList = $expr->getExpressionList();
        foreach ($expressionList as $child) {
            $this->dispatch($child);
        }
    }

    /**
     * @param Comparison $comparison
     *
     * @return bool
     */
    private function isOptimizationSupported(Comparison $comparison): bool
    {
        $operator = $comparison->getOperator();

        if ('EXISTS' === $operator) {
            return $this->walkValue($comparison->getValue());
        }
        if ('NEQ_OR_NULL' === $operator) {
            return false;
        }

        return true;
    }
}
