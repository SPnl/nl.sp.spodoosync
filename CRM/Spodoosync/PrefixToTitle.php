<?php

class CRM_Spodoosync_PrefixToTitle {
  
  /**
   * Returns the Odoo id for a title
   * 
   * Returns false if the title is not a valid title
   * 
   * @param int $location_type_id
   * @return string|false
   */
  public static function getOdooId($title) {
    $keys = array(
        new xmlrpcval(array(
          new xmlrpcval('title', 'string'),
          new xmlrpcval('=', 'string'),
          new xmlrpcval($title, 'string'),
        ), "array"),
        new xmlrpcval(array(
          new xmlrpcval('domain', 'string'),
          new xmlrpcval('=', 'string'),
          new xmlrpcval('Contactpersoon', 'string'),
        ), "array"),
    );
    
    $connector = CRM_Odoosync_Connector::singleton();
    $ids = $connector->search('res.partner.title', $keys);
    foreach($ids as $id_element) {
      return $id_element->scalarval();
    }
    
    //try to create a title
    $parameters['title'] = new xmlrpcval($title, 'string');
    $parameters['domain'] = new xmlrpcval('Contactpersoon', 'string');
    $odoo_id = $connector->create('res.partner.title', $parameters);
    if ($odoo_id) {
      return $odoo_id;
    }
    
    return false;
  }
  
}

