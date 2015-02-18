<?php

namespace DataTables\Adapters;

use DataTables\ParamsParser;

/**
 * Class AdapterInterface
 *
 * @package DataTables\Adapters
 */
abstract class AdapterInterface {

  /**
   * @var ParamsParser
   */
  protected $parser = null;
  /**
   * @var array
   */
  protected $columns = [];
  /**
   * @var int
   */
  protected $length = 30;

  /**
   * Constructor
   *
   * @param $length
   */
  public function __construct($length) {
    $this->length = $length;
  }

  /**
   * Response
   *
   * @return mixed
   */
  abstract public function getResponse();

  /**
   *
   * @param ParamsParser $parser
   *
   */
  public function setParser(ParamsParser $parser) {
    $this->parser = $parser;
  }

  /**
   * Set Columns, split tablename, fieldname and alias
   *
   * @param array $columns
   *
   */
  public function setColumns(array $columns) {
    foreach ($columns as $column) {
      $columnSet = array(
        'tableName' => null,
        'fieldName' => null,
        'alias'     => null
      );
      if (preg_match('/(?:(\w+)\.)?(\w+)?\s*(?:AS)?\s*(\w+)?/is', $column, $matches)) {
        $count = count($matches);
        if ($count == 4) {
          $columnSet['tableName'] = $matches[1];
          $columnSet['fieldName'] = $matches[2];
          $columnSet['alias'] = $matches[3];
        }
        if ($count == 3){
          $columnSet['tableName'] = $matches[1];
          $columnSet['fieldName'] = $matches[2];
          $columnSet['alias'] = $matches[2];
        }
      }
      $this->columns[] = $columnSet['alias'];
    }
  }

  public function getColumns() {
    return $this->columns;
  }

  public function columnExists($column) {
    return in_array($column, $this->columns);
  }

  public function getParser() {
    return $this->parser;
  }

  public function formResponse($options) {
    $defaults = [
      'total'    => 0,
      'filtered' => 0,
      'data'     => []
    ];
    $options += $defaults;

    $response = [];
    $response['draw'] = $this->parser->getDraw();
    $response['recordsTotal'] = $options['total'];
    $response['recordsFiltered'] = $options['filtered'];

    if (count($options['data'])) {
      foreach ($options['data'] as $item) {
        if (isset($item['id'])) {
          $item['DT_RowId'] = $item['id'];
        }

        $response['data'][] = $item;
      }
    } else {
      $response['data'] = [];
    }

    return $response;
  }

  public function sanitaze($string) {
    return mb_substr($string, 0, $this->length);
  }

  public function bind($case, $closure) {
    switch ($case) {
      case "global_search":
        $search = $this->parser->getSearchValue();
        if (!mb_strlen($search)) {
          return;
        }

        foreach ($this->parser->getSearchableColumns() as $column) {
          if (!$this->columnExists($column)) {
            continue;
          }
          $closure($column, $this->sanitaze($search));
        }
        break;
      case "column_search":
        $columnSearch = $this->parser->getColumnsSearch();
        if (!$columnSearch) {
          return;
        }

        foreach ($columnSearch as $key => $column) {
          if (!$this->columnExists($column['data'])) {
            continue;
          }
          $closure($column['data'], $this->sanitaze($column['search']['value']));
        }
        break;
      case "external_search":
        $columnSearch = $this->parser->getExternalSearch();
        if (!$columnSearch) {
          return;
        }

        foreach ($columnSearch as $key => $column) {
          if (!$this->columnExists($column['name'])) {
            continue;
          }
          $closure($column['name'], $column['type'], $this->sanitaze($column['value']));
        }
        break;
      case "order":
        $order = $this->parser->getOrder();
        if (!$order) {
          return;
        }

        $orderArray = [];

        foreach ($order as $columnId => $orderBy) {
          if (!isset($orderBy['dir']) || !isset($orderBy['column'])) {
            continue;
          }
          $orderDir = $orderBy['dir'];

          $column = $this->parser->getColumnById($columnId);
          if (is_null($column) || !$this->columnExists($column)) {
            continue;
          }

          $orderArray[] = "{$column} {$orderDir}";
        }

        $closure($orderArray);
        break;
      default:
        throw new \Exception('Unknown bind type');
    }

  }

}
