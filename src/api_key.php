<?php

use Keygen\Keygen;

class PBXApi_keys {
    protected $db;
    private $id = 0;
    const FIELDS = [ "id", "value", "client_id", "last_request", "deleted" ];

    public function __construct($id = 0) {
        global $app;
        $container = $app->getContainer();
        $this->db = $container['db'];

        if ($id != 0){
            $this->id = $id;
        }
    }
    private function getTableName(){
        return 'api_keys';
    }

    public function getAllKeys() {
        $sql = " SELECT api_keys.".implode(",api_keys.", self::FIELDS).", 
        acl_user.name, acl_user.fullname
    FROM ".$this->getTableName()."  
    LEFT JOIN acl_user ON (acl_user.id = ".$this->getTableName().".client_id) 
    ";
        $sql .= "WHERE api_keys.deleted != 1";

        $res = $this->db->query($sql, \PDO::FETCH_ASSOC);
        $result = [];
        while ($row = $res->fetch()) {
            $row['fullname'] = trim($row['fullname'].' ('.$row['name'].')');
            $result[] = $row;

        }
        return $result;
    }

    public function deleteKey($id){
        $sql = " UPDATE ".$this->getTableName()."  SET `deleted` = 1 WHERE id = {$id}";
        $res = $this->db->query($sql);
        if ($res) return true;
        return $this->db->errorInfo();
    }

    public function checkKey($key = null){
        $sql = " SELECT COUNT(*) FROM api_keys WHERE `value` ='".addslashes($key)."' && deleted != 1";
        $res = $this->db->query($sql);
        if ($res && ($row = $res->fetch(PDO::FETCH_NUM))) {
            if(intval($row[0])){
                return false;
            }
        }
        return true;
    }
    public function checkClient($id) {
        $sql = " SELECT COUNT(*) FROM api_keys WHERE `client_id` ='".addslashes($id)."' && deleted != 1";
        $res = $this->db->query($sql);
        if ($res && ($row = $res->fetch(PDO::FETCH_NUM))) {
            if(intval($row[0])){
                return false;
            }
        }
        return true;
    }
    private function generate(){
        return Keygen::token(65)->generate();
    }
    public function saveKey($data = -1) {
        if (is_object($data)) {
            if (!$this->checkClient($data->client_id)) {
                return [ "result" => false, "message" => "У данного клиента уже есть ключ для этой студии."];
            }

            $sql = " INSERT INTO ".$this->getTableName()." ";

            $fsql = "";

            $data->value = $this->generate();
            while (!$this->checkKey($data->value)) {
                $data->value = $this->generate();
            }
            foreach (self::FIELDS as $field) {
                if ($field == "id")  continue;
                if (isset($data->$field)) {
                    if (strlen($fsql)) $fsql .= ",";
                    $fsql .= "`".$field."` = '".addslashes($data->$field)."'";
                }
            }
            if (strlen($fsql)) $sql .= " SET ".$fsql;

            $res = $this->db->query($sql);
            if ($res){
                return[ "result" => true,  "id" => $this->db->lastInsertId()];
            }
        }
        return [ "result" => false, "message" => "Ошибка получения данных"];
    }
    public static function getUserByKey($key = null) {
        if (!is_null($key)){
            global $app;
            $container = $app->getContainer();
            $db = $container['db'];
            $sql = " SELECT client_id, id FROM api_keys WHERE `value` ='".addslashes($key)."' AND deleted != 1 LIMIT 1";

            $res = $db->query($sql);
            if ($res && ($row = $res->fetch(PDO::FETCH_NUM))) {
                $sql = "UPDATE api_keys SET last_request = NOW() where `id` = '".$row[1]."'";
                $res = $db->query($sql);
                return $row[0];
            }
        }
        return 0;
    }
}
