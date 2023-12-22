<?php

class UserLogger {

    // DB connection variables
    private $dbHost = 'localhost';
    private $dbUserName = 'root';
    private $dbPassword = '1234';
    private $dbName = 'mydatabase';
    private $dbConnectionString;
    private $dbConnection;

    // Log table variables
    private $tableName = 'userlogs';
    private $primaryKeyColumnName = 'id';

    // Aşağıdaki dizide değişiklik yapılırsa addLog() fonksiyonunda da değişiklik gerektirebilir.
    private $tableColumns = [
        [ 'name' =>'log_ip', 'type'=>'VARCHAR(39)', 'nullable'=>true, 'default'=>null ],
        [ 'name' =>'log_os', 'type'=>'TEXT', 'nullable'=>true, 'default'=>null ],
        [ 'name' =>'log_browser_name', 'type'=>'TEXT', 'nullable'=>true, 'default'=>null ],
        [ 'name' =>'log_browser_vers', 'type'=>'TEXT', 'nullable'=>true, 'default'=>null ],
        [ 'name' =>'log_page_name', 'type'=>'TEXT', 'nullable'=>true, 'default'=>null ],
        [ 'name' =>'log_description', 'type'=>'TEXT', 'nullable'=>true, 'default'=>null ],
        [ 'name' =>'log_date', 'type'=>'DATETIME', 'nullable'=>false, 'default'=>null ]
    ];
    private $tableCreated = false;
    
    // Usaful variables
    public $lastSqlQuery = null;
    public $lastError = null;
    private $user_agent = null;

    public function __construct($addLog=false,$description)
    {
        date_default_timezone_set('Europe/Istanbul');
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $this->dbConnectionString = 'mysql:host='.$this->dbHost.';dbname='.$this->dbName.';charset=utf8';
        if($addLog===true) $this->addLog(true,$description);
    }

    private function openDbConnection()
    {
        try {
            $this->dbConnection = new PDO($this->dbConnectionString,$this->dbUserName,$this->dbPassword);
            return true;
        }
        catch(Exception $e) { $this->lastError = $e->errorMessage(); return false; }
    }

    private function closeDbConnection()
    {
        $this->dbConnection = null;
    }

    public function createLogTable()
    {
        if($this->tableCreated) return false;
        $sql = 'CREATE TABLE '.$this->dbName.'.'.$this->tableName.' (';
        $sql.= $this->primaryKeyColumnName.' INT NOT NULL AUTO_INCREMENT, ';
        foreach($this->tableColumns as $column) {
            $sql.= $column['name'].' ';
            $sql.= $column['type'].' ';
            $sql.= $column['nullable'] ? 'NULL' : 'NOT NULL';
            $sql.= $column['default']!=null ? ' DEFAULT '.$column['default'].', ' : ', ';
        }
        $sql.= 'PRIMARY KEY('.$this->primaryKeyColumnName.')) ';
        $sql.= 'ENGINE=InnoDB CHARSET=utf8 COLLATE utf8_unicode_ci;';
        $this->lastSqlQuery = $sql;
        try {
            if($this->openDbConnection()===false) {
            $this->lastError = 'Veritabanı bağlantısı kurulamadı.';
            return false;
        }
        if ($result = $this->dbConnection->query("SHOW TABLES LIKE '".$this->tableName."'")) {
            if($result->num_rows > 0) {
                $this->lastError = 'Tablo veritabanında zaten oluşturulmuş.';
                $this->tableCreated = true;
                return false;
            }
        }
        if($this->dbConnection->query($sql)===true) $this->tableCreated=true;
        $this->closeDbConnection();
        }
        catch(Exception $e) {
            $this->lastError = $e->getMessage();
            $this->closeDbConnection();
            return false;
        }
        return $this->tableCreated;
    }

