<?php

class CRM_Spodoosync_Synchronisator_ContactSynchronisator extends CRM_OdooContactSync_ContactSynchronisator {
  
  public function findOdooId(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $contact = $this->getContact($sync_entity->getEntityId());
    $odoo_id = $this->findPartnerByCiviCrmId($contact);
    
    if (!$odoo_id) {
      $odoo_id = $this->findPartnerByAwareId($contact);
    }
    
    if (!$odoo_id) {
      $odoo_id = $this->findByContactType($contact);
    }
    
    return $odoo_id;
  }
  
  protected function findPartnerByCiviCrmId($contact) {
    if (!empty($contact['id'])) {
      //find by field aware_id
      $key = array(
      new xmlrpcval(array(
        new xmlrpcval('civicrm_id', 'string'),
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
    $name = str_ireplace($search, $replace, $contact['display_name']);
    
    //find labels for sp staten fractie
    $label_ids = array();
    $parent_id = CRM_OdooContactSync_Helper_FindLabel::findLabel('SP-relaties');
    if ($parent_id) {
      $staten_label = CRM_OdooContactSync_Helper_FindLabel::findLabel('SP Statenfracties', $parent_id);
      if ($staten_label) {
        $label_ids[] = $staten_label;
      }
    }
    
    return $this->findByName($name, $label_ids);
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
    $name = str_ireplace($search, $replace, $contact['display_name']);
    
    //find labels for in opricht en actiefe afdeling
    $label_ids = array();
    $parent_id = CRM_OdooContactSync_Helper_FindLabel::findLabel('SP-relaties');
    if ($parent_id) {
      $afdeling_label = CRM_OdooContactSync_Helper_FindLabel::findLabel('SP afdelingen', $parent_id);
      if ($afdeling_label) {
        $label_ids[] = $afdeling_label;
        $actief = CRM_OdooContactSync_Helper_FindLabel::findLabel('SP afdelingen actief', $afdeling_label);
        if ($actief) {
          $label_ids[] = $actief;
        }
        $io = CRM_OdooContactSync_Helper_FindLabel::findLabel('SP afdelingen in oprichting', $afdeling_label);
        if ($io) {
          $label_ids[] = $io;
        }
      }
    }
    
    return $this->findByName($name, $label_ids);
  }
  
  protected function findByName($name, $label_ids = array()) {
    $key = array(
    new xmlrpcval(array(
      new xmlrpcval('name', 'string'),
      new xmlrpcval('like', 'string'),
      new xmlrpcval('%'.trim($name).'%', 'string'),
    ), "array"));
    
    if (is_array($label_ids) && count($label_ids)) {
      $label_ids_rpc = array();
      foreach($label_ids as $label_id) {
        if ($label_id) {
          $label_ids_rpc[] = new xmlrpcval($label_id, 'int');
        }
      }
      $key[] = new xmlrpcval(array(
        new xmlrpcval('category_id', 'string'),
        new xmlrpcval('in', 'string'),
        new xmlrpcval($label_ids_rpc, 'array'),
      ), "array");
    }

    $result = $this->connector->search($this->getOdooResourceType(), $key);

    foreach($result as $id_element) {
      return $id_element->scalarval();
    }
    
    return false;
  }
  
  /**
   * Returns the parameters to update/insert an Odoo object
   * 
   * @param type $contact
   * @return \xmlrpcval
   */
  protected function getOdooParameters($contact, $entity, $entity_id, $action) {
    $parameters = parent::getOdooParameters($contact, $entity, $entity_id, $action);
    $parameters['civicrm_id'] = new xmlrpcval($contact['id'], 'int');
    return $parameters;
  }
  
  
  
}

