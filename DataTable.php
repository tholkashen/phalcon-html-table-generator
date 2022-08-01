<?php

namespace Siena\Datatable;

use Phalcon\Http\Response;
use Phalcon\Paginator\Adapter\QueryBuilder;
use Siena\Html\ClassManager;

class DataTable
{
    public $models             = [];
    public $schema             = [];
    public $filters            = [];
    public $ignoreFilters      = [];
    public $widgets            = [];
    public $headers            = [];
    public $rows               = [];
    private $filterableColumns = [];
    private $scripts           = [];
    private $styles            = [];
    private $sort              = null;
    private $modes             = [];
    private $hooks             = [];
    private $custom_filters    = [];
    private $classes;
    public function __construct(\Phalcon\Mvc\Model $model)
    {
        $this->classes = new ClassManager('table', 'tbody', 'thead', 'tr', 'td', 'th');
        $this->model = $model->modelsManager->createBuilder();
        $this->model->from(get_class($model));
    }
    public function setSchema($config)
    {
        $this->schema = $config;
        if(isset($config['filters'])){
            foreach ($config['filters'] as $key => $value) {
                $this->custom_filters[$key] = [
                    'title' => $value['title'],
                    'status' => false
                ];
            }
        }
    }
    public function addAction($name, $callback)
    {
        array_push($this->hooks[$name], $callback);
    }
    public function doAction($name, $values = [])
    {
        foreach ($this->hooks as $key => $value) {
            if ($key == $name) {
                $value(...$values);
            }
        }
    }
    public function setMode($mode, $viewFile, $option = [])
    {
        $this->modes[$mode] = [
            'view' => $viewFile,
            'options' => $option
        ];
    }
    public function runWidget($widgetName, $data, $values, $params = [])
    {
        if (!isset($this->widgets[$widgetName])) {
            $widgetPath = BASE_PATH . "/public/widgets/";
            if (file_exists($widgetPath . $widgetName . ".php")) {
                include_once $widgetPath . $widgetName . ".php";
                $this->widgets[$widgetName] = $widgetName;
            }
        }
        $widgetInit = $this->widgets[$widgetName];
        $_class = new $widgetInit();
        if (count($params) > 0) {
            foreach ($params as $key => $value) {
                if ($value[0] == "@") {
                    $_buffer = str_replace("@", "", $value);
                    if (isset($values->$_buffer)) {
                        $value = $values->$_buffer;
                    }else{
                        $value = "";
                    }
                } else if ($value == "%ROW%") {
                    $value = $values;
                }
                $_class->$key = $value;
            }
        }
        if (method_exists($_class, "script")) {
            $this->scripts[$widgetName] = $_class->script(isset($_GET['name']) ? $_GET['name'] : null);
        }
        if (method_exists($_class, "style")) {
            $this->styles[$widgetName] = $_class->style(isset($_GET['name']) ? $_GET['name'] : null);
        }
        return $_class->handle($data, $this->classes);
    }
    public function setFilter($name, $callback)
    {
        $this->filters[$name] = $callback;
    }
    public function ignoreFilter($name)
    {
        $this->ignoreFilters[$name] = true;
    }
    public function handleCustomFilters(){
        if (isset($_GET['custom_filters'])) {
            $customFilter = $_GET['custom_filters'];
            if (count($customFilter) > 0) {
                foreach ($customFilter as $key => $value) {
                    if(isset($this->custom_filters[$key])){
                        $this->schema['filters'][$key]['map']($value,$this->model);
                        $this->custom_filters[$key]['status'] = true;
                    }
                }
            }
        }
    }
    public function handleFilters()
    {
        if (isset($this->schema['columns'])) {
            foreach ($this->schema['columns'] as $keySchema => $valueSchema) {
                if (isset($valueSchema['filterable'])) {
                    $this->filters[$keySchema] = $valueSchema['filterable'];
                    $this->filterableColumns[$keySchema] = $valueSchema['title'];
                }
            }
        }
        if (isset($_GET['filters'])) {
            $query = $_GET['filters'];
            if (count($query) > 0) {
                foreach ($query as $key => $value) {
                    $key = trim($key);
                    if ($key == "__") {
                        $ORs = [];
                        foreach ($this->filterableColumns as $key1 => $value1) {
                            array_push($ORs, $this->filter($key1, $value));
                        }
                        $this->model->andWhere(implode(" OR ", $ORs));
                    } else {
                        if (isset($this->filters[$key])) {
                            if (!isset($this->ignoreFilters[$key])) {
                                $query = $this->filter($key, $value);
                                $this->model->andWhere($query);
                            }
                        }
                    }
                }
            }
        }
    }
    public function handleSorting()
    {
        if (isset($_GET['sort'])) {
            $sortQuery = $_GET['sort'];
            if (isset($this->schema['columns'])) {
                if (isset($this->schema['columns'][$sortQuery['col']])) {
                    $col = $this->schema['columns'][$sortQuery['col']];
                    if (isset($col['sortable'])) {
                        if (isset($col['datacol'])) {
                            $column = $col['datacol'];
                        } else {
                            $column = $sortQuery['col'];
                        }
                        $this->sort = $sortQuery;
                        $this->model->orderBy($column . " " . $sortQuery['dir']);
                    }
                }
            }
        }else{
            if (isset($this->schema['default_sorting'])) {
                $this->model->orderBy($this->schema['default_sorting'][0] . " " . $this->schema['default_sorting'][1]);
            }
        }
    }
    public function filter($key, $values)
    {
        $sqlQuery = [];
        if (isset($this->schema['columns'][$key]['datacol'])) {
            $column = $this->schema['columns'][$key]['datacol'];
        } else {
            $column = $key;
        }
        if(!is_array($values)){
            $_val = $values;
            $values = [];
            $values[0] = $_val;
        }
        foreach ($values as $key => $value) {
            $type = "con";
            $val = $value;
            if (strpos($value, "::") > -1) {
                $_arr = explode("::", $value);
                $type = $_arr[0];
                $val = $_arr[1];
            }
            switch ($type) {
                case 'con':
                    array_push($sqlQuery, $column . " LIKE '%" . $val . "%' ");
                    break;
                case 'equ':
                    array_push($sqlQuery, $column . "='" . $val . "'");
                    break;
                case 'big':
                    array_push($sqlQuery, $column . " > '" . $val . "'");
                    break;
                case 'bie':
                    array_push($sqlQuery, $column . " >= '" . $val . "'");
                    break;
                case 'sma':
                    array_push($sqlQuery, $column . " < '" . $val . "'");
                    break;
                case 'sme':
                    array_push($sqlQuery, $column . " <= '" . $val . "'");
                    break;
                case 'not':
                    array_push($sqlQuery, $column . " != '" . $val . "'");
                    break;
            }
        }
        return implode(" OR ", $sqlQuery);
    }
    public function handleTable($paginator)
    {
        
        if (isset($this->schema['columns'])) {
            foreach ($paginator->getItems() as $key => $value) {
                $this->classes->empty("tr");
                $columns = [];
                $details = [];
                foreach ($this->schema['columns'] as $keySchema => $valueSchema) {
                    $this->classes->empty("td");
                    $header_class = "";
                    if (isset($valueSchema['hidden'])) {
                        continue;
                    }
                    if (isset($valueSchema['sortable'])) {
                        $header_class .= " sortable ";
                    }
                    if ($this->sort != null) {
                        if ($this->sort['col'] == $keySchema) {
                            $header_class .= " sorted sorted-" . $this->sort['dir'] . " ";
                        }
                    }
                    $mainValue = null;
                    if (isset($value->$keySchema)) {
                        $mainValue = $value->$keySchema;
                        $datacol = $keySchema;
                    } else {
                        if (isset($valueSchema['datacol'])) {
                            $datacol = $valueSchema['datacol'];
                            $mainValue = $value->$datacol;
                        }
                    }
                    if (isset($valueSchema['widget'])) {
                        if(is_callable($valueSchema['widget'])){
                            $mainValue = $valueSchema['widget']($value,$this->classes);
                        }else{
                            $mainValue = $this->runWidget($valueSchema['widget']['name'], $mainValue, $value, isset($valueSchema['widget']['params']) ? $valueSchema['widget']['params'] : []);
                        }
                    }
                    $this->headers[$keySchema] = [
                        'title' =>  $valueSchema['title'],
                        'html' => "<th class='" . $header_class . " " . $this->classes->get('th') ."' data-column='" . $keySchema . "'>" . $valueSchema['title'] . "</th>"
                    ];
                    $this->classes->empty('th');

                    array_push($details, [
                        'column' => $keySchema,
                        'value'  => $mainValue
                    ]);
                    array_push($columns, "<td class='" . $this->classes->get("td") . "' data-column='" . $keySchema . "'>" . $mainValue  . "</td>");
                    $this->classes->empty('td');
                }
                array_push($this->rows, [
                    'uid' => $value->id,
                    'columns' => $details,
                    'html' => "<tr class='" . $this->classes->get("tr") . "' data-id='" . $value->id . "'>" . implode("", $columns) . "</tr>"
                ]);
                $this->classes->empty('tr');
            }
        }
    }
    public function renderTable($id, $class = "", $attr = "")
    {
        $headerHtml = array_map(function ($val) {
            return $val['html'];
        }, $this->headers);
        $bodyHtml = array_map(function ($val) {
            return $val['html'];
        }, $this->rows);
        $text = "<table id='"  .  $id . "' class='" . $class . " " .  $this->classes->get("table") . "'><thead>" . implode("", $headerHtml)  . "</thead><tbody>" . implode("", $bodyHtml)  . "</tbody></table>";
        $response = new Response();
        return $response->setJsonContent([
            'total_rows' => $this->paginator->getTotalItems(),
            'current_page' =>  $this->paginator->getCurrent(),
            'last_page'  => $this->paginator->getLast(),
            'limit' => $this->paginator->getLimit(),
            'custom_filters' => $this->custom_filters,
            'filterable_columns' => $this->filterableColumns,
            'html' => $text,
            'scripts' => $this->scripts,
            'style' => $this->styles
        ])->send();
    }
    public function renderCustom($viewPath, $option = [])
    {
        $view = \Phalcon\Di::getDefault()->get('view_compiler');
        $view->rawData = $this->paginator;
        $view->options = $option;
        $response = new Response();
        return $response->setJsonContent([
            'total_rows' => $this->paginator->getTotalItems(),
            'current_page' =>  $this->paginator->getCurrent(),
            'last_page'  => $this->paginator->getLast(),
            'limit' => $this->paginator->getLimit(),
            'filterable_columns' => $this->filterableColumns,
            'custom_filters' => $this->custom_filters,
            'html' => $view->render($viewPath),
            'scripts' => $this->scripts,
            'style' => $this->style
        ])->send();
    }
    public function render($id, $class = "", $attr = "")
    {
        if (isset($_GET['mode'])) {
            if (isset($this->modes[$_GET['mode']])) {
                $mode = $this->modes[$_GET['mode']];
                return $this->renderCustom($mode['view'], $mode['options']);
            } else {
                return $this->renderTable($id, $class, $attr);
            }
        }else{
            return $this->renderTable($id, $class, $attr);
        }
    }
    public function generate()
    {
        $this->handleFilters();
        $this->handleCustomFilters();
        $this->handleSorting();
        if (isset($this->schema['query'])) {
            $this->schema['query']($this->model);
        }
        $this->paginator = new QueryBuilder(
            [
                "builder" => $this->model,
                "limit"   => isset($_GET['limit']) ? $_GET['limit'] : 1000000000000,
                "page"    => isset($_GET['page']) ? $_GET['page'] : 1,
            ]
        );
        $this->paginator = $this->paginator->paginate();
        $this->handleTable($this->paginator);
    }
}
