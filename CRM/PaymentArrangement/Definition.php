<?php

class CRM_PaymentArrangement_Definition extends CRM_Odoosync_Model_ObjectDefinition implements CRM_Odoosync_Model_ObjectDependencyInterface {
  
  /**
   *
   * @var CRM_Ibanaccounts_Config 
   */
  protected $config;
  
  public function __construct() {
    $this->config = CRM_Paymentarrangement_Config::singleton();
  }
  
  public function isObjectNameSupported($objectName) {
    if ($objectName == $this->config->getPaymentArrangementGroup('table_name')) {
      return true;
    }
    return false;
  }
  
  public function getName() {
    return $this->config->getPaymentArrangementGroup('table_name');
  }
  
  public function getCiviCRMEntityName() {
    return $this->config->getPaymentArrangementGroup('table_name');
  }
  
  public function getSynchronisatorClass() {
    return 'CRM_PaymentArrangement_Synchronisator';
  }
  
  public function getSyncDependenciesForEntity($entity_id, $data=false) {
    $dep = array();
    try {
      if (is_array($data) && isset($data['contribution_id'])) {
         $contribution_id = $data['contribution_id'];
         $dep[] = new CRM_Odoosync_Model_Dependency('civicrm_contribution', $contribution_id, +5);
      }
    } catch (Exception $ex) {
       //do nothing
    }
    return $dep;
  }
  
  public function getCiviCRMEntityDataById($id) {
    $table = $this->config->getPaymentArrangementGroup('table_name');
    $pa_field = $this->config->getPaymentArrangementField('column_name');
    $details_field = $this->config->getPaymentArrangementDetailsField('column_name');
    
    $sql = "SELECT * FROM `".$table."` WHERE `id` = %1";
    $dao = CRM_Core_DAO::executeQuery($sql, array(1 => array($id, 'Integer')));
    $data = array();
    if ($dao->fetch()) {
      $data['contribution_id'] = $dao->entity_id;
      $data['id'] = $dao->id;
      $data['payment_arrangement'] = $dao->$pa_field;
      $data['payment_arrangement_details'] = $dao->$details_field;
      
      return $data;
    }
    
    throw new Exception('Could not find Payment arrangement data for syncing into Odoo');
  }
  
}

