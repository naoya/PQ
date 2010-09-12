<?php
/**
* SQL/Abstract.php
* @author ittetsu miyazaki<ittetsu.miyazaki _at_ gmail.com>
* @package SQL
*/

/**
* SQL_Abstract
* CPANのSQL::AbstractのPHP版です
* 
* include_pathの通っている場所に例えばlib/SQL/Abstract.class.phpに置いて、
* 
* require_once 'SQL/Abstract.class.php';
* 
* このようにお使い下さい。
* 
* ドキュメントに関してもSQL::Abstractをかなり流用していることにご了承願いたい。
* PHPには連想配列と配列とで同じarray()を利用する。
* そのため元のSQL::Abstractと若干違う仕様の部分があるので注意しながら使って欲しい
* 
* WHERE句の説明
* 
* 最も簡単に説明するには多くの例を見せることだ。それぞれ$where連想配列を提示した後、それを以下のように使ったものとする
* 
*  list($stmt,$bind) = $sql->where($where);
* 
* whereメソッドは最適化のためリファレンスを返す。
* よって配列で受け取る方がより効果的と言えるだろう。（listのバグのためリファレンスにならないので）
* 
*  $data = $sql->where($where);
*  $stmt =& $data[0];
*  $bind =& $data[1];
* 
* さあ、始めよう。まずはシンプルな連想配列からだ。
* 
*     $where  = array(
*         'user'   => 'nwiger',
*         'status' => 'completed'
*     );
* 
* key = valのSQL文に変換される
* 
*     $stmt = "WHERE user = ? AND status = ?";
*     $bind = array('nwiger', 'completed');
* 
* 処理を終えるのに共通なのは、あるフィールドが代入可能な値の配列を持っているということだ。
* そのためには、単に連想配列に配列を指定するだけでよい。
* 
*     $where  = array(
*         'user'   => 'nwiger',
*         'status' => array('assigned', 'in-progress', 'pending'),
*     );
* 
* この単純なコードは、次の結果になる
* 
*     $stmt = "WHERE user = ? AND ( status = ? OR status = ? OR status = ? )";
*     $bind = array('nwiger', 'assigned', 'in-progress', 'pending');
* 
* 異なるタイプの比較演算子を指定したいなら、連想配列を使う：
* 
*     $where  = array(
*         'user'   => 'nwiger',
*         'status' => array( '!=' => 'completed' )
*     );
* 
* こうなる
* 
*     $stmt = "WHERE user = ? AND status != ?";
*     $bind = array('nwiger', 'completed');
* 
* このとき、必ず「=>」を使ってください。「,」を使うと配列と見なされてしまうからです。
* 
*     $where  = array(
*         'user'   => 'nwiger',
*         'status' => array( '!=' , 'completed' ) // 「,」はダメ！
*     );
* 
* 比較演算子の値を配列にするとその文のORをつないでくれる
* 
*     'status' => array( '!=' => array('assigned', 'in-progress', 'pending'))
* 
* こうなる
* 
*     "WHERE status != ? OR status != ? OR status != ?"
* 
* また、連想配列には複数のペアを含められる。その場合、その要素は ANDに拡張される
* 
*     $where  = array(
*         'user'   => 'nwiger',
*         'status' => array( '>' => 0 , '<' => 10 )
*     );
*     $stmt = "WHERE user = ? AND (status > ? AND status < ?)";
*     $bind = array('nwiger', 0, 10);
* 
* 同じ比較演算子を複数指定したい場合はさらに配列に埋め込むことで可能となります。その場合、その要素はORとなります。
* 
*     $where  = array(
*         'user'   => 'nwiger',
*         'status' => array( array( '!=' => 0 ) , array( '!=' => 10 ) )
*     );
*     $stmt = "WHERE user = ? AND (status != ? OR status != ?)";
*     $bind = array('nwiger', 0, 10);
* 
* このケースではORをANDに変更する場合、下記のように書けます。
* 
*     $where  = array(
*         'user'   => 'nwiger',
*         'status' => array( '-and' => array(array( '!=' => 0 ) , array( '!=' => 10 )) )
*     );
*     $stmt = "WHERE user = ? AND (status != ? AND status != ?)";
*     $bind = array('nwiger', 0, 10);
* 
* -and,-orについてのいくつかの例を紹介します。
* 
*  // 同じ意味
*  'status' => array( '>' => 0 , '<' => 10 )
*  'status' => array( '-and' => array(array( '>' => 0),array('<' => 10 )) )
*  
*  // 同じ意味
*  'status' => array('assigned', 'in-progress')
*  'status' => array( '=' => array('assigned', 'in-progress') )
*  'status' => array( array( '=' => 'assigned' ), array( '=' => 'in-progress') )
*  'status' => array( '-or' => array( array( '=' => 'assigned' ), array( '=' => 'in-progress') ) )
* 
* 次に、ネスト構造のWHERE句を作りたい場合の書き方を説明する。下記のような結果を期待する場合、
* 
*     $stmt = WHERE user = ? AND ( workhrs > ? OR geo = ? )
*     $bind = array('nwiger', '20', 'ASIA');
* 
* これは-nestという構文を使えば実現できる。
* 
*     $where = array(
*          'user' => 'nwiger',
*         '-nest' => array( array('workhrs' => array('>' => 20)), array('geo' => 'ASIA') ),
*     );
* 
* 特殊な比較演算子として下記のものをサポートしている。
* 
*  -in
*  -like
*  -not_like
*  -between
*  -not_between
* 
* -inの例
* 
*     $where  = array(
*         'status'   => 'completed',
*         'reportid' => array( '-in' => array(567, 2335, 2))
*     );
* 
* こうなる
* 
*     $stmt = "WHERE status = ? AND reportid IN (?,?,?)";
*     $bind = array('completed', '567', '2335', '2');
* 
* -not_betweenの例
* 
*    $where  = array(
*         'user'   => 'nwiger',
*         'completion_date' => array(
*            '-not_between' => array('2002-10-01', '2003-02-06')
*         )
*     );
* 
* こうなる
* 
*   WHERE user = ? AND completion_date NOT BETWEEN( ? AND ? )
* 
* ここまでで、いかにして複数の条件がANDで結びつくかをみてきた。
* しかし、異なる条件を連想配列内に置いて、それからそれらの連想配列を配列にすることで、この動作を変えることができる。例えば：
* 
*     $where = array(
*         array(
*             'user'   => 'nwiger',
*             'status' => array('pending', 'dispatched'),
*         ),
*         array(
*             'user'   => 'robot',
*             'status' => 'unassigned',
*         )
*     );
* 
* このデータ構造は次のようになる：
* 
*     $stmt = "WHERE ( user = ? AND ( status = ? OR status = ? ) )
*                 OR ( user = ? AND status = ? ) )";
*     $bind = array('nwiger', 'pending', 'dispatched', 'robot', 'unassigned');
* 
* 時には文字通りのSQL文だけが必要となるだろう。もし字句通りに SQLを含ませたいなら、-injectを指定する。つまり：
* 
*     $inn = 'is not null';
*     $where = array(
*         'priority' => array( '<' => 2 ),
*         'requestor' => array( '-inject' => $inn )
*     );
* 
* こうなる：
* 
*     $stmt = "WHERE priority < ? AND requestor is not null";
*     $bind = array('2');
* 
* 最後に、値がnull値の場合は少し特殊で、!=はIS NOT NULL、それ以外の比較演算子の場合はIS NULLになる。
* 
*     $where = array(
*         'priority' => array( '<' => 2 ),
*         'requestor' => array( '!=' => null ),
*     );
*     
*     $stmt = "WHERE priority < ? AND requestor IS NOT NULL";
*     $bind = array('2');
* 
* 履歴
* 2007/06/13 ver 0.01 ほんの少し手直し
* 2008/03/25 ver 0.02 -betweenを使用するとSQLエラーが発生していた問題を修正
*                     error_reportingを削除。(使う側でスキにしてね)
*                     ソースコードをUTF8に変更
* 
* @version 0.02
* @package SQL
* @access public
*/
class SQL_Abstract {
    
