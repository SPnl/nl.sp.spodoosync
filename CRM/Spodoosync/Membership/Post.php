<?php

class CRM_Spodoosync_Membership_Post {

  public static function post($op, $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Membership') {
      return;
    }
    if ($op != 'edit') {
      return;
    }

    $sql = "SELECT c.* FROM civicrm_contribution c inner join civicrm_membership_payment mp on c.id = mp.contribution_id where mp.membership_id = %1";
    $params[1] = array($objectId, 'Integer');
    $contributions = CRM_Core_DAO::executeQuery($sql, $params, true, 'CRM_Contribute_BAO_Contribution');
    $odooSync = CRM_Odoosync_Objectlist::singleton();
    while($contributions->fetch()) {
      $odooSync->restoreSyncItem('Contribution', $contributions->id);
    }
  }

}