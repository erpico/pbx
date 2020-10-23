<?php

class PBXBlacklist {
    private $container;
    private $db;
    private $auth;
    const FIELDS = [
        "phone" => 0,
        "comment" => 0,
        "action" => 0,
        "message" => 0,
        "number" => 0,
        "deleted" => 0
    ];

    public function __construct($contaiter) {
        $this->container = $contaiter;
        $this->db = $contaiter['db'];
        $this->auth = $contaiter['auth'];
    }

    private function getTableName() {
        return "blacklist";
    }

    public function fetchList($filter = "", $pos = 0, $count = 20, $onlycount = 0) {

        $sql = "SELECT * FROM {$this->getTableName()}";

        $wsql = "";
        if (is_array($filter)) {
            $fields = self::FIELDS;
            $fields["id"] = 1;
            $wsql = "";
            foreach ($filter as $key => $value) { // по полям
                if (isset($fields[$key])) {
                    if (array_key_exists($key,$fields) && (intval($fields[$key]) ? intval($value) : strlen($value) )) {
                        if (strlen($wsql)) {
                            $wsql .= " AND ";
                        }
                        if ($key == "number" || $key == "message" || $key == "action" ) {
                            $wsql .= 'JSON_EXTRACT(action, "$.'.$key.'") LIKE "%'.
                                ($fields[$key] ? intval($value) : trim(addslashes($value))).'%"';
                        } else {
                            $wsql .= self::getTableName() . ".`" . $key . "` " .
                                (intval($fields[$key]) ? "='" : (true ? "LIKE '%" : "='")) . "" .
                                ($fields[$key] ? intval($value) : trim(addslashes($value))) . "" .
                                (intval($fields[$key]) ? "'" : (true ? "%'" : "'"));
                        }
                    }
                }
            }
        }

        if (strlen($wsql)) {
            $sql .= " WHERE ".$wsql;
        }

        if ($count) {
            $sql .= " LIMIT $pos, $count";
        }

        $res = $this->db->query($sql);
        $result = [];
        while ($row = $res->fetch()) {
            $json_array = json_decode($row["action"], true);
            $result[] = [
                "id" => $row["id"],
                "phone" => $row["phone"],
                "comment" => $row["comment"],
                "action" => $this->getViewAction($json_array["action"]),
                "message" => $json_array["message"],
                "number" => $json_array["number"]
            ];
        }
        return $result;
    }

    public function deleteFromBlacklist($id) {
        // удаляет из таблицы
        $sql = "DELETE FROM {$this->getTableName()} WHERE id=:id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam('id',$id);
        return $stmt->execute()?true:false;
    }

    public function removeFromBlacklist($id) {
        // удаляет из вьюшки
        $sql = "UPDATE {$this->getTableName()} SET deleted=1 WHERE id=:id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam('id',$id);
        return $stmt->execute()?true:false;
    }

    public function updateBlacklistItem($id, $params) {
        $sql = "UPDATE {$this->getTableName()} SET phone=:phone, comment=:comment, action=:action WHERE id=:id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam('phone',$params["phone"]);
        $stmt->bindParam('comment',$params["comment"]);
        $stmt->bindParam('action', $params["action"]);
        $stmt->bindParam('id',$id);
        return $stmt->execute()?true:false;
    }

    public function saveBlacklistItem(array $params)
    {
        $sql = "INSERT INTO {$this->getTableName()}(phone, comment, action, deleted) VALUES (:phone, :comment, :action, 0)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam('phone',$params["phone"]);
        $stmt->bindParam('comment',$params["comment"]);
        $stmt->bindParam('action',$params["action"]);
        return $stmt->execute()?true:false;
    }

    private function getViewAction($action){
        if ($action === "disconnect")
            return "Сброс";
        elseif ($action === "transfer")
            return "Перевод";
        else
            return $action;
    }
}