    public function addLog($page_name=null, $description=null)
    {
        $log_ip = $this->getIPAddress();
        $log_masked_ip = $_SERVER['REMOTE_ADDR'];
        $log_os = $this->getOS();
        $log_browser_name = $this->getBrowserName();
        $log_browser_vers = $this->getBrowserVersion();
        if($page_name===true) $page_name = $_SERVER['REQUEST_URI'];
        elseif($page_name===false) $page_name = $_SERVER['SCRIPT_NAME'];
        $log_date = date('Y-m-d H:i:s');
        try {
            if($this->openDbConnection()===false) {
                $this->lastError = 'Veritabanı bağlantısı kurulamadı.';
                return false;
            }
            $query = $this->dbConnection->prepare('
                INSERT INTO '.$this->tableName.' SET
                log_ip = ?,
                log_os = ?,
                log_browser_name = ?,
                log_browser_vers = ?,
                log_page_name = ?,
                log_description = ?,
                log_date = ?
            ');
            $insert = $query->execute(array(
                $log_ip,
                $log_os,
                $log_browser_name,
                $log_browser_vers,
                $page_name,
                $description,
                $log_date
            ));
            if ($insert) {
                $this->lastSqlQuery = $query->queryString;
                $this->closeDbConnection();
                return true;
            }
            else {
                $this->lastError = 'Kullanıcının beritabanına loglanması işlemi başarısız oldu.';
                $this->closeDbConnection();
                return false;
            }
        }
        catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->closeDbConnection();
            return false;
        }
    }

    public function getOS()
    {
        $os_array = array(
            '/windows nt 10/i' => 'Windows 10',
            '/windows nt 6.3/i' => 'Windows 8.1',
            '/windows nt 6.2/i' => 'Windows 8',
            '/windows nt 6.1/i' => 'Windows 7',
            '/windows nt 6.0/i' => 'Windows Vista',
            '/windows nt 5.2/i' => 'Windows Server 2003/XP x64',
            '/windows nt 5.1/i' => 'Windows XP',
            '/windows xp/i' => 'Windows XP',
            '/windows nt 5.0/i' => 'Windows 2000',
            '/windows me/i' => 'Windows ME',
            '/win98/i' => 'Windows 98',
            '/win95/i' => 'Windows 95',
            '/win16/i' => 'Windows 3.11',
            '/macintosh|mac os x/i' => 'Mac OS X',
            '/mac_powerpc/i' => 'Mac OS 9',
            '/linux/i' => 'Linux',
            '/ubuntu/i' => 'Ubuntu',
            '/iphone/i' => 'iPhone',
            '/ipod/i' => 'iPod',
            '/ipad/i' => 'iPad',
            '/android/i' => 'Android',
            '/blackberry/i' => 'BlackBerry',
            '/webos/i' => 'Mobile'
        );
        $os_platform = null;
        foreach($os_array as $regex => $value) if(preg_match($regex, $this->user_agent)) {
            $os_platform = $value;
            break;
        }
        return $os_platform;
    }

    public function getBrowserName()
    {
        if (strpos($this->user_agent,'MSIE') !== false) return 'Internet Explorer';
        elseif(strpos($this->user_agent,'Trident') !== false) return 'Internet Explorer';
        elseif(strpos($this->user_agent,'Firefox') !== false) return 'Mozilla Firefox';
        elseif(strpos($this->user_agent,'Chrome') !== false) return 'Google Chrome';
        elseif(strpos($this->user_agent,'Opera Mini') !== false) return "Opera Mini";
        elseif(strpos($this->user_agent,'Opera') !== false) return "Opera";
        elseif(strpos($this->user_agent,'Safari') !== false) return "Safari";
        else return null;
    }

    public function getBrowserVersion()
    {
        $bname = $this->getBrowserName();
        if($bname=='Internet Explorer') {
            if(strrpos($this->user_agent, 'MSIE') !== false)
            return (int)explode('MSIE', $this->user_agent)[1];
            if(strrpos($this->user_agent, 'Trident/') !== false)
            return (int)explode('rv:', $this->user_agent)[1];
        }
        $ub = null;
        if ($bname == 'Mozilla Firefox') $ub = "Firefox";
        elseif($bname == 'Google Chrome') $ub = "Chrome";
        elseif($bname == 'Opera') $ub = "Opera";
        elseif($bname == 'Safari') $ub = "Safari";
        elseif($bname == 'Netscape') $ub = "Netscape";
        else return null;

        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>'.join('|',$known) .')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if(!preg_match_all($pattern, $this->user_agent, $matches)) return null;

