<?php
use Erpico\User;

class PBXSettings {
  protected $db;
  private $id;
  private $name;
 

  public function __construct($id = 0) {
    global $app;    
    $container = $app->getContainer();
    $this->db = $container['db'];
    $this->setId(intval($id));
    
    $this->user = $container['auth'];
  }

  public function getId() {
    return $this->id;
  }
  
  public function setId($id) {
    return $this->id = intval($id);
  }

  private function getDefaultSettings() {
    $sql = "SELECT  id, handle, val, updated FROM cfg_setting";
    $result = [];
    $res = $this->db->query($sql);
    while ($row = $res->fetch()) {
      $result[] = $row;
    }
    return $result;
  }

  private function getUserSettingByHandle($handle = "", $user_id) {
    $sql = "SELECT id, handle, val FROM cfg_user_setting WHERE handle = '".trim(addslashes($handle))."' AND acl_user_id = '".intval($user_id)."' LIMIT 1";
    $res = $this->db->query($sql);
    $row = $res->fetch();
    return $row;
  }

  private function getGroupSettingByHandle($handle = "", $group_id) {
    $sql = "SELECT id, handle, val FROM cfg_group_setting WHERE handle = '".trim(addslashes($handle))."' AND acl_user_group_id = '".intval($group_id)."' LIMIT 1";
    $res = $this->db->query($sql);
    $row = $res->fetch();
    return $row;
  }

  public function getUserSettings($user_id) {
    $settings = $this->getDefaultSettings();
    $result = [];    
    $user = new User();
    $user_groups = $user->getUserGroups($user_id)['ids'];
    if (count($settings)) {
      foreach ($settings as $setting) {
        $row = [];
        $row['handle'] = $setting['handle'];
        $row['main'] = false;        
        $row['val'] = $this->getUserSettingByHandle($setting['handle'], $user_id)['val'];
        if (isset($row['val']) || strlen($row['val'])) {
          $row['main'] = true;
        } else {
          foreach ($user_groups as $group_id) {
            if (strlen($row['val']) || isset($row['val'])) {
              break;
            } else {
              $row['val'] = $this->getGroupSettingByHandle($setting['handle'], $group_id)['val'];
            }
          }
          if (!isset($row['val']) || !strlen($row['val'])) {
            $row['val'] = $setting['val'];
          }
        }
        $result[] = $row;
      }      
    }    
    usort ($result, function ($left, $right) {
      return $right['main'] - $left['main'];
    });
    return $result;
  } 
  
  public function getGroupSettings($group_id) {
    $settings = $this->getDefaultSettings();
    $result = [];    
    if (count($settings)) {
      foreach ($settings as $setting) {
        $row = [];
        $row['handle'] = $setting['handle'];
        $row['main'] = false;        
        $row['val'] = $this->getGroupSettingByHandle($setting['handle'], $group_id)['val'];
        if (isset($row['val']) || strlen($row['val'])) {
          $row['main'] = true;
        } else {
          if (!isset($row['val']) || !strlen($row['val'])) {
            $row['val'] = $setting['val'];
          }
        }
        $result[] = $row;
      }      
    }    
    usort ($result, function ($left, $right) {
      return $right['main'] - $left['main'];
    });
    return $result;
  }

  public function setUserSettings($user_id, $settings)
  {
    if (is_string($settings)) {
      if (strlen($settings)) {
        $jd = json_decode($settings);
        foreach ($jd as $row) {
          $this->insertOrUpdateUserSetting($user_id, $row->handle, $row->val, $row->main);
        }
        return true;
      }
    }
    return false;
  }

  public function insertOrUpdateUserSetting($user_id, $handle, $val, $update = true) {
    if (!intval($user_id)) return false;
    if (boolval($update)) {
      $setting = $this->getUserSettingByHandle($handle, $user_id);
      if (isset($setting['id']) && intval($setting['id']) && $setting['handle'] == $handle) {
        $sql = "UPDATE cfg_user_setting 
        SET val = '".trim(addslashes($val))."', updated = NOW() 
        WHERE id = '".intval($setting['id'])."'";
        $this->db->query($sql);
      }
    } else {
      $sql = "INSERT INTO cfg_user_setting 
        SET val = '".trim(addslashes($val))."', updated = NOW(), handle = '".trim(addslashes($handle))."', 
        acl_user_id = '".intval($user_id)."'";
      $this->db->query($sql);
    }
    return true;
  }

  public function insertOrUpdateGroupSetting($group_id, $handle, $val, $update = true) {
    if (!intval($group_id)) return false;
    if (boolval($update)) {
      $setting = $this->getGroupSettingByHandle($handle, $group_id);
      if (isset($setting['id']) && intval($setting['id']) && $setting['handle'] == $handle) {
        $sql = "UPDATE cfg_group_setting 
        SET val = '".trim(addslashes($val))."', updated = NOW() 
        WHERE id = '".intval($setting['id'])."'";
        $this->db->query($sql);
      }
    } else {
      $sql = "INSERT INTO cfg_group_setting 
        SET val = '".trim(addslashes($val))."', updated = NOW(), handle = '".trim(addslashes($handle))."', 
        acl_user_group_id = '".intval($group_id)."'";
      $this->db->query($sql);
    }
    return true;
  }

  public function setGroupSettings($group_id, $settings)
  {
    if (is_string($settings)) {
      if (strlen($settings)) {
        $jd = json_decode($settings);
        foreach ($jd as $row) {
          $this->insertOrUpdateGroupSetting($group_id, $row->handle, $row->val, $row->main);
        }
        return true;
      }
    }
    return false;
  }
}