    /**
    * コンストラクタ
    * <pre>
    * $cgi = new SQL_Abstract()
    * </pre>
    * @access public
    * @return object SQL_Abstract オブジェクト
    */
    function SQL_Abstract () {}
    
    var $res_join = array(
        '-or'  => ' OR ',
        '-and' => ' AND ',
    );
    
    /**
    * 渡された配列を調べる
    *
    * @access private
    * @param array
    * @return 連想配列なら真を返す。
    *         配列なら偽を返す。
    */
    function _is_hash (&$array) {
        reset($array);
        list($key,$val) = each($array);
        return is_numeric($key) ? false : true;
    }
    
    /**
    * 渡された配列を調べる
    *
    * @access private
    * @param array
    * @return 連想配列で且つキーが-and,-orならその文字列を返す。
    *         連想配列なら真を返す。
    *         配列なら偽を返す。
    */
    function _hash_and_or (&$array) {
        reset($array);
        list($key,$val) = each($array);
        if( array_key_exists( $key , $this->res_join ) ){
            return $this->res_join[$key];
        }
        else {
            return is_numeric($key) ? false : true;
        }
    }
    
    function select ($table,$columns,$where,$order=null) {
        $where  = $this->where($where,$order);
        $sql    =& $where[0];
        $params =& $where[1];
        if( is_array($columns) ){
            $col = join(',',$columns);
        }
        else {
            $col = $columns;
        }
        $sql = "SELECT $col FROM $table $sql";
        return array(&$sql,&$params);
    }
    
