<?php

function civicrm_api3_sp_odoo_test_createlabel($params) {
  $sql = "SELECT * FROM `civicrm_odoo_entity`  WHERE entity = 'civicrm_contact' and entity_id = 807085"; // Contact Jaap Jansma sync
  $dao = CRM_Core_DAO::executeQuery($sql);
  //sync this object
  while ($dao->fetch()) {
    $odooEntity = new CRM_Odoosync_Model_OdooEntity($dao);
    $odooEntity->process(true);
  }
  return array();
}