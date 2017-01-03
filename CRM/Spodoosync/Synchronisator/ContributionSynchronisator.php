<?php

class CRM_Spodoosync_Synchronisator_ContributionSynchronisator extends CRM_OdooContributionSync_ContributionSynchronisator {

  private $openBookYears = array();

  /**
   * Returns true if the book year exist in odoo and wether the book year is open and
   * available for bookings in the SP context
   *
   * @param int $year
   * @return int|false
   */
  protected function isBookYearAvailableForSP($year) {
    //check if currency id exist in cache
    //if not try to retrieve it from Odoo
    if (!isset($this->openBookYears[$year])) {
      $keys = array(
        new xmlrpcval(array(
          new xmlrpcval('name', 'string'),
          new xmlrpcval('=', 'string'),
          new xmlrpcval($year, 'string'),
        ), "array"),
        new xmlrpcval(array(
          new xmlrpcval('civicrm_block_fiscal_year', 'string'),
          new xmlrpcval('=', 'string'),
          new xmlrpcval(false, 'boolean'),
        ), "array"),
      );

      $ids = $this->connector->search('account.fiscalyear', $keys);
      foreach ($ids as $id_element) {
        $this->openBookYears[$year] = true;
        break;
      }
      if (!isset($this->openBookYears[$year])) {
        $this->openBookYears[$year] = false;
      }
    }

    return $this->openBookYears[$year];
  }

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
      } elseif ($contribution['contribution_status_id'] == CRM_Core_OptionGroup::getValue('contribution_status', 'Refunded', 'name')) {
        $return = FALSE;
        $doDelete = TRUE;
      } elseif ($this->checkEventContribution($contribution)) {
        $return = false;
        $doDelete = true;
      } elseif (!CRM_Spodoosync_Membership_CheckMembership::checkContributionForMembershipStatus($contribution)) {
        $return = false;
        $doDelete = false;
      } elseif (!$this->isBookYearAvailableForSP($receive_date->format('Y'))) {
        $return = FALSE;
        $doDelete = FALSE;
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
    if (!$this->checkDirectDebitAndLinkedMandate($contribution)) {
      throw new Exception('No mandate linked');
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
    if (!$this->checkDirectDebitAndLinkedMandate($contribution)) {
      throw new Exception('No mandate linked');
    }
    return parent::performUpdate($odoo_id, $sync_entity);
  }

  protected function checkDirectDebitAndLinkedMandate($contribution) {
    $config = CRM_Spodoosync_Config::singleton();
    if (!empty($contribution['instrument_id']) && $contribution['instrument_id'] == $config->direct_debit_payment_instrument_id) {
      // Check whether the mandate field is set
      if (!sepamandaat_get_odoo_id_for_contribution_id($contribution['id'])) {
        return false;
      }
    }
    return true;
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