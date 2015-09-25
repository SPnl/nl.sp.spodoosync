<?php

class CRM_Spodoosync_Membership_CheckMembership {

  public static function getMembershipPayment($contribution_id) {
    $sql = "SELECT m.* FROM civicrm_membership_payment mp inner join civicrm_membership m on mp.membership_id = m.id where mp.contribution_id = %1";
    $params[1] = array($contribution_id, 'Integer');
    $dao = CRM_Core_DAO::executeQuery($sql, $params, true, 'CRM_Member_BAO_Membership');
    if ($dao->fetch()) {
      $return = array();
      CRM_Core_DAO::storeValues($dao, $return);
      return $return;
    }
    return false;
  }

  public static function checkContributionForMembershipStatus($contribution) {
    $membership = self::getMembershipPayment($contribution['id']);
    if (!$membership) {
      return true;
    }

    if (self::checkMembershipContribution($contribution, $membership)) {
      return true;
    }
    return false;
  }

  /**
   * Returns true when membership contribution is syncable and false
   * wether we should wait for sync
   *
   * @param $contribution
   * @param $membership
   * @return bool
   */
  public static function checkMembershipContribution($contribution, $membership) {
    if (self::checkMembershipStatus($membership['status_id'])) {
      return true;
    }
    return false;
  }

  /**
   * Returns false when membership contributions should not be syned
   * based on their status
   *
   * In this particulair case it is when membership status is pending
   *
   * @param $membership_status_id
   * @return bool
   */
  public static function checkMembershipStatus($membership_status_id) {
    $status = CRM_Member_BAO_MembershipStatus::getMembershipStatus($membership_status_id);
    if ($status['name'] == 'Pending') {
      return false;
    }
    return true;
  }

}