<?php

/**
 * OdooInvoice.Updatecontribution API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_odoo_invoice_updatecontribution_spec(&$spec) {
  $spec['id']['api.required'] = 1;
  $spec['invoice_id']['api.required'] = 1;
  $spec['contribution_status_id']['api.required'] = 1;
}

/**
 * OdooInvoice.Updatecontribution API
 *
 * This is a slightly modified version of the normal contribution create api.
 * The modification is that this api does not check whether a status could be changed.
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 */
function civicrm_api3_odoo_invoice_updatecontribution($params) {
  $status = CRM_Contribute_PseudoConstant::contributionStatus();
  if (!isset($status[$params['contribution_status_id']])) {
    return civicrm_api3_create_error('Invalid contribution status');
  }

  // Update the status and invoice_id directly in the database to prevent a resync of the contribution to odoo.
  $sql = "UPDATE civicrm_contribution SET invoice_id = %1, contribution_status_id = %2 WHERE id = %3";
  $sqlParams[1] = array($params['invoice_id'], 'String');
  $sqlParams[2] = array($params['contribution_status_id'], 'Integer');
  $sqlParams[3] = array($params['id'], 'Integer');
  CRM_Core_DAO::executeQuery($sql, $sqlParams);

  return civicrm_api3('Contribution', 'getsingle', array('id' => $params['id']));
}

