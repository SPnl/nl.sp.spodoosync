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
  $values = array();
  _civicrm_api3_custom_format_params($params, $values, 'Contribution');
  $params = array_merge($params, $values);

  //legacy soft credit handling - recommended approach is chaining
  if(!empty($params['soft_credit_to'])){
    $params['soft_credit'] = array(array(
      'contact_id' => $params['soft_credit_to'],
      'amount' => $params['total_amount']));
  }

  $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $params['id']));
  $params['financial_type_id'] = $contribution['financial_type_id'];

  return _civicrm_api3_basic_create('CRM_Contribute_BAO_Contribution', $params, 'Contribution');
}

