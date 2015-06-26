<?php

class CRM_PaymentArrangement_Synchronisator extends CRM_Odoosync_Model_ObjectSynchronisator {
  
  /**
   *
   * @var CRM_Ibanaccounts_Config
   */
  protected $config;
  
  public function __construct(CRM_Odoosync_Model_ObjectDefinitionInterface $objectDefinition) {
    $this->config = CRM_Paymentarrangement_Config::singleton();
    parent::__construct($objectDefinition);
  }
  
  /**
   * Retruns wether this item is syncable
   * By default false. 
   * 
   * subclasses should implement this function to make items syncable
   */
  public function isThisItemSyncable(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $data = $this->getData($sync_entity->getEntityId());
    $odoo_invoice_id = $sync_entity->findOdooIdByEntity('civicrm_contribution', $data['contribution_id']);
    CRM_Core_Error::debug_log_message('Is payment arrangement syncable: '.var_export($odoo_invoice_id, true));
    if ($odoo_invoice_id > 0) {
      return true;
    }
    return false;
  }
  
  public function save(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $data = $this->getData($sync_entity->getEntityId());
    $odoo_invoice_id = $sync_entity->findOdooIdByEntity('civicrm_contribution', $data['contribution_id']);
    $parameters = $this->getOdooParameters($data, $odoo_invoice_id, $sync_entity->getEntity(), $sync_entity->getEntityId(), 'create');
    $odoo_id = $this->connector->write($this->getOdooResourceType(), $odoo_invoice_id, $parameters);
    if ($odoo_id) {
      return $odoo_invoice_id;
    }
    CRM_Core_Error::debug_log_message('Save payment arrangment: '.var_export($parameters, true));
    throw new Exception('Could not update payment arrangement into Odoo');
  }
  
  /**
   * Insert a civicrm entity into Odoo
   * 
   */
  public function performInsert(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    return $this->save($sync_entity);
  }
  
  public function getSyncData(\CRM_Odoosync_Model_OdooEntity $sync_entity, $odoo_id) {
    $data = $this->getData($sync_entity->getEntityId());
    $odoo_invoice_id = $sync_entity->findOdooIdByEntity('civicrm_contribution', $data['contribution_id']);
    $parameters = $this->getOdooParameters($data, $odoo_invoice_id, $sync_entity->getEntity(), $sync_entity->getEntityId(), 'create');
    return $parameters;
  }
  
  /**
   * Update an Odoo resource with civicrm data
   * 
   */
  public function performUpdate($odoo_id, CRM_Odoosync_Model_OdooEntity $sync_entity) {
    return $this->save($sync_entity);
  }
  
  /**
   * Delete an item from Odoo
   * 
   */
  function performDelete($odoo_id, CRM_Odoosync_Model_OdooEntity $sync_entity) {
    return $this->save($sync_entity);
  }
  
  /**
   * Find item in Odoo and return odoo_id
   * 
   */
  public function findOdooId(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    return false;
  }
  
  /**
   * Checks if an entity still exists in CiviCRM.
   * 
   * This is used to check wether a civicrm entity is soft deleted or hard deleted. 
   * In the first case we have to update the entity in odoo 
   * In the second case we have to delete the entity from odoo 
   */
  public function existsInCivi(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $table = $this->config->getPaymentArrangementGroup('table_name');
    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM `".$table."` WHERE `id` = %1", array(
      1 => array($sync_entity->getEntityId(), 'Integer'),
    ));
    
    if ($dao->fetch()) {
      return true;
    }
    return false;
  }
  
  /**
   * Returns the name of the Odoo resource e.g. res.partner
   * 
   * @return string
   */
  public function getOdooResourceType() {
    return 'account.invoice';
  }
  
  protected function getData($entity_id) {
    $table = $this->config->getPaymentArrangementGroup('table_name');
    $sql = "SELECT * FROM `".$table."` WHERE `id` = %1";
    $params[1] = array($entity_id, 'Integer');
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $data = array();
    if ($dao->fetch()) {
      $pa_field = $this->config->getPaymentArrangementField('column_name');
      $details_field = $this->config->getPaymentArrangementDetailsField('column_name');
      $data['contribution_id'] = $dao->entity_id;
      $data['id'] = $dao->id;
      $data['payment_arrangement'] = $dao->$pa_field;
      $data['payment_arrangement_details'] = $dao->$details_field;
    }
    return $data;
  }
  
  /**
   * Returns the parameters to update/insert an Odoo object
   * 
   * @param type $contact
   * @return \xmlrpcval
   */
  protected function getOdooParameters($data, $odoo_partner_id, $entity, $entity_id, $action) {
    $comment = '';
    if ($data['payment_arrangement'] == '1') {
      $comment = $data['payment_arrangement_details'];
    } 
    
    $parameters = array(
      'comment' => new xmlrpcval($comment, 'string'),
    );
    
    $this->alterOdooParameters($parameters, $this->getOdooResourceType(), $entity, $entity_id, $action);
    
    return $parameters;
  }
  
}
