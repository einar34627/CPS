<?php
$host = '153.92.15.81';
$dbname = 'u514031374_cpas';
$username = 'u514031374_cpas'; 
$password = 'cpasP@55w0rd'; 

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => true,
        ]
    );
} catch (PDOException $e) {
    if (!class_exists('CPSA_NullPDO')) {
        class CPSA_NullPDO {
            public function query($sql) { throw new PDOException('Database connection failed'); }
            public function prepare($sql) { throw new PDOException('Database connection failed'); }
            public function exec($sql) { throw new PDOException('Database connection failed'); }
        }
    }
    $pdo = new CPSA_NullPDO();
}
?>
