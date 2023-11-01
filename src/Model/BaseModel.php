<?php

namespace williamgall\lambase\Model;

use Laminas\Config\Config;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;

class BaseModel
{
    const RESULTS_TO_ARRAY = 1;

    public $adapter;
    public $sql;
    public $useAdapter;
    public $tableName;
    public $params;
    public $dbDriver;

    /**
     * getConfig
     * gets the applications configuration
     * @return array
     * Tested
     */
    public function getConfig()
    {
        // always use local.php if it exists
        if(!file_exists( './config/autoload/local.php'))
        {
            return include './config/autoload/global.php';
        }
        return include './config/autoload/local.php';
    }

    /**
     *
     * Tested
     * @return mixed
     */
    public function getLocalConfig()
    {
        return include './config/autoload/local.php';
    }
    public function getMergedConfig()
    {
        $global = $this->getGlobConfig();
        $local = $this->getLocalConfig();
        return array_merge($global,$local);
    }

    /**
     * Tested
     * @return mixed
     */
    public function getGlobConfig()
    {
        return include './config/autoload/global.php';
    }

    public function __construct($params = [])
    {
        $config = $this->getConfig();

        if(!empty($config['dbs']['adapters'][$this->useAdapter]))
        {
            if(empty($this->adapter))
            {
                $this->adapter = new Adapter(
                    $config['dbs']['adapters'][$this->useAdapter]
                );
            }
        }
        if (! isset($config['dbs']['adapters'][$this->useAdapter]))
        {
            throw new \Exception('No Config for '.$this->useAdapter." ".__FILE__);
        }
        $this->dbDriver = $config['dbs']['adapters'][$this->useAdapter]['driver'];
        $this->sql = new Sql($this->adapter);
        $this->setParams($params);
    }

    // TODO Probably not the best spot for this
    public function devSendMail()
    {
        $config = $this->getConfig();
        if (isset($config['devOptions']) && isset($config['devOptions']['sendmail']))
        {
            if ($config['devOptions']['sendmail'] == 0 || $config['devOptions']['sendmail'] == "FALSE" )
            {
                return FALSE;
            }else{
                return TRUE;
            }
//             return (bool) $config['devOptions']['sendmail'];
        }
        return "Error";
    }

    /**
     * getUseAdapter
     *
     * gets the db adapter in use for debugging and switching environments from mysql to oracle or mssql
     * TESTED
     * @return string
     */
    public function getUseAdapter()
    {
        return $this->useAdapter;
    }

    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * Tested
     * @param $query
     * @param $wheres
     * @return void
     */
    public function setWhere(&$query, $wheres)
    {
        if(is_numeric($wheres))
        {
            $wheres = [ $this->pkId => $wheres ];
        }

        if(!empty($wheres))
        {
            foreach ($wheres as $k => $where)
            {
                if(is_array($where))
                {
                    $query->where($where);
                }else {
                    $query->where([$k => $where]);
                }
            }
        }
    }

    /**
     *
     * Tested
     * @param $formData
     * @return array
     */
    public function convertFormData($formData)
    {
        $dataArray = array();
        foreach ($formData as $key => $value)
        {
            if (strtolower($key) != 'submit')
            {
                $dataArray[$key] = $value;
            }
        }
        unset($dataArray['submit']);
        return $dataArray;
    }

    public function runQueryBuilt($sql)
    {
        $queryString = $this->sql->buildSqlString($sql);

        if(get_class($sql) == Select::class)
        {

            $results = $this->adapter->query($queryString, $this->adapter::QUERY_MODE_EXECUTE);
            return $this->resultsToArray($results);
        }

        if(get_class($sql) == Delete::class)
        {

            $results = $this->adapter->query($queryString, $this->adapter::QUERY_MODE_EXECUTE);
            return $results;
        }
    }

    public function query($sql, $vars = [])
    {
        $results = $this->adapter->query($sql, new ParameterContainer($vars));
        return $results->toArray();
    }
    public function query2($sql, $vars = [])
    {
        // TODO Test
        $results = $this->adapter->query($sql, new ParameterContainer($vars));
//         return $results->toArray();
    }

