<?php

/**
 * Collection of upgrade steps
 */
class CRM_Spodoosync_Upgrader extends CRM_Spodoosync_Upgrader_Base {

  public function install() {
    $this->addPaymentInstruments();
    $this->executeCustomDataFile('xml/odoo.xml');
  }
  
  public function upgrade_1001() {
    $this->addPaymentInstruments();
    return true;
  }

  /**
   * Upgrade for version 1.3 - step 1
   *
   * Create custom fields.
   */
  public function upgrade_1301() {
    //add Contact in Odoo custom field for all contacts
    $this->executeCustomDataFile('xml/odoo.xml');
    return true;
  }

  /**
   * Upgrade for version 1.3 - step 2
   *
   * Set Contact in Odoo to yes when contact is already pushed.
   */
  public function upgrade_1302() {
    $sql = "INSERT INTO `civicrm_value_odoo_contact` (`entity_id`, `in_odoo`) SELECT o.`entity_id`, 1 AS in_odoo FROM `civicrm_odoo_entity` o WHERE `entity` = 'civicrm_contact' AND `status` = 'SYNCED'";
    CRM_Core_DAO::executeQuery($sql);
    return true;
  }

  public function upgrade_1860() {
    $billingLocationType = civicrm_api3('LocationType', 'getvalue', array('name' => 'Billing', 'return' => 'id'));
    $sqlCreateTable = "CREATE TABLE `civicrm_address_upgrade1860` AS SELECT id, contact_id FROM civicrm_address WHERE location_type_id = %1";
    $sqlCreateTableParams[1] = array($billingLocationType, 'Integer');
    $selectPrimarySql = "SELECT id FROM civicrm_address WHERE is_primary = 1 AND is_billing = 0 AND contact_id NOT IN (SELECT contact_id FROM `civicrm_address_upgrade1860`)";
    $updateSql = "UPDATE `civicrm_address` SET `is_billing` = '1' WHERE id = %1";
    $selectBillingSql = "SELECT id FROM `civicrm_address` WHERE is_billing = 0 AND `id` IN (SELECT id FROM `civicrm_address_upgrade1860`)";
    $odooSyncTableUpdateSql = "UPDATE civicrm_odoo_entity SET change_date = NOW(), `action` = 'UPDATE', `last_error` = NULL, `last_error_date` = NULL, `status` = 'OUT OF SYNC', `weight` = 0 WHERE entity = 'civicrm_address' AND entity_id = %1";

    CRM_Core_DAO::executeQuery($sqlCreateTable, $sqlCreateTableParams);
    $dao = CRM_Core_DAO::executeQuery($selectPrimarySql);
    while($dao->fetch()) {
      $updateSqlParams[1] = array($dao->id, 'Integer');
      CRM_Core_DAO::executeQuery($updateSql, $updateSqlParams);
      CRM_Core_DAO::executeQuery($odooSyncTableUpdateSql, $updateSqlParams);
    }

    $dao = CRM_Core_DAO::executeQuery($selectBillingSql);
    while($dao->fetch()) {
      $updateSqlParams[1] = array($dao->id, 'Integer');
      CRM_Core_DAO::executeQuery($updateSql, $updateSqlParams);
      CRM_Core_DAO::executeQuery($odooSyncTableUpdateSql, $updateSqlParams);
    }

    CRM_Core_DAO::executeQuery("DROP TABLE `civicrm_address_upgrade1860`");

    return true;
  }
  
  protected function addPaymentInstruments() {
    $pi_gid = civicrm_api3('OptionGroup', 'getvalue', array('return' => 'id', 'name' => 'payment_instrument'));
    $this->addOptionValue('sp_acceptgiro', 'Acceptgiro (Beschikbaar in Odoo)', $pi_gid);
    $this->addOptionValue('sp_automatischincasse', 'Automatisch incasso (Beschikbaar in Odoo)', $pi_gid);
  }
  
  protected function addOptionValue($name, $label, $option_group_id) {
    try {
      $exist_id = civicrm_api3('OptionValue', 'getvalue', array('return' => 'id', 'name' => $name, 'option_group_id' => $option_group_id));
      return; //aleardy exist
    } catch (Exception $e) {
      //do nothing
    }
    
    $params['name'] = $name;
    $params['label'] = $label;
    $params['option_group_id'] = $option_group_id;
    civicrm_api3('OptionValue','create', $params);
  }

}
