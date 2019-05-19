<?php

declare(strict_types=1);

namespace entry;


class ProcDb
{
    public $beginTran, $isExceptionExit, $conn, $wrapTranFlag;

    function __construct(bool $beginTran=false, bool $isExceptionExit=true , string $dbKey='01', \PDO $existConn=null)
    {
        $this->beginTran=$beginTran;
        $this->isExceptionExit=$isExceptionExit;
        if($existConn){
            $this->conn=$existConn;
            $this->wrapTranFlag=true;
        }else{
            $this->conn=$this->connectDb($dbKey);
            $this->wrapTranFlag=false;
        }
    }

    //写库用虚拟IP连接, 读库为实际IP的数组
    function connectDb(string $dbKey)
    {
        //常量数组COMM_DBCLUSTER信息为[XX0=>[X.X.X.X,...], XX1=>X.X.X.X, ... ]; XX为moduleNo，moduleNo.0为XX集群的多台读库，moduleNo.1为写库
        $dsnVal = COMM_DBCLUSTER[$dbKey];
        if (is_string($dsnVal)) {
            $resConn = $this->connectRealDb($dsnVal);
            if ($resConn) {
                return $resConn;
            } else {
                Util::echoRetMsg(false, '数据库连接错误!');
            }
        } else {
            while (count($dsnVal) > 0) { //多台读库，随机获取一台，如果这台崩溃，则再随机遍历其他所有读库
                $randIdx = array_rand($dsnVal);
                $dsn = $dsnVal[$randIdx];
                $resConn = $this->connectRealDb($dsn);
                if ($resConn) {
                    return $resConn;
                } else {
                    unset($dsnVal[$randIdx]);
                }
            }
        }
        Util::echoRetMsg(false, '数据库连接错误!'); //所有DB均崩溃, exit process
        exit();
    } //Util::echoRetMsg will exit the process

    function connectRealDb(string $realDsn)
    {
        try {
            $dbConn = new \PDO($realDsn);
            $dbConn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $dbConn->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
            $dbConn->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_TO_STRING);
//        $dbConn->setAttribute(\PDO::ATTR_TIMEOUT, 10); //web环境php的默认超时时间为30s
            $dbConn->query("SET CLIENT_ENCODING TO 'UTF8'"); //如果database是GBK编码则需要这行
            if ($this->beginTran){
                $dbConn->beginTransaction();
            }
        } catch (\Exception $e) {
            error_log("DB Connect Error:" . $e->getMessage());
            return null;
        }
        return $dbConn;
    }


//DB长连接中不能用closeDb，只能在程序中手动commit
    function closeDb():void
    {
        if($this->wrapTranFlag) {
            return;
        }
        else {
            try {
                if ($this->beginTran) {
                    $this->conn->commit();
                }
            } catch (\Exception $e) {
                $this->procDbException($e); //短连接exit
            } finally {
                $this->conn = null;
            }
        }
    }

    static function  getSetStatement(array $setDataArr, array &$valArr):string //$setDataArr格式为:{字段名:value}
    {
        $setArr = [];
//        $setDataArr=array_map(function($val){
//            return Util::checkStrExist($val) ? $val : null;
//        }, $setDataArr); //decimal字段不能set ''字符，所以将这些空字段(包括'', '  ')全部转成null，所有DB字段都能set null
        foreach ($setDataArr as $key => $value) {
            $setArr[]="$key=?";
            $valArr[]=$value;
        }
        if(empty($setArr))
            return '';

        return ' set ' . implode(',', $setArr).' ';
    }

    /*可以处理insert/delete/update/select;execSql是预处理SQL，execArr是?对应的值,
    serialName是需要返回自增主键的自增序列的名称，不能处理游标*/
    function procDb(string $execSql, array $execArr = [], string $serialName = '')
    {
        try {
            $sth = $this->conn->prepare($execSql);
            $sth->execute($execArr);
            if (stripos(trim($execSql), 'select') === 0
                || stripos(trim($execSql), 'with') === 0) { //以select/with开头的SQL
                return $sth->fetchAll(\PDO::FETCH_ASSOC);
            }
            else if (Util::checkStrExist($serialName)) {
                return $this->conn->lastInsertId($serialName); //pgsql需要送serial的name才能返回插入的自增主键,serialName='tablename'_'colname'_seq
            }
        } catch (\Exception $e) {
            $this->procDbException($e);
        }

        return true;
    }

