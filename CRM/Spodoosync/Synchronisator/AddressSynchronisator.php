<?php

class CRM_Spodoosync_Synchronisator_AddressSynchronisator extends CRM_OdooContactSync_AddressSynchronisator {
  
  public function isThisItemSyncable(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $address = $this->getAddress($sync_entity->getEntityId());
    
    if (empty($address['contact_id'])) {
      return false;
    }
    $odoo_partner_id = $sync_entity->findOdooIdByEntity('civicrm_contact', $address['contact_id']);
    if ($odoo_partner_id <= 0) {
      return false;
    }

    // Only sync invoice address
    $type = CRM_Spodoosync_LocationTypeToOdooType::getOdooType($address['location_type_id']);
    if ($type !== false) {
      return true;
    }
    
    //adress is not syncable, clear address of partner if item is already synced intoo Odoo    
    $this->clearAddressInOdoo($sync_entity, $address);
    
    return false;
  }
  
    /**
   * Insert a new Contact into Odoo
   * 
   * @param CRM_Odoosync_Model_OdooEntity $sync_entity
   * @return type
   * @throws Exception
   */
  public function performInsert(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    //an insert is impossible because we only insert valid address types and we 
    //update primary addresses at partner level
    //and store them at the partner entity in Odoo
    //throw new Exception('It is imposible to insert an address into Odoo');
    return -1; //a -1 ID means that the entity does not exist in Odoo
  }
  
  /**
   * Update an existing contact in Odoo
   * 
   * @param int $orig_odoo_id
   * @param CRM_Odoosync_Model_OdooEntity $sync_entity
   * @return int
   * @throws Exception
   */
  public function performUpdate($orig_odoo_id, CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $odoo_id = $this->findOdooId($sync_entity);
    if (!$odoo_id || !$this->existsInOdoo($odoo_id)) {
      return $this->performInsert($sync_entity);
    }
    
    $address = $this->getAddress($sync_entity->getEntityId());
    $parameters = $this->getOdooParameters($address, $sync_entity->getEntity(), $sync_entity->getEntityId(), 'write');
    if ($this->connector->write($this->getOdooResourceType(), $odoo_id, $parameters)) {
      return $odoo_id;
    }
    throw new Exception("Could not update partner in Odoo");
  }
  
  /**
   * Clear an address in Odoo (uses parent method)
   * But set the odoo_field to empty
   * 
   * @param CRM_Odoosync_Model_OdooEntity $sync_entity
   * @param type $address
   */
  protected function clearAddressInOdoo(CRM_Odoosync_Model_OdooEntity $sync_entity, $address) {
    $parent_id = false;
    if ($address['contact_id']) {
      $parent_id = $sync_entity->findOdooIdByEntity('civicrm_contact', $address['contact_id']);
    }
    $is_synced = $this->isThisFieldSynced($sync_entity, $sync_entity->getOdooId(), $sync_entity->getOdooField());
    if (!$is_synced && strlen($sync_entity->getOdooField()) && $sync_entity->getOdooId() && $sync_entity->getOdooId() != $parent_id) {
      //remove address/partner if it is not the main contact
      $this->connector->unlink($this->getOdooResourceType(), $sync_entity->getOdooId());
    }
    //set odoo field to empty because this item is not syncable due to an invalid odoo type
    //we use the odoo_field to store the current address type
    $sync_entity->setOdooField('');
  }

  /**
   * Delete contact from Odoo
   *
   * @param type $odoo_id
   * @param CRM_Odoosync_Model_OdooEntity $sync_entity
   * @throws Exception
   */
  public function performDelete($odoo_id, CRM_Odoosync_Model_OdooEntity $sync_entity) {
    // Check whether the contact has an invoice address and whether the invoice address differs from the address we
    // want to delete. Only clear address when an contact does not have an invoice address.
    $address = array();
    if (!empty($sync_entity->getOdooId()) && $sync_entity->getOdooId() > 0) {
      $odoo_id = false;
      $contact_id = false;
      // Fin the contact ID
      $civicrm_odoo_entities = $sync_entity->findByOdooIdAndField('res.partner', $sync_entity->getOdooId(), '');
      foreach ($civicrm_odoo_entities as $civicrm_odoo_entity) {
        if ($civicrm_odoo_entity['entity'] == 'civicrm_contact') {
          $contact_id = $civicrm_odoo_entity['entity_id'];
          $odoo_id = $civicrm_odoo_entity['odoo_id'];
        }
      }

      if (!$contact_id || !$odoo_id) {
        return;
      }

      $invoiceAddressLocationTtypeId = civicrm_api3('LocationType', 'getvalue', array('name' => 'Billing', 'return' => 'id'));
      try {
        $invoice_address = civicrm_api3('Address', 'getsingle', array('contact_id' => $contact_id, 'location_type_id' => $invoiceAddressLocationTtypeId));
      } catch(Exception $e) {
        // No invoice address found. So we could safely empty the current address in Odoo.
        $parameters = $this->getOdooParameters($address, $sync_entity->getEntity(), $sync_entity->getEntityId(), 'clear');
        if (!$this->connector->write($this->getOdooResourceType(), $odoo_id, $parameters)) {
          throw new Exception('Could not clear address in Odoo');
        }
      }
    }

  }
  
  /**
   * If the address is a primary address retrieve the odoo of the contact
   * 
   * In odoo we store the primary address at partner level because there is no such thing as an address entity in Odoo
   * 
   * @param CRM_Odoosync_Model_OdooEntity $sync_entity
   * @return boolean
   */
  public function findOdooId(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $address = $this->getAddress($sync_entity->getEntityId());
    $contact_id = $address['contact_id'];
    $odoo_id = $sync_entity->findOdooIdByEntity('civicrm_contact', $contact_id);
    if ($odoo_id != $sync_entity->getOdooId()) {
      $this->clearAddressInOdoo($sync_entity, $address); 
    }      
    return $odoo_id;
  }
  
  protected function getOdooParameters($address, $entity, $entity_id, $action) {
    $parameters = parent::getOdooParameters($address, $entity, $entity_id, $action);
    return $parameters;
  }
  
  /**
   * Returns wether this field is already in sync in Odoo
   * 
   * This is useful for checking wether we should empty an address because it is possible to
   * empty the address while another address is already synced for this type
   * 
   * @param CRM_Odoosync_Model_OdooEntity $sync_entity
   * @param type $odoo_id
   * @param type $odoo_field
   * @return boolean
   */
  protected function isThisFieldSynced(CRM_Odoosync_Model_OdooEntity $sync_entity, $odoo_id, $odoo_field) {
    if (!$odoo_id) {
      return false;
    }
    
    $rows = $sync_entity->findByOdooIdAndField($this->getOdooResourceType(), $odoo_id, $odoo_field);
    $synced = false;
    foreach($rows as $row) {
      if ($row['id'] == $sync_entity->getId()) {
        continue; //do not check current record
      }
      if ($row['status'] == 'SYNCED') {
        $synced = true;
        break; //stop we know now that this field is alreay in sync
      }
    }
    return $synced;
  }
    
}

