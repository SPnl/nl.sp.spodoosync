<?php

class CRM_Spodoosync_Synchronisator_ContactSynchronisator extends CRM_OdooContactSync_ContactSynchronisator {
  
  public function findOdooId(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $contact = $this->getContact($sync_entity->getEntityId());
    $odoo_id = $this->findPartnerByAwareId($contact);
    if ($odoo_id) {
      return $odoo_id;
    }
    return $odoo_id;
  }
  
  protected function findPartnerByAwareId($contact) {
    if (!empty($contact['id'])) {
      //find by field aware_id
      $key = array(
      new xmlrpcval(array(
        new xmlrpcval('aware_id', 'string'),
        new xmlrpcval('=', 'string'),
        new xmlrpcval($contact['id'], 'int'),
      ), "array"));
      
      $result = $this->connector->search($this->getOdooResourceType(), $key);
      
      foreach($result as $id_element) {
        return $id_element->scalarval();
      }
    }
    return false;
  }
  
}

