<?php

/**
 * EvoSQL
 *  
 * Generic PHP class for handling MySQL Queries and Results 
 *  
 * This class will help you create MySQL Queries and show the results.  
 * Intead of passing in strings to the PHP Classes, I prefer to use assiciated 
 * arrays to make things more logical and easier to debug and maintain.
 *  
 * @package   EvoSQL
 * @author    Aaron Jay Lev <aaronjaylev@gmail.com>
 * @copyright Copyright (c) 2013, Aaron Jay Lev
 * @example   http://www.aaronjay.com/EvoSQL
 * @link      http://www.aaronjay.com 
 * @license   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Copyright 2013 Aaron Jay Lev
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.                                                                                                           
*/

class EvoSQL {
  private $db_host = "localhost";
	private $db_name = "";
	private $db_user = "";
	private $db_pass = "";
	
	private $sql_link = 0;
	
	public function Connect($db_host = null, $db_name = null, $db_user = null, $db_pass = null) {
		if ($db_host !== null) $this->db_host = $db_host;
		if ($db_name !== null) $this->db_name = $db_name;
		if ($db_user !== null) $this->db_user = $db_user;
		if ($db_pass !== null) $this->db_pass = $db_pass;

      // Open Database Connection
		$this->sql_link=mysql_connect ($this->db_host, $this->db_user, $this->db_pass) or die('<br />Cannot connect to the database because: ' . mysql_error());
		if (!$this->sql_link) {
   		die('Could not connect to database : ' . mysql_error());
			exit;
		}
		mysql_select_db($this->db_name) or die(mysql_error());
	}
	
	
	public function quote_smart($value) {
	   // Stripslashes
	   if (get_magic_quotes_gpc()) {
	       $value = stripslashes($value);
	   }
	   $value = "'" . mysql_real_escape_string($value) . "'";
	   return $value;
	}	
	
	public function RunQuery($DBString) { // The parameter names are passed in and not a reference to the variables themselves
		mysql_query('SET CHARACTER SET utf8');
		$DBQuery = mysql_query($DBString, $this->sql_link);
		if (!$DBQuery) {
			die('Invalid query: ' . $DBString . '<br>Error: ' . mysql_error());
		}
		return $DBQuery;	
	}
	private function MakeWhereQuery($whereArray) {
	// if whereArray is 0, return blank to return all rows
		if ($whereArray == 0) return '';
		
		if (! is_array($whereArray)) die('not an array in MakeWhereQuery');

		$st = '';
		foreach ($whereArray as $key => $value) {
         $st .= ($st == '' ? ' where ' : ' and ');
         if ($key == '') {
            $st .= $value;
         } else {
            $st .= $key . ' = ' . $this->quote_smart($value);
         }
  		}
			
		return $st;
	}
	
	private function MakeOrderQuery($orderArray) {
	
	// 9/1/11 - Updated to not force key/value combo
		if (! is_array($orderArray)) {
			if (!$orderArray || $orderArray == '') {
				return '';
			} else { // assume it's a single field string sort
				return " order by " . $orderArray;
			}
		} else {
			$st = '';
			foreach ($orderArray as $key => $value) {
				if ($st == '') {
					$st = ' order by ';
				} else {
					$st .= ', ';
				}
				$st .= ($key === 0 ? '' : $key) . ' ' . $value;
			}
			return $st;
		}
	}
	
	private function MakeFieldNames($fieldNames) {
		if ($fieldNames == 0) {
			return "*";
		} elseif (is_string($fieldNames)) {
			return $fieldNames;
		} elseif (is_array($fieldNames)) {
			return implode(', ', $fieldNames);
		} else {
			print_r($fieldNames);
			die('Unknown type in MakeFieldNames');
		}
  }

	public function DeleteRows($tableName, $whereArray) {
		if (! is_array($whereArray)) die('not an array in DeleteRows');
		$query = "delete from " . $tableName . $this->MakeWhereQuery($whereArray);
		return $this->RunQuery($query);
	} 

	function NumRows($tableName, $field = '*', $whereArray=0){
	
		$results = $this->RunQuery("select $field FROM $tableName " . $this->MakeWhereQuery($whereArray));

		return mysql_num_rows($results);
	
	}
	public function SelectRows($tableName, $whereArray = 0, $fieldNames = 0, $orderArray = 0, $startpos=0,$norows=0) {
		if (! is_array($whereArray) && $whereArray != 0) die('whereArray is not an array in SelectRows');
		$query = "select " . $this->MakeFieldNames($fieldNames) . " from " . $tableName . $this->MakeWhereQuery($whereArray) .
			$this->MakeOrderQuery($orderArray);
		if (0 < $norows) {
			$query .= " limit $startpos, $norows ";
		}	
   	return $this->RunQuery($query);
	} 

	public function SelectRow($tableName, $whereArray, $fieldNames = 0) {
		$results = $this->SelectRows($tableName, $whereArray,$fieldNames);
		if (mysql_num_rows($results) == 0) { // not found
			return false;
		} else {
			$info = mysql_fetch_assoc($results);
			return $info;
		}	
	}
   
   public function NextAutoNumber($table) {
      $query = 'SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = ' . 
         $this->quote_smart($this->db_name) . ' AND TABLE_NAME = ' . $this->quote_smart($table);
      $results = $this->RunQuery($query);
      list($value) = mysql_fetch_row($results);
      return $value;
   }
      
   
	
	public function InsertRow($tableName, $valuesArray) {
		$query = "insert into " . $tableName;
		$st1 = '';
		$st2 = '';
		foreach ($valuesArray as $key => $value) {
			$st1 .= ($st1 == '' ? '' : ', ') . $key;
			$st2 .= ($st2 == '' ? '' : ', ') . $this->quote_smart($value);
		}
		$query .= " (" . $st1 . ") values (" . $st2 . ")";
		$results = $this->RunQuery($query);
		$id = mysql_insert_id();
		return $id;
	}
	
	public function UpdateRows($tableName, $valuesArray, $whereArray) {
		$query = "update " . $tableName . " set ";
		$st = "";
		foreach ($valuesArray as $key => $value) {
			$st .= ($st == '' ? '' : ', ') . $key . ' = ' . $this->quote_smart($value);
		}
		$query .= $st . $this->MakeWhereQuery($whereArray);
		return $this->RunQuery($query);
	}
	
	public function AutoInsertUpdate($tableName, $valuesArray, $whereArray) {
		$result = $this->SelectRows($tableName, $whereArray);
		if (mysql_num_rows($result) == 0) {
			return $this->InsertRow($tableName, array_merge($valuesArray, $whereArray));
		} else {
			return $this->UpdateRows($tableName, $valuesArray, $whereArray);
		}
	}	
	
	public function NextId($tableName) { 
		$results=$this->RunQuery("show table status like '".$tableName."' ");
		$info = mysql_fetch_array($results);
		return $info["Auto_increment"];
	}
	
	public function MakeValuesArray($keys, $arr) {
		$ans = array();
		foreach ($keys as $key) {
			if (array_key_exists($key, $arr)) {
				$ans[$key] = $arr[$key];
			} else {
				$ans[$key] = '';
			}
		}
		return $ans;
	}
    
    public function enum_select($table, $field) {
    	$query = "SHOW COLUMNS FROM $table LIKE '$field'";
    	$results = $this->RunQuery($query);
    	$row = mysql_fetch_array($results, MYSQL_NUM );
    	preg_match_all("/'(.*?)'/", $row[1], $enum_array );
    	return($enum_array[1]);
    } 
}

?>