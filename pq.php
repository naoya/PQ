<?php

require_once 'MDB2.php';
require_once './libs/SQL/Abstract.class.php';
require_once './libs/List/RubyLike.class.php';

PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, function ($e) { die($e->getMessage()); });

/* SQL_Abstract のUIを変更したというだけでは物足りないのでもう少し考えたい */
class PQ_Table {
    var $_table;
    var $_limit;
    var $_fields = array('*');
    var $_offset;
    var $_order = array();
    var $_where = array();

    function __construct ($table) {
        $this->_table = $table;
    }

    function where ($where) {
        $this->_where =& array_merge($this->_where, $where);
        return $this;
    }

    /* function and() とかできたらいいんだけどなー */
    /*
    function and_where ($where) {
        $this->_where =& array_merge($this->_where, $where);
        return $this;
    }

    function or_where ($where) {
        $this->_where[] = $where;
        return $this;
    }
    */

    function fields ($fields) {
        $this->_fields = $fields;
        return $this;
    }
   
    function order ($order) {
        $this->_order[] = $order;
        return $this;
    }

    function limit ($limit) {
        $this->_limit = $limit;
        return $this;
    }
    
    function offset ($offset) {
        $this->_offset = $offset;
        return $this;
    }

    function to_sql() {
        $sql = new SQL_Abstract();

        list($sql, $bind) = $sql->select(
            $this->_table,
            $this->_fields,
            $this->_where,
            $this->_order
        );

        // SQL::Abstract の limit 関連
        // http://d.hatena.ne.jp/dayflower/20061107/1162894215
        if (isset($this->_offset) or isset($this->_limit)) {
            $sql .= ' LIMIT ';

            if ( isset($this->_offset) )
                $sql .= sprintf("%d,", $this->_offset);

            $sql .= sprintf("%d", $this->_limit);
        }

        return array($sql, $bind);
    }
}

class PQ_Query extends PQ_Table {
    var $_dsn;
    var $_table;

    function __construct($dsn, $table) {
        $this->_dsn = $dsn;
        parent::__construct($table);
    }

    function exec() {
        $db = MDB2::connect($this->_dsn);

        list($sql, $binds) = $this->to_sql();

        // echo $sql . "\n";

        $st = $db->prepare($sql);
        $rs = $st->execute($binds);

        $rows = $rs->fetchAll();
        return LR( $rows );            
    }
}

class PQ {
    var $_dsn;

    function dsn ($dsn) {
        $this->_dsn = $dsn;
        return $this;
    }

    function query($table) {
        return new PQ_Query($this->_dsn, $table);
    }
}

function main () {
    $pq = new PQ();
    $pq->dsn('mysqli://nobody:nobody@localhost/sample?charset=utf8');

    echo $pq->query('users')
        ->where( array('age'  => array('>' => 20)) )
        ->where( array('mail' => array('-like' => '%@example.com')) )
        ->fields("mail, name")
        ->order("updated desc")
        ->offset(0)
        ->limit(10)
        ->exec()
        ->map(function ($o) { return $o[0]; })
        ->map(function ($v) { return strtoupper($v); })
        ->join("\n") . "\n";
}

main();
?>