//$serialCol是待获得的自增主键列名，keyArr是输入数组键数组，colbColA是CLOB列的列名数组
    function procOracleDb(string $execSql, array $execArr, string $serialCol='', array $keyArr=[], array $clobColA=[]) //TODO 待测试
    {
        try {
            $sth = $this->conn->prepare($execSql);
            if (stripos(trim($execSql), 'select') === 0
                || stripos(trim($execSql), 'with') === 0){ //以select/with开头的查询SQL
                $sth->execute($execArr);
                return $sth->fetchAll(\PDO::FETCH_ASSOC);//如果遇到LOB字段，用stream_get_contents($row[columnName])获取LOB内容
            }
            else if(stripos(trim($execSql), 'insert') === 0){
                $valNum=count($execArr);
                $insertId=-1;
                list($keyIdx, $bindIdx)=[0,1];
                $clobValIdxA=[];
                do{ // 类似这种，EMPTY_CLOB()不是?占位符，得跳过去，在RETURNING中绑定，例如：("insert into images (id, imagedata, contenttype) " ."VALUES (?, EMPTY_BLOB(), ?) RETURNING imagedata INTO ?");
                    if($clobColA and in_array($keyArr[$keyIdx], $clobColA)){
                        $clobValIdxA[]=$keyIdx;
                    }
                    else{
                        $sth->bindParam($bindIdx, $execArr[$keyIdx]);
                        ++$bindIdx;
                    }
                    ++$keyIdx;

                }while($keyIdx<$valNum);
                for($j=0; $j<count($clobValIdxA); ++$j){
                    $clobStr=$execArr[$clobValIdxA[$j]];
                    $sth->bindParam($bindIdx, $clobStr, \PDO::PARAM_STR, strlen($clobStr)); //oracle clob插入,PARAM_STR还是PARAM_LOB待定？？
                    ++$bindIdx;
                }

                if(Util::checkStrExist($serialCol)){
                    $sth->bindParam($bindIdx, $insertId); //oracle execute时会将auto inc id传入insertId
                }
                if($clobColA){
                    if(!$this->conn->inTransaction()) {
                        $this->conn->beginTransaction();
                    }
                    $sth->execute();
                    $this->conn->commit(); //插入Oracle CLOB必须以事务提交的方式插入
                }
                else{
                    $sth->execute();
                }
                return $insertId; //支持Oracle单笔Insert时返回自增主键值
            }
            else{ // update/delete
                $sth->execute($execArr);
            }
        } catch (\Exception $e) {
            $this->procDbException($e);
        }
        return true;
    }


    /*拼接x=x 和x in(?,?,?)的动态where语句,
    $formDataArr的格式如下：
    =, >, <, >=, &&, @>, <@的map形式为: 'X=?':'X'或'X>?':'X'
    between的map形式为： 'X between ? and ?':[X,X]
    like的map形式为:'X like ?':'X%' 或'X like ?':'%X'
    in/not in的map形式为:'X in/not in (?,?,?)':[X,X,X]
    */
    static function getWhereStatement(array $formDataArr, array &$valArr):string
    {
        $whereArr = [];
        $formDataArr = array_filter($formDataArr, '\comm\checkStrOrArrExist');   //formDataArr的value可能是数组或字符串;对数组而言，只能过滤空数组或者全部由""、"  "、"null"等字符串构成的数组
        foreach ($formDataArr as $key => $value) {
            $whereArr[]=$key; //填充X=?

            if (is_string($value)) {
                //填充值
                $valArr[]=$value;
            } else {
                $valArr = array_merge($valArr, $value);
            }
        }
        if (empty($whereArr))
            return '';
        return ' where ' . implode(' and ', $whereArr) . ' ';
    }


    function procDbException(\Exception $e):void
    {
        if($this->isExceptionExit){ //short connection
            error_log($e->getMessage());
            if($this->beginTran){
                $this->conn->rollBack();
            }
            $this->conn=null;
            Util::echoRetMsg(false, "数据库内部错误！");
        }else{ //long connection,外层catch exception
            throw new \Exception($e->getMessage());
        }
    }
    /*
     * 适合下面这种情况，where语句并非完全动态，动态where语句后面还跟有固定的and语句，如果整个where都是动态的则不能用下面的函数：
     * --------------------------------------------------------
             where  c.chCode ='组件'
    -----------------------------------------------------------------
             and b.ID in(select XXXX)
     */
    static function completeWhereSql(string $whereSql):string
    {
        return Util::checkStrExist($whereSql) ? "$whereSql and " : ' where ';
    }

