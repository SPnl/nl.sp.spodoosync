<?php

/**
 * Fix for SP issue #1641.
 * 
 * CiviCRM creates a billing address after an online payment for an event.
 * This billing address has the location type billing and the is_billing flag set.
 * The address itself is empty and holds no data.
 * 
 * The code where the billing address is set is in:
 *   CRM\Event\Form\Registranion\Confirm.php:515 
 *   CRM\Event\Form\Registranion\Confirm.php:1129
 * 
 * And on line CRM\Event\Form\Registranion\Confirm.php:529 the billing information is removed 
 *   when the event is pay later or a non-paid event. 
 * 
 * In the conext of the SP this means we loose the information in Odoo. As the address
 * with the is_billing flag is synchronised to Odoo.
 * 
 */
class CRM_Spodoosync_FixEmptyInvoiceAddress {
  
  public static function pre($op,$objectName, $objectId, &$params) {
    if ($objectName != 'Profile') {
      return;
    }
    if ($op != 'create' && $op != 'edit') {
      return;
    }
    
    // Check whether billing fields are present in the set.
    // If so we dont need to remove the address-name-{billingId} parameter.
    // 
    // Billing fields appear in the profile as billing_first_name, billing_....
    if (!self::billingFieldsPresent($params)) {
      $billingLocationTypeId = CRM_Core_BAO_LocationType::getBilling();
      unset($params["address_name-{$billingLocationTypeId}"]);
    }
  }
  
  private static function billingFieldsPresent($params) {
    foreach($params as $key => $value) {
      if (stripos($key, 'billing_')===0) {
        return true;
      }
    }
    return false;
  }
  
}
