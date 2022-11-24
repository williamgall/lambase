<?php

namespace williamgall\lambase\Model;

use Laminas\Config\Config;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Adapter\Profiler\Profiler;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;

class Base
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
     */
    public function getConfig()
    {
        // always use local.php if it exists 
        if(!file_exists(__DIR__ . '/../../../../config/autoload/local.php'))
        {
            return include __DIR__ . '/../../../../config/autoload/global.php';
        }
        return include __DIR__ . '/../../../../config/autoload/local.php';
    }
    public function getLocalConfig()
    {
        return include __DIR__ . '/../../../../config/autoload/local.php';
    }
    public function getMergedConfig()
    {
        $global = $this->getGlobConfig();
        $local = $this->getLocalConfig();
        return array_merge($global,$local);
    }
    
    public function getGlobConfig()
    {
        return include __DIR__ . '/../../../../config/autoload/global.php';
    }

    public function __construct($params = [])
    {
        $config = $this->getConfig();

        if(!empty($config['dbs']['adapters'][$this->useAdapter]))
        {
            if(empty($this->adapter))
            {
                $this->profiler = new Profiler();
                $this->adapter = new Adapter(
                    $config['dbs']['adapters'][$this->useAdapter],
                    NULL,
                    NULL,
                    $this->profiler
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
    
    public function getProfile()
    {
        $profiler = $this->adapter->getProfiler();
        return $profiler->getLastProfile();
    }
    
    public function getProfiles()
    {
        $profiler = $this->adapter->getProfiler();
        return $profiler->getProfiles();
    }
    
    public function devSendMail()
    {
        $config = $this->getConfig();
//         print_r($config['devOptions']['sendmail'],0);
//         exit;
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
        $results = $this->adapter->query($sql, new ParameterContainer($vars));
//         return $results->toArray();
    }
    
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

    public function insert($data)
    {
        $insert = $this->sql->insert($this->tableName);

        $insert->values($data);
        $sqlString = $this->sql->buildSqlString($insert);

        $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $id = $this->adapter->getDriver()->getLastGeneratedValue();

        return $id;
    }

    public function insertToTable($data,$table)
    {
        $insert = $this->sql->insert($table);
        
        $insert->values($data);
        $sqlString = $this->sql->buildSqlString($insert);
        
        $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);
        
        $id = $this->adapter->getDriver()->getLastGeneratedValue();
        
        return $id;
    }
    
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
    public function delete($id)
    {
        $delete = $this->getDeleteObject();
        $delete->where([$this->pkId => $id]);
        $this->runQueryBuilt($delete);
    }
    
    public function deleteWhere($where)
    {
        if(empty($where))
        {
            
            return false;
        }
        
        $delete = $this->sql->delete($this->tableName);
        
        
        $this->setWhere($delete, $where);
        
        $sqlString = $this->sql->buildSqlString($delete);
//         error_log($sqlString);
        return $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);
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
    
    public function exportData($data)
    {
        echo "// Array with: ".count($data)." Rows";

            var_export($data);
       
        exit;
    }
    public function mailAdmin($messageText)
    {
        if ($this->devSendMail() == FALSE && $_SERVER['APPLICATION_ENV'] == 'development')
        {
//             error_log('skipping mail in dev');
            return;
        }
        $admin = DEV_EMAIL;
        $message = new Message();
        $subject = $messageText;
        
        
        $body = $messageText;
        
        $message->addTo($admin);
        
        // Add environment to subject
        $subject = "[EASP-".APPLICATION_ENV."] ".$subject;
        
        
        $message->addFrom('abroad@msu.edu','Education Abroad');
        
        
        $message->addTo($admin);
        
        $message->setSubject($subject);
        $message->setBody($body);
        $message->setEncoding('UTF-8');
        
        $transport = new SmtpTransport();
        $config = $this->getConfig();
        $options   = new SmtpOptions($config['smtpOptions']);
        $transport->setOptions($options);
        $transport->send($message);
    }
    
    /**
     * sendEmail
     * 
     * @param string | array $messageTo
     * @param string $subject
     * @param string $messageText
     */
    public function sendEmail($messageTo, $subject, $messageText)
    {
        if ($this->devSendMail() == 0 && $_SERVER['APPLICATION_ENV'] == 'development')
        {
            // Return without sending mail
            return;
        }
        
        $message = new Message();
        
        
        $body = $messageText;
        if (APPLICATION_ENV == 'production')
        {
            if (is_array($messageTo))
            {
                foreach ($messageTo as $recip)
                {
                    $message->addTo($recip);
                }
            }else{
                $message->addTo($messageTo);
            }
            $message->addCc(DEV_EMAIL);
        }else{
            $message->addTo(DEV_EMAIL);
            $header = "On production the email would have gone to the following recipients: ";
            if (is_array($messageTo))
            {
                foreach ($messageTo as $recip)
                {
                    $header .= $recip.",";
                }
            }else{
                $header .= $messageTo;
            }
        }
        
        // Add environment to subject
        $subject = "[EASP-".APPLICATION_ENV."] ".$subject;
        
        $message->addFrom('abroad@msu.edu','Education Abroad');
        
        $message->setSubject($subject);
        
        if ($header <> "")
        {
            $body = $header."\n\n".$body;
        }
        $message->setBody($body);
        $message->setEncoding('UTF-8');
        
        $transport = new SmtpTransport();
        $config = $this->getConfig();
        $options   = new SmtpOptions($config['smtpOptions']);
        $transport->setOptions($options);
        $transport->send($message);
    }
    
    public function compareArray($array1, $array2)
    {
        return array_diff_assoc($array1, $array2);
    }
    
    public function getGlobalConfig()
    {
        $path = __DIR__."/../../../..";
        
        $config = new Config(include($path."/config/autoload/global.php"),TRUE);
        $configlocal = new Config(include( $path."/config/autoload/local.php"));
        $config->merge($configlocal);
        $config->setReadOnly();
        return $config;
    }
}