    function delete ($table,$where) {
        $where  = $this->where($where);
        $sql    =& $where[0];
        $params =& $where[1];
        $sql = "DELETE FROM $table $sql";
        return array(&$sql,&$params);
    }
    
    function insert ($table,$fieldvals) {
        $columns = '';
        $values  = '';
        $params  = array();
        
        foreach ($fieldvals as $col => $val) {
            $columns .=  $col.',';
            $values  .=  '?,';
            $params  []= $val;
        }
        $columns = rtrim($columns,',');
        $values  = rtrim($values,',');
        
        $sql = sprintf("INSERT INTO %s(%s) VALUES(%s)",$table,$columns,$values);
        return array(&$sql,&$params);
    }
    
    function update ($table,$fieldvals,$where) {
        $set     = array();
        $params  = array();
        
        foreach ($fieldvals as $col => $val) {
            $set    []= "$col = ?";
            $params []= $val;
        }
        
        $set_join = join(' , ',$set);
        
        $where     =  $this->where($where);
        $where_sql =& $where[0];
        foreach($where[1] as $val){
            $params []= $val;
        }
        $sql = sprintf("UPDATE %s SET %s %s",$table,$set_join,$where_sql);
        return array(&$sql,&$params);
    }
    
    /**
    * WHERE句を生成する
    *
    * @access public
    * @param array WHERE句
    * @param array ORDER句(省略可)
    * @return 連想配列で且つキーが-and,-orならその文字列を返す。
    *         連想配列なら真を返す。
    *         配列なら偽を返す。
    */
    function where ($where,$order=null){
        $ret = $this->_more_where($where,false);
        $sql = ( is_null($ret[0]) || $ret[0] === '' ) ? '' : 'WHERE '.$ret[0];
        if( is_array($order) ) $sql .= ' ORDER BY '. join(',',$order);
        return array(&$sql,&$ret[1]);
    }
    
    function _more_where ($where,$bracket,$mode=' AND '){
        $sqls = array();
        $args = array();
        
        if( is_array($where) === false )
            trigger_error('parse error '.var_export($where,true),E_USER_ERROR);
        
        if( $this->_is_hash($where) === true ){
            foreach( $where as $column => $val ){
                $ret = $this->_column($column,$val);
                $sqls[] =& $ret[0];
                array_splice($args,count($args),0,$ret[1]);
            }
        }
        else {
            $mode = ' OR ';
            $more_bracket = count($where) > 1 ? true : false;
            foreach( $where as $no => $wheres ){
                if( !is_numeric($no) ) trigger_error('parse error '.var_export($wheres,true),E_USER_ERROR);
                $ret = $this->_more_where($wheres,$more_bracket);
                $sqls[] =& $ret[0];
                array_splice($args,count($args),0,$ret[1]);
            }
        }
        
        return 
            ( count($sqls) > 1 )
                ? ( $bracket )
                    ? array( '('.join($mode,$sqls).')' , &$args )
                    : array( join($mode,$sqls) , &$args )
                : array( &$sqls[0] , &$args );
    }
    
