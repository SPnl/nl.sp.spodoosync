<?php

class CRM_Spodoosync_Initials {

  private static $customFieldId;

  private static function getCustomFieldId() {
    if (!isset(self::$customFieldId)) {
      $cfsp = CRM_Spgeneric_CustomField::singleton();
      self::$customFieldId = $cfsp->getFieldId('Migratie_Contacten', 'Voorletters');
    }
    return self::$customFieldId;
  }

  public static function getInitialsForContact($contact_id) {
    $initials = civicrm_api3('Contact', 'getvalue', array('id' => $contact_id, 'return' => 'custom_'.self::getCustomFieldId()));
    return $initials;
  }

}