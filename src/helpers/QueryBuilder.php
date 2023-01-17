<?php

namespace App\helpers;

class QueryBuilder
{
  /**
   * @var array
   */
  private $fields = [];
  /**
   * @var array
   */
  private $from = [];
  /**
   * @var array
   */
  private $join = [];
  /**
   * @var array
   */
  private $joinType = [];
  /**
   * @var array
   */
  private $joinOn = [];
  /**
   * @var array
   */
  private $where = [];
  /**
   * @var array
   */
  private $limit = [];
  /**
   * @var array
   */
  private $order = [];
  /**
   * @var array
   */
  private $column = [];
  /**
   * @var array
   */
  private $condition = [];
  /**
   * @var array
   */
  private $value = [];
  /**
   * @var array
   */
  private $namespace = [];
  public function select(/*array */$fields)/*: QueryBuilder*/
  {
    $this->fields[] = $fields;
    return $this;
  }
  public function from(/*string */$table,/* string*/ $alias)/*: QueryBuilder*/
  {
    $this->from[] = $table.' AS '.$alias;
    return $this;
  }
  public function where(/*string */$column, $condition, $value)/*: QueryBuilder*/
  {
    $this->column[] = $column;
    $this->condition[] = $condition;
    $this->value[] = $value;
    $this->namespace[] = '?';
    $this->where[] = $column.' '.$condition.'?';
    return $this;
  }

  public function whereAltogether($columns, $conditions, $values, $type, $orAnd)
  {
    $where = '' ;
    for($i = 0; $i<COUNT($columns); $i++)
    {
      $this->column[] = $columns[$i];
      $this->condition[] = $conditions;
      $this->namespace[] = '?';
      if ($conditions == 'LIKE' || $conditions == "NOT LIKE")
      {
        switch ($type)
        {
          case 'int':
            $this->value[] = '%'.intval($values[$i]).'%';
            break;
          case 'string':
            $this->value[] = strpos($values[$i], '%') === false ? '%' . $values[$i] . '%' : $values[$i];
            break;
        }
      }
      else
      {
        $this->value[] = $values[$i];
      }
      $where .= $columns[$i].' '.$conditions.' ? '.$orAnd[$i].' ';
    }
    $this->where[] = '('.$where.')';
    return $this;
  }

  public function whereInFromArray($column, $values, $type)
  {
    $condition = 'IN';
    if (is_array($values))
    {
      if (COUNT($values))
      {
        $intvalValues = [];
        foreach ($values as $value)
        {
          switch ($type) {
            case 'int':
              $intvalValues[] = intval($value);
              break;
            case 'string':
              $intvalValues[] = trim(addcslashes($value, "'"));
              break;
          }
        }
        if (COUNT($intvalValues))
        {
          $namespace = substr(str_repeat('?,', count($intvalValues)), 0, -1);
          $this->column[] = $column;
          $this->condition[] = $condition;
          foreach ($intvalValues as $value)
          {
            $this->value[] = $value;
          }
          $this->namespace[] = $namespace;
          $this->where[] = $column.' '.$condition.'('.$namespace.')';
        }
      }
    }
    return $this;
  }

  public function getBinds()
  {
    return /*array_chunk(*/$this->value;/*, 2, true);/*[array_merge($this->namespace, $this->value)];*/
  }


  public function join(/*string */$type,/* string*/ $table,/* string*/ $alias, /*string*/ $on )/*: QueryBuilder*/
  {
    $this->join[] = $type.' JOIN '.$table.' AS '.$alias.' ON ('.$on.')';
    return $this;
  }

  public function limit(/*int*/$from, /*int */$to = -1)
  {
    if ($to == -1)
    {
      $this->limit[] = $from;
    }
    else
    {
      $this->limit[] = $from.','.$to;
    }
    return $this;
  }
  public function order(/*string*/ $order)
  {
    $this->order[] = $order;

    return $this;
  }
  public function __toString()/*: string*/
  {
    return sprintf(
      'SELECT %s FROM %s  %s  WHERE %s ORDER BY %s LIMIT %s',
      join(', ', $this->fields),
      join(', ', $this->from),
      join(' ', $this->join),
      join(' AND ', $this->where),
      join(', ', $this->order),
      join(', ', $this->limit)
    );
  }
}