<?php

class CRM_Spodoosync_Initials {

  private static $customFieldId;

  private static function getCustomFieldId() {
    if (!isset(self::$customFieldId)) {
      $customGroupId = civicrm_api3('CustomGroup', 'getvalue', array('return' => 'id', 'name' => 'Migratie_Contacten'));
      self::$customFieldId = civicrm_api3('CustomField', 'getvalue', array('return' => 'id', 'name' => 'Voorletters', 'custom_group_id' => $customGroupId));
    }
    return self::$customFieldId;
  }

  public static function getInitialsForContact($contact_id) {
    $initials = civicrm_api3('Contact', 'getvalue', array('id' => $contact_id, 'return' => 'custom_'.self::getCustomFieldId()));
    return $initials;
  }

}