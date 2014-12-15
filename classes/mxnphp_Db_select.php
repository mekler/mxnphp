<?php
/**
* Class abstract mxnphp_Db_select
*
* Esta clase es el modelado de la base de datos 
* que va a cargar el framework.
* 
*/
class mxnphp_Db_select{
	private $_query;
	private $_select;
	private $_fields;
	private $_from;
	private $_tables;
	private $_where;
	private $_join;
	private $_limit;
	private $_order;
	private $_adapter;

	
	const FIELDS = "_fields";
	const LIMIT = "_limit";
	
	public function __construct($adapter){
		$this->_query = "";
		$this->_limit = "";
		$this->_select = "SELECT ";
		$this->_order = array();
		$this->_adapter = $adapter;
	}
	public function getTable($table){
		$t  = new $table();
		$t->table_name;
		return $t;
	}
	public function select($_field = null){
		if($_field != null && trim($_field) != ""){
			$field = $_field;
		}else{
			$field = null;
		}
		if(is_string($field)){
			$this->_fields[] = $field; 
		}
		return $this;	
	}
	public function from($_table = false,$fields = false){
		$table = "";
		if(is_string($_table)){
			$table =  $this->getTable($_table)->table_name;
			$this->_from[] = $table;
		}elseif(is_array($_table)){
			foreach($_table as $key=>$value){
				$table = $this->getTable($value)->table_name." AS $key" ;
				$this->_from[] = $table;				
				break;
			}
		}
		if((!$fields || count($fields) == 0) && $fields != null){
			$this->_fields[] = "$table.*";
		}elseif(is_array($fields) || count($fields) > 1){
			foreach($fields as $key => $f){
				if(is_int($key)){
					$this->_fields[] = "$table.$f";
				}else{
					$this->_fields[] = "$table.$f AS $key";
				}
			}
		}
		return $this;	
	}
	private function innetJoin($_table,$condition,$fields,$type){
		$table = "";
		$asTable = "";
		if(is_string($_table)){
			$table = $this->getTable($_table)->table_name;
			$this->_join[] = array($table,$type,$condition,$asTable);
		}elseif(is_array($_table)){
			foreach($_table as $asTable=>$value){
				$asTable = trim($asTable);
				$table = $this->getTable($value)->table_name;
				$this->_join[] = array($table,$type,$condition,$asTable);				
				break;
			}
		}
		if(is_array($fields) && (count($fields) == 0)){
			if($asTable == ""){
				$this->_fields[] = "$table.*";	
			}else{
				$this->_fields[] = "$asTable.*";
			}
		}elseif(is_array($fields) || count($fields) > 1){
			foreach($fields as $key => $f){
				if(is_int($key)){
					if($asTable == ""){
						$this->_fields[] = "$table.$f";
					}else{
						$this->_fields[] = "$asTable.$f";
					}
				}else{
					if($asTable == ""){
						$this->_fields[] = "$table.$f AS $key";
					}else{
						$this->_fields[] = "$asTable.$f AS $key";
					}
				}
			}
		}		
	}
	public function join($_table,$condition,$fields = array()){
		$this->innetJoin($_table,$condition,$fields,"INNER JOIN");
		return $this;
	}
	public function leftJoin($_table,$condition,$fields = array()){
		$this->innetJoin($_table,$condition,$fields,"LEFT JOIN");
		return $this;
	}
	public function rightJoin($_table,$condition,$fields = array()){
		$this->innetJoin($_table,$condition,$fields,"RIGHT JOIN");
		return $this;
	}
	public function distinct(){
		return $this;
	}
	public function where($where = "",$param = ""){
		if($param != "") {
			$param = $this->_adapter->quote($param);
			$where = str_replace('?',"'$param'",$where);
		}
		$this->_where[] = array("where" => $where,"operator" => "AND");
		return $this;
	}
	public function orWhere($where = "",$param = ""){
		if(trim($param) != ""){
			$param = $this->_adapter->quote($param);
			$where = str_replace('?',"'$param'",$where);
		}
		$this->_where[] = array("where" => $where,"operator" => "OR");
		return $this;
	}
	public function order($order){
		$this->_order[] = $order;
		return $this;
	}
	public function limit($begin,$num = ""){
		if(trim($num) != ""){
			$num = ", ".$num;
		}
		$this->_limit = "LIMIT $begin OFFSET $num";
		return $this;
	}
	public function union(){
		return $this;
	}
	public function group(){
		return $this;
	}
	public function having(){
		return $this;
	}
	private function makeSelect(){
		$this->_query[] = $this->_select;
	}
	private function makeFields(){
		if(count($this->_fields) == 0){
			$fields = " * ";
		}else{
			$fields = " ";
			$coma = "";
			foreach($this->_fields as $f){
				$fields .= $coma.$f;
				$coma = ",";
			}			
		}
		$this->_query[] = $fields;
	}
	private function makeFrom(){
		$from = " FROM ";
		if(count($this->_from) > 0){
			$coma = "";
			foreach($this->_from as $f){
				$from .= $coma.$f;
				$coma = ", ";
			}
		}
		$this->_query[] = $from;
	}
	private function makeJoin(){
		$join = "";
		if(count($this->_join) > 0){
			foreach($this->_join as $j){
				if($j[3] ==""){
					$join .= " ".$j[1]." ".$j[0]." ON ".$j[2];
				}else{
					$join .= " ".$j[1]." ".$j[0]." AS {$j[3]}"." ON ".$j[2];
				}
			}
		}
		$this->_query[] = $join;		
	}
	private function makeWhere(){
		$where = "WHERE ";
		if(count($this->_where)){
			$_operator = false;
			foreach($this->_where as $w){
				if(!$_operator){
					$_operator = true;
					$operator = "";
				}else{
					$operator = $w["operator"];	
				}
				$where .= " $operator ( {$w['where']} )";
			}
		}else{
			$where .= " 1";	
		}
		$this->_query[] = $where;
	}
	private function makeLimit(){
		$this->_query[] = $this->_limit;	
	}
	private function makeOrder(){
		if(count($this->_order) > 0){
			$this->_query[] = "ORDER BY ";
			$order = "";
			$coma = "";
			foreach($this->_order as $o){
				$order .= "$coma $o";
				$coma = ", ";
			}
			$this->_query[] = $order;
		}
	}
	private function makeSql(){
		$this->makeSelect();
		$this->makeFields();
		$this->makeFrom();
		$this->makeJoin();
		$this->makeWhere();
		$this->makeOrder();
		$this->makeLimit();
	}
	private function makeQuery(){
		$this->makeSql();
		$query = "";
		foreach($this->_query as $q){
			$query .= "$q ";
		}
		return $query;
	}
	public function getSql(){
		$query = $this->makeQuery();
		$this->_query = array();
		return $query;
	}
	public function reset($value){
		switch ($value) {
			case self::FIELDS:
				$this->_fields = array();
				break;
			case self::LIMIT:
				$this->_limit = "";
				break;
		}
	}
	public function __toString(){
		return $this->getSql();
	}
}
?>