        $version = null;
        $i = count($matches['browser']);
        if($i!=1) {
            if(strripos($this->user_agent,"Version") < strripos($this->user_agent,$ub))
                $version=$matches['version'][0];
            else $version=$matches['version'][1];
        } else $version= $matches['version'][0];
        if ($version==null || $version=="") return null;
        return $version;
    }


    public function getIPAddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
        if (!empty($_SERVER['REMOTE_ADDR'])) return $_SERVER['REMOTE_ADDR'];
        else return null;
    }

}

/*
KURULUM

1. Bu dosya sayfaya include edilir.

include('UserLogger.php');

2. Sınıftaki veritabanı bağlantı değerleri düzenlenir.

// DB connection variables
private $dbHost = 'localhost';
private $dbUserName = 'root';
private $dbPassword = '';
private $dbName = 'mydatabase';

3. Veritabanında tablonun oluşturulması için bir defaya mahsus aşağıdaki komut çalıştırılır.
Veritabanında tablo oluştuktan sonra bu komut bir daha kullanılmamalıdır.

(new UserLogger())->createLogTable();

KULLANIM

1. Yalnızca loglama yapılacak, başka bir işlem yapılmayacaksa aşağıdaki satırlardan istenen biri bullanılabilir.

(new UserLogger())->addLog(); // Bu örnekte, sayfa adı ve açıklama verileri null kaydedilir.

$logger = new UserLogger(); $logger->addLog(); // Bu örnekte sayfa adı ve açıklama verileri null kaydedilir.

new UserLogger(true); // Bu örnekte direkt olarak addLog(true) fonksiyonu işletilir.

new UserLogger(true,'Açıklama'); // Börnekte direkt olarak addLog(true,'Açıklama') fonksiyonu işletilir.

2. Sayfa adı, addLog() fonksiyonunu ilk parametresi ile yöntemlerden biriyle kaydedilebilir.
İlk parametre boş bırakıldığında sayfa adı null olarak kaydedilecektir.

(new UserLogger())->addLog(false); // Sayfa adını otomatik alacaktır.

(new UserLogger())->addLog(true); // Sayfa adını, GET değerleriyle birlikte olarak otomatik alacaktır.

(new UserLogger())->addLog('Sayfa Adı'); // Sayfa adı olarak string veri gönderilebilir.

3. Loglamayla ilgili açıklama da kaydetmek için addLog()'un ikinci parametresi kullanılabilir.

(new UserLogger())->addLog(null, 'Sayfa adı kaydedilmeden yapılan loglama için açıklama');

(new UserLogger())->addLog('Sayfa Adı', 'Sayfa adı kaydedilerek yapılan loglama için açıklama');

4. Sınıfın loglama yapmak için kullandığı fonksiyonlar public tanımlı olduğu için kullanılabilirler:

$logger = new UserLogger();
echo $logger->getIPAddress(); // İstemcinin IP adresi
echo $logger->getBrowserName(); // İstemcinin tarayıcı adı
echo $logger->getBrowserVersion(); // İstemcinin tarayıcı sürümü
echo $logger->getOS(); // İstemcinin işletim sistemi

5. Loglama sırasında bir hata oluşup oluşmadığı kontrol edilebilir:

$logger = new UserLogger();
if(!$logger->addLog()) echo $logger->lastError;

6. İşletilen son sql sorgusu görüntülenebilir:

$logger = new UserLogger();
$logger->addLog();
echo $logger->lastSqlQuery; // PDO kullanıldığı için gönderilen veriler ? karakteriyle görüntülenecektir.

*/

?>
