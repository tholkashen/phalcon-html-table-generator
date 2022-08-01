# phalcon-html-table-generator
A Library Cluster For PhalconPHP Framework 

<h1>Phalcon HTML Table Generator Via Mysql</h1>

<code>
  $lib = new DataTable(new Users());
</code><br>
Users model must be implemented Phalcon\Mvc\Model<br>
<code>
  $lib->setSchema([
    'columns' => [
        'Name' => [
          'filterable' => true,
          'sortable' => true
        ]
    ]
  ]);
</code>