    /**
     *
     * Tested
     * @param $sql
     * @param $vars
     * @return array|mixed
     */
    public function fetchRow($sql, $vars = [])
    {
        if(! is_array($vars))
        {
            $vars = [$vars];
        }
        $results = $this->adapter->query($sql, new ParameterContainer($vars));
        $resultsArray = $results->toArray();
        if (! empty($resultsArray))
        {
            return $resultsArray[0];
        }
        return $resultsArray;
    }

    /**
     * fetchAll
     *
     * fetches all rows from query
     *
     * Tested
     * @param string $sql
     * @param array $vars
     * @return array
     */
    public function fetchAll($sql, $vars=[])
    {
        if(! is_array($vars))
        {
            $vars = [$vars];
        }
        $results = $this->adapter->query($sql, new ParameterContainer($vars));
        return $results->toArray();
    }

    public function getSelectObject($table = '')
    {
        if(empty($table))
        {
            $table = $this->tableName;
        }

        $queryObject = $this->sql->select($table);
        return $queryObject;
    }

    public function getDeleteObject()
    {
        $queryObject = $this->sql->delete($this->tableName);
        return $queryObject;
    }


    public function select($wheres = [], $returnType = self::RESULTS_TO_ARRAY)
    {
        $returnSingleRow = false;

        if(is_numeric($wheres))
        {
            $wheres = [$this->pkId => $wheres];
            $returnSingleRow = true;
        }

        $select = $this->sql->select($this->tableName);
        $this->setWhere($select, $wheres);
        $this->setLimit($select);

        $selectString = $this->sql->buildSqlString($select);
        $results = $this->adapter->query($selectString, $this->adapter::QUERY_MODE_EXECUTE);

        if($returnType == self::RESULTS_TO_ARRAY)
        {
            $results =  $this->resultsToArray($results);
        }

        if($returnSingleRow && !empty($results[0])){

            $results = $results[0];
        }
        return $results;
    }

    public function resultsToArray(ResultSet $results)
    {
        return $results->toArray();
    }

    /**
     * Tested
     * @param $data
     * @return mixed
     */
    public function insert($data)
    {
        $insert = $this->sql->insert($this->tableName);

        $insert->values($data);
        $sqlString = $this->sql->buildSqlString($insert);

        $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $id = $this->adapter->getDriver()->getLastGeneratedValue();

        return $id;
    }

    /**
     * Tested
     * @param $data
     * @param $table
     * @return mixed
     */
    public function insertToTable($data, $table)
    {
        $insert = $this->sql->insert($table);

        $insert->values($data);
        $sqlString = $this->sql->buildSqlString($insert);

        $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $id = $this->adapter->getDriver()->getLastGeneratedValue();

        return $id;
    }

//    public function beginTrans()
//    {
//        $this->adapter->getDriver()->getConnection()->beginTransaction();
//    }

