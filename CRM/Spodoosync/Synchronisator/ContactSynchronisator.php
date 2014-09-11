<?php

class CRM_Spodoosync_Synchronisator_ContactSynchronisator extends CRM_OdooContactSync_ContactSynchronisator {
  
  public function findOdooId(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $contact = $this->getContact($sync_entity->getEntityId());
    $odoo_id = $this->findPartnerByAwareId($contact);
    
    if (!$odoo_id) {
      $odoo_id = $this->findByContactType($contact);
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
  
  protected function findByContactType($contact) {
    $odoo_id = false;
    if (!$odoo_id && in_array('SP_Provincie', $contact['contact_sub_type'])) {
      $odoo_id = $this->findProvincie($contact);
    }
    
    if (!$odoo_id && in_array('SP_Afdeling', $contact['contact_sub_type'])) {
      $odoo_id = $this->findAfdeling($contact);
    }
    
    return $odoo_id;
  }
  
  /**
   * Provincies zijn in Odoo herkeenbaar aan de naam die 
   * begint met SP Statenfractie en dan de provincie naam
   * 
   * Dus we moeten hier op zoeken willen we een provincie kunnen linken
   * 
   * @param type $contact
   * @return type
   */
  protected function findProvincie($contact) {
    $search = array('sp-provincie', 'sp provincie');
    $replace = array('');
    $name = 'SP Statenfractie '.trim(str_ireplace($search, $replace, $contact['display_name']));
    
    return $this->findByName($name);
  }
  
  /**
   * Afdelingen zijn in Odoo herkeenbaar aan de naam die 
   * begint met SP Statenfractie en dan de provincie naam
   * 
   * Dus we moeten hier op zoeken willen we een afdeling kunnen linken
   * 
   * @param type $contact
   * @return type
   */
  protected function findAfdeling($contact) {
    $search = array('sp-afdeling', 'sp afdeling', 'afdeling');
    $replace = array('');
    $name = 'SP Afd '.trim(str_ireplace($search, $replace, $contact['display_name']));
    
    return $this->findByName($name);
  }
  
  protected function findByName($name) {
    $key = array(
    new xmlrpcval(array(
      new xmlrpcval('name', 'string'),
      new xmlrpcval('like', 'string'),
      new xmlrpcval($name, 'string'),
    ), "array"));

    $result = $this->connector->search($this->getOdooResourceType(), $key);

    foreach($result as $id_element) {
      return $id_element->scalarval();
    }
    
    return false;
  }
  
}

