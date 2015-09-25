<?php

class CRM_Spodoosync_Synchronisator_ContributionSynchronisator extends CRM_OdooContributionSync_ContributionSynchronisator {

  public function isThisItemSyncable(CRM_Odoosync_Model_OdooEntity $sync_entity) {

    //to test we return false so no contributions are synced to Odoo
    //return false;

    $return = parent::isThisItemSyncable($sync_entity);
    $doDelete = false;

    //do not sync contributions with a date before 31 december 2014
    if ($return) {
      $contribution = $this->getContribution($sync_entity->getEntityId());
      $receive_date = new DateTime($contribution['receive_date']);
      if (!($receive_date->format('Y') >= 2015)) {
        $return = FALSE;
        $doDelete = TRUE;
      } elseif ($this->checkEventContribution($contribution)) {
        $return = false;
        $doDelete = true;
      } elseif (!CRM_Spodoosync_Membership_CheckMembership::checkContributionForMembershipStatus($contribution)) {
        $return = false;
        $doDelete = false;
      }
    }
    if (!$return && $doDelete && $sync_entity->getOdooId()) {
      $this->performDelete($sync_entity->getOdooId(), $sync_entity);
    }
    return $return;
  }

  public function performInsert(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $contribution = $this->getContribution($sync_entity->getEntityId());
    $receive_date = new DateTime($contribution['receive_date']);
    if ($receive_date->format('Y') < 2015) {
      throw new Exception('Do not sync invoices before 2015');
    }
    return parent::performInsert($sync_entity);
  }

  public function performUpdate($odoo_id, CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $contribution = $this->getContribution($sync_entity->getEntityId());
    $receive_date = new DateTime($contribution['receive_date']);
    if ($receive_date->format('Y') < 2015) {
      $this->performDelete($odoo_id, $sync_entity);
      throw new Exception('Do not sync invoices before 2015');
    }
    return parent::performUpdate($odoo_id, $sync_entity);
  }

  protected function checkEventContribution($contribution) {
    $sql = "SELECT COUNT(*) as total FROM `civicrm_participant_payment` WHERE contribution_id = %1";
    $params[1] = array($contribution['id'], 'Integer');
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    if ($dao->fetch()) {
      if ($dao->total > 0) {
        return true;
      }
    }
    return false;
  }

}