//dataArr={字段名:value}或[{字段名:value},{字段名:value}],value非空，支持批量插入；单笔会过滤空值，多笔不会过滤空值
    function insertDb(string $tbName, array $dataArr, string $serialName='')
    {
        if (isset($dataArr[0])) {//批量插入
            list($strArr, $valArr)=[[], []];
            foreach ($dataArr as $subArr) {
                $questionMarkStr = implode(',', array_fill(0, count($subArr), '?'));
                $strArr[]="($questionMarkStr)";
                $valArr = array_merge($valArr, array_values($subArr));
            }
            $inColStr = implode(',', array_keys($dataArr[0]));
            $inStr = implode(',', $strArr);
        } else {//单笔
            $dataArr=array_filter($dataArr, '\entry\Util::checkStrExist');
            $inColStr = implode(',', array_keys($dataArr));
            $inStr = '(' . implode(',', array_fill(0, count($dataArr), '?')) . ')';
            $valArr = array_values($dataArr);
        }

        $insSql = "insert into $tbName ($inColStr) values $inStr";
        //print_r($valArr);
        //echo "\n".$insSql;
        $res=$this->procDb($insSql, $valArr, $serialName);

        return $res;
    }


    /*insDataArr={字段名:value}或[{字段名:value},{字段名:value}],Oracle不支持lastInsertId,serialCol为自增主键的字段名
    clobColA为CLOB列列名数组，只支持单笔插入CLOB字段.  单笔会过滤空值，多笔不会过滤空值*/
    function insertOracleDb(string $tbName, array $dataArr, string $serialCol='', array $clobColA=[]) //TODO 待测试
    {
        $appendClobA=[];
        if (isset($dataArr[0])) {//批量插入
            list($strArr, $valArr)=[[], []];
            foreach ($dataArr as $subArr) {
                $queMarkStr = implode(',', array_fill(0, count($subArr), '?'));
                $strArr[]="($queMarkStr)";
                $valArr = array_merge($valArr, array_values($subArr));
            }
            $keyArr=array_keys($dataArr[0]);
            $inColStr = implode(',', $keyArr);
            $inStr = implode(',', $strArr);
        } else {//单笔
            $dataArr=array_filter($dataArr, '\entry\Util::checkStrExist');
            $colNameA=array_keys($dataArr);
            $inValA=[];
            foreach($colNameA as $colName){
                if($clobColA and in_array($colName, $clobColA)){
                    $inValA[]='EMPTY_CLOB()';
                    $appendClobA[$colName]='?';
                }
                else {
                    $inValA[] = '?';
                }
            }

            $inColStr = implode(',', array_keys($dataArr));
            $inStr='(' . implode(',', $inValA) . ')';// ?,?,EMPTY_CLOB(),EMPTY_CLOB(),?...
            $keyArr = $colNameA;
            $valArr = array_values($dataArr);
        }

        $insSql = "insert into $tbName ($inColStr) values $inStr";

        if(isset($serialCol)){
            $appendClobA[$serialCol]='?';
        }
        if($appendClobA) {
            $insSql .= ' RETURNING ' . implode(',', array_keys($appendClobA)) . ' INTO ' . implode(',', array_values($appendClobA)); //RETURNING imagedata1, imagedata2, AutoIncID INTO ?,?,?;
        }

        //echo "$insSql\n";
        $res=$this->procOracleDb($insSql, $valArr, $serialCol, $keyArr, $clobColA);

        return $res;
    }

    function selectDb(string $sql, array $var=[]):array
    {
        //只读交易，随机选择读库, 读写分离后，将只读事务随机路由到读库上
        $resArr=$this->procDb($sql, $var);
        if(!$this->beginTran){
            $this->closeDb();
        }
        return $resArr;
    }

    function freeOpDb(string $sql, array $var=[]):void
    {
        $this->procDb($sql, $var);
        if(!$this->beginTran){
            $this->closeDb();
        }
    }
}

//this function can be other transaction wrap or independent
function inTranExample(\PDO $existConn=null):void
{
    $dbObj=new MyDb(true, true, "01", $existConn);
    // do something
    $dbObj->closeDb();
}

function db_test():void
{
    $dbConn=new ProcDb(true);
    //short Connection
    $dbConn->freeOpDb("update java_test set name=?", ['foo']);
    $dbConn->freeOpDb("update java_test set name=?", ['foo']);
    $dbConn->closeDb();
   
    $dbConn2=new ProcDb(true, false);
    try {//long connection，try/catch exception
        $dbConn2->freeOpDb("update java_test set name=?", ['bar']);
        $dbConn2->freeOpDb("update java_test set name=?", ['JPCAR']);
        $dbConn2->conn->commit();
    } catch (\Exception $e) {
        error_log($e->getMessage());
        $dbConn2->conn->rollBack();
        //回到循环入口
    }

    var_dump((new ProcDb())->insertDb('java_test', [
        [
            'name'=>'haha9',
            'amt'=>'1344.09',
            'age'=>'34',
        ]
    ],
        'java_test_id_seq'));

    var_dump((new ProcDb())->selectDb("select * from java_test where name=?", ['HAHA']));
}

//db_test();