    function _column (&$column,$val,$mode=' AND ') {
        $sqls = array();
        $args = array();
        
        switch($column){
            case '-nest':
            case '-and':
                $ret = $this->_more_where($val,true);
                break;
            case '-or':
                $ret = $this->_more_where($val,true,' OR ');
                break;
            default:
                if( !is_array($val) ){
                    $ret = $this->_wheres($column,array(array( '=' => &$val )));
                }
                elseif( $and_or = $this->_hash_and_or($val) ) {
                    if( $and_or === true ){
                        $ret = $this->_wheres($column,array(&$val));
                    }
                    else {
                        reset($val);
                        list(,$val) = each($val);
                        if( $this->_is_hash($val) ){
                            $ret = $this->_wheres($column,array(&$val),$and_or);
                        }
                        else {
                            $ret = $this->_wheres($column,$val,$and_or);
                        }
                    }
                }
                else {
                    $ret = $this->_wheres($column,$val);
                }
                break;
        }
        $sqls[] =& $ret[0];
        array_splice($args,count($args),0,$ret[1]);
        return 
            ( count($sqls) > 1 )
                ? array( '('.join($mode,$sqls).')' , &$args )
                : array( &$sqls[0] , &$args );
    }
    
    function _wheres (&$column,$colarrays,$mode=' OR ') {
        $sqls = array();
        $args = array();
        foreach( $colarrays as $colarray ){
            if( is_array($colarray) ){
                if( $this->_is_hash($colarray) ) {
                    // 'hoge' => array(array($ope=>$val,...))
                    $ret = $this->_where($column,$colarray,' AND ');
                }
                else {
                    // 'hoge' => array(array(1,2,3))
                    $ret = $this->_wheres($column,$colarray);
                }
            }
            else {
                // 'hoge' => 1
                $colarray = array( '=' => $colarray );
                $ret = $this->_where($column,$colarray,$mode);
            }
            $sqls[] =& $ret[0];
            array_splice($args,count($args),0,$ret[1]);
        }
        return 
            ( count($sqls) > 1 )
                ? array( '('.join($mode,$sqls).')' , &$args )
                : array( &$sqls[0] , &$args );
    }
    
    function _where (&$column,&$colarray,$mode=' OR ') {
        $sqls = array();
        $args = array();
        
        if( count($colarray) == 0 )
            trigger_error('parse error '.var_export($colarray,true),E_USER_ERROR);
        
        foreach( $colarray as $ope => $val ){
            
            if( is_null($val) ) {
                $sqls[] = ( $ope === '!=' ) ? "$column IS NOT NULL" : "$column IS NULL";
                continue;
            }
            
            switch($ope){
                case '-like':
                    $sqls[] = "$column LIKE ?";
                    $args[] = $val;
                    break;
                case '-not_like':
                    $sqls[] = "$column NOT LIKE ?";
                    $args[] = $val;
                    break;
                case '-not_between':
                    if( is_array($val) ){
                        $sqls[] = "$column NOT BETWEEN ? AND ?";
                        array_push($args,$val[0],$val[1]);
                    }
                    else {
                        $sqls[] = "$column NOT BETWEEN ?";
                        array_push($args,$val);
                    }
                    break;
                case '-between':
                    if( is_array($val) ){
                        $sqls[] = "$column BETWEEN ? AND ?";
                        array_push($args,$val[0],$val[1]);
                    }
                    else {
                        $sqls[] = "$column BETWEEN ?";
                        array_push($args,$val);
                    }
                    break;
                case '-in':
                    $ques = array();
                    foreach( $val as $data ){
                        $ques[] = '?';
                        $args[] = $data;
                    }
                    $sqls[] = "$column IN (".join(',',$ques).')';
                    break;
                case '-inject':
                    $sqls[] = "$column $val";
                    break;
                default:
                    if( is_array($val) ){
                        // 'hoge' => array( $ope => array(1,2,3) )
                        $tmp_colarray = array();
                        foreach( $val as $val2 ) $tmp_colarray[] = array( $ope => $val2 );
                        $ret = $this->_wheres($column,$tmp_colarray);
                        $sqls[] =& $ret[0];
                        array_splice($args,count($args),0,$ret[1]);
                    }else{
                        // 'hoge' => array( $ope => $val )
                        $sqls[] = "$column $ope ?";
                        $args[] = $val;
                    }
                    break;
            }
        }
        return 
            ( count($sqls) > 1 )
                ? array( '('.join($mode,$sqls).')' , &$args )
                : array( &$sqls[0] , &$args );
    }
}
