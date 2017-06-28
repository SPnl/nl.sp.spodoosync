<?php

class CRM_Spodoosync_LocationTypeToOdooType {
  
  /**
   * Returns the Odoo type for a civicrm location type
   * 
   * Returns false if the location type is not a valid Odoo type
   * 
   * @param int $location_type_id
   * @return string|false
   */
  public static function getOdooType($location_type_id) {
    $odoo_civi_loc_type = self::getValidOdooTypes();
    
    $location_type = new CRM_Core_DAO_LocationType();
    $location_type->id = $location_type_id;
    if ($location_type->find(TRUE)) {
      if (isset($odoo_civi_loc_type[$location_type->name])) {
        return $odoo_civi_loc_type[$location_type->name];
      }
    }
    
    return false;
  }
  
  /**
   * Returns the name for the Odoo partner
   * 
   * Returns false if the location type is not a valid Odoo type
   * 
   * @param int $location_type_id
   * @return string|false
   */
  public static function getOdooDisplayName($location_type_id, $contact_id) {    
    $location_type = new CRM_Core_DAO_LocationType();
    $location_type->id = $location_type_id;
    if ($location_type->find(TRUE)) {
        return $location_type->display_name;
    }
    
    return false;
  }
  
  protected static function getValidOdooTypes() {
    return array(
      'Home' => 'default',
      'Billing' => 'invoice',
      'Other' => 'other',
    );
  }
  
  /**
   * Searches for an Odoo partner ID by address type and parent id.
   * 
   * Returns false if no odoo partner is found
   * 
   * @param type $type
   * @param type $parent_id
   */
  public static function getOdooIdByTypeAndParent($type, $parent_id) {
    $keys = array(
        new xmlrpcval(array(
          new xmlrpcval('type', 'string'),
          new xmlrpcval('=', 'string'),
          new xmlrpcval($type, 'string'),
        ), "array"),
        new xmlrpcval(array(
          new xmlrpcval('parent_id', 'string'),
          new xmlrpcval('=', 'string'),
          new xmlrpcval($parent_id, 'int'),
        ), "array"),
    );
    
    $connector = CRM_Odoosync_Connector::singleton();
    $ids = $connector->search('res.partner', $keys);
    foreach($ids as $id_element) {
      return $id_element->scalarval();
    }
    
    return false;
  }
}