    public function insertToTableTrans($data,$table=NULL)
    {
        if ($table===NULL)
        {
            $insert = $this->sql->insert($this->tableName);
        }else{
            $insert = $this->sql->insert($table);
        }

        $insert->values($data);
        $statement1 = $this->sql->prepareStatementForSqlObject($insert);
        $result1 = $statement1->execute();
        if ($result1 instanceof ResultInterface && $result1->getAffectedRows())
        {
            return 1;

        }else{
            return 0;
        }
    }
//    public function commitTrans()
//    {
//        $this->adapter->getDriver()-getConnection()->commit();
//    }

//    public function rollbackTrans()
//    {
//        $this->adapter->getDriver()->getConnection()->rollback();
//    }
    /**
     *
     * Tested
     * @param array $data
     * @param int || array $where
     *
     */
    public function update($data, $where)
    {
        if(empty($where))
        {

            return false;
        }

        $update = $this->sql->update($this->tableName);
        $update->set($data);

        if(is_numeric($where))
        {

            $where = [$this->pkId => $where];
        }
        $this->setWhere($update, $where);

        $sqlString = $this->sql->buildSqlString($update);

        $results = $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);
        return $results;
    }

    /**
     *
     * Tested
     * @param $data
     * @param $where
     * @param $table
     * @return false|StatementInterface|ResultSet|ResultSetInterface
     */
    public function updateTable($data, $where, $table)
    {
        if(empty($where) || empty($table))
        {
            return false;
        }

        $update = $this->sql->update($table);
        $update->set($data);

        if(is_numeric($where))
        {

            $where = [$this->pkId => $where];
        }
        $this->setWhere($update, $where);

        $sqlString = $this->sql->buildSqlString($update);

        $results = $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);
        return $results;
    }

    /**
     * delete
     *
     * deletes a row from the table by id
     * Tested
     *
     * @param int $id
     */
    public function delete($id)
    {
        $delete = $this->getDeleteObject();
        $delete->where([$this->pkId => $id]);
        $this->runQueryBuilt($delete);
    }

    /**
     * Tested
     * @param $where
     * @return false|StatementInterface|ResultSet|ResultSetInterface
     */
    public function deleteWhere($where)
    {
        if(empty($where))
        {
            return false;
        }

        $delete = $this->sql->delete($this->tableName);

        $this->setWhere($delete, $where);

        $sqlString = $this->sql->buildSqlString($delete);
        return $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);
    }
    public function deleteIdFromTable($id, $tableName)
    {
        $delete = $this->sql->delete($tableName);
        $delete->where([$this->pkId => $id]);
        return $this->runQueryBuilt($delete);
    }

    public function setLimit(&$select)
    {
        if(!empty($this->params['rows'])){

            $select->limit($this->params['rows']);
            $select->offset(0);
        }
    }

    public function setOrder(&$select)
    {
        if(!empty($this->params['order']))
        {

            $select->order($this->params['order']);
        }
        else {

            $select->order($this->pkId);
        }
    }

    public function __destruct()
    {
        if(!empty($this->adapter)){

            $this->adapter->getDriver()->getConnection()->disconnect();
        }
    }

    /**
     *
     * Tested
     * @param $data
     * @param $pretty
     * @return void
     */
    public function debug($data, $pretty=0)
    {
        if ($_SERVER['APPLICATION_ENV'] == 'development' || $_SERVER['APPLICATION_ENV'] == 'testing')
        {
            if (is_array($data))
            {
                echo "<p>Array with: ".count($data)." Rows</p>";
                echo "<pre>";
                if ($pretty)
                {
                    var_export($data);
                }else{
                    print_r($data,FALSE);
                }
                echo "</pre>";
                exit;
            }else{
                if (is_bool($data))
                {
                    if ($data)
                    {
                        echo "boolean TRUE";
                    }else{
                        echo "boolean FALSE";
                    }
                    exit;
                }
                if (is_int($data))
                {
                    echo "<p>Integer</p>";
                    echo "<p>".$data."</p>";
                    exit;
                }
                echo "<p>String</p>";
                echo "<p>".$data."</p>";
                exit;
            }
        }
    }

    /**
     * Tested
     * @param $data
     * @return void
     */
    public function exportData($data)
    {
        echo "// Array with: ".count($data)." Rows";

        var_export($data);

        exit;
    }

    /**
     * Tested
     * @param $array1
     * @param $array2
     * @return array
     */
    public function compareArray($array1, $array2)
    {
        return array_diff_assoc($array1, $array2);
    }

    public function getGlobalConfig()
    {
        $config = new Config(include("./config/autoload/global.php"),TRUE);
        $configlocal = new Config(include("./config/autoload/local.php"));
        $config->merge($configlocal);
        $config->setReadOnly();
        return $config;
    }

    /**
     *
     * Tested
     * @param $tableName
     * @param $dbName
     * @return mixed
     */
    public function getNextAutoIncValue($tableName, $dbName)
    {
        $result = $this->fetchRow("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = '".$tableName."' AND table_schema = '".$dbName."'");
        return $result['AUTO_INCREMENT'];
    }

    /**
     *
     * Tested
     * @param string $table
     * @return array
     */
    public function getColumnsForTable(string $table)
    {
        $returnArray = [];
        $result = $this->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_name = ?",$table);
        foreach ($result as $item)
        {
            $returnArray[] = $item['column_name'];
        }
        return $returnArray;
    }
}