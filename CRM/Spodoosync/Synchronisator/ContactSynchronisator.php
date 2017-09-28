<?php

class CRM_Spodoosync_Synchronisator_ContactSynchronisator extends CRM_OdooContactSync_ContactSynchronisator {

  private static $tagIdsToLabels = false;

  public function isThisItemSyncable(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    // Check whether Contact in Odoo is set to yes.
    if (!$this->checkContactInOdoo($sync_entity->getEntityId())) {
      return false;
    }

    if (!parent::isThisItemSyncable($sync_entity)) {
      $this->updateContactInOdoo($sync_entity->getEntityId(), false, ts('Contact is not syncable'));
      return false;
    }

    $this->updateContactInOdoo($sync_entity->getEntityId(), true, '');

    return true;
  }
	
	protected function getOdooParameters($contact, $entity, $entity_id, $action) {
		$parameters = parent::getOdooParameters($contact, $entity, $entity_id, $action);
		$parameters['category_id'] = new xmlrpcval($this->contactLabels($contact), 'array');
		return $parameters;
	}

  /**
   * Returns whether the field Contact in Odoo is set to yes.
   *
   * @param $contact_id
   * @return bool
   */
  protected function checkContactInOdoo($contact_id) {
    $sql = "SELECT `in_odoo` FROM `civicrm_value_odoo_contact` WHERE `entity_id` = %1";
    $params[1] = array($contact_id, 'Integer');
    $contact_in_odoo = CRM_Core_DAO::singleValueQuery($sql, $params);
    return $contact_in_odoo ? true : false;
  }

  /**
   * Update the Contact in Odoo field.
   *
   * @param $contact_id
   * @param $in_odoo
   * @param null $status
   */
  protected function updateContactInOdoo($contact_id, $in_odoo, $status=null) {
    $sql = "SELECT `id` FROM `civicrm_value_odoo_contact` WHERE `entity_id` = %1";
    $params[1] = array($contact_id, 'Integer');
    $id = CRM_Core_DAO::singleValueQuery($sql, $params);
    if ($id) {
      $update_params[1] = array($id, 'Integer');
      $update_params[2] = array($in_odoo ? 1 : 0, 'Integer');
      $update_params[3] = array($status ? $status : '', 'String');
      $sql = "UPDATE `civicrm_value_odoo_contact` SET `in_odoo` = %2, `status` = %3 WHERE `id` = %1";
      CRM_Core_DAO::executeQuery($sql, $update_params);
    } else {
      $insert_params[1] = array($contact_id, 'Integer');
      $insert_params[2] = array($in_odoo ? '1' : '0', 'Integer');
      $insert_params[3] = array($status ? $status : '', 'String');
      $sql = "INSERT INTO `civicrm_value_odoo_contact` (`entity_id`, `in_odoo`, `status`) VALUES (%1, %2, %3)";
      CRM_Core_DAO::executeQuery($sql, $insert_params);
    }
  }

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
    if (!$odoo_id && is_array($contact['contact_sub_type']) && in_array('SP_Provincie', $contact['contact_sub_type'])) {
      $odoo_id = $this->findProvincie($contact);
    }
    
    if (!$odoo_id && is_array($contact['contact_sub_type']) && in_array('SP_Afdeling', $contact['contact_sub_type'])) {
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
   * Find a label in Odoo if it doesnot exists try to create it
   *
   * @param $label
   * @return bool|int
   */
  protected static function findAndCreateLabel($label) {
    $connector = CRM_Odoosync_Connector::singleton();
    $label_id = CRM_OdooContactSync_Helper_FindLabel::findLabel($label);
    if (!$label_id) {
      $parameters['name'] = new xmlrpcval($label);
      $connector->create('res.partner.category', $parameters);
      $label_id = CRM_OdooContactSync_Helper_FindLabel::findLabel($label);
    }
    return $label_id;
  }

  /**
   * Returns what the label in Odoo should be for the given tag
   *
   * @return array
   */
  private static function tagsToLabels() {
    if (!self::$tagIdsToLabels) {
      self::$tagIdsToLabels = array();
      $tagsToLabels = array(
        'jaap' => 'jaap'
      );
      foreach ($tagsToLabels as $tag => $label) {
        $tag_id = civicrm_api3('Tag', 'getvalue', array('name' => $tag, 'return' => 'id'));
        $label_id = self::findAndCreateLabel($label);
        if ($label_id) {
          self::$tagIdsToLabels[$tag_id] = $label_id;
        }
      }
    }
    return self::$tagIdsToLabels;
  }

  /**
   * Create an array with category_ids for the partner.
   *
   * @param $contact
   * @param $current_labels
   * @return array
   */
  private function contactLabels($contact) {
    $tagsToLabels = self::tagsToLabels();
    $current_tags = civicrm_api3('EntityTag', 'get', array('entity_table' => 'civicrm_contact', 'entity_id' => $contact['id'], 'options' => array('limit' => 0)));
    $labels = array();
    foreach($current_tags['values'] as $current_tag) {
      if (isset($tagsToLabels[$current_tag['tag_id']])) {
        $labels[] = new xmlrpcval(array(
            new xmlrpcval(4, "int"),// 4 : add link
            new xmlrpcval($tagsToLabels[$current_tag['tag_id']],"int")
            ),
        "array" ); 
				unset($tagsToLabels[$current_tag['tag_id']]);
      }
    }
		foreach($tagsToLabels as $label_id) {
			$labels[] = new xmlrpcval(array(
            new xmlrpcval(3, "int"),// 3 : remove link
            new xmlrpcval($label_id,"int")
            ),
        "array" ); 
		}
    return $labels;
  }

  
  
  
}

