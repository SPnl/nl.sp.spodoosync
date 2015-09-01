<?php

require_once 'spodoosync.civix.php';

/**
 * Implementation of hook_civicrm_odoo_object_definition
 * 
 */
function spodoosync_civicrm_odoo_object_definition(&$list) {
  if (spodoosync_paymentarrangement()) {
    $config = CRM_Paymentarrangement_Config::singleton();
    $table_name = $config->getPaymentArrangementGroup('table_name');
    $list[$table_name] = new CRM_PaymentArrangement_Definition();
  }
}

/** 
 * Implementation of hook_civicrm_custom
 * 
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_custom
 */
function spodoosync_civicrm_custom($op,$groupID, $entityID, &$params ) {
  if (spodoosync_paymentarrangement()) {  
    //check if the group if the Payment arrangement group
    $config = CRM_Paymentarrangement_Config::singleton();
    if ($groupID == $config->getPaymentArrangementGroup('id')) {
      //add the payment arrangement for syncing
      if ($op == 'delete') {
        //when deleting the params contains the id
        $objectId = $params;
      } else {
        //first find the id for this custom value pair
        $contributionParams = array();
        $contributionParams['id'] = $entityID;
        foreach($params as $param) {
          $contributionParams['custom_'.$param['custom_field_id']] = $param['value'];
          $contributionParams['return.custom_'.$param['custom_field_id']] = 1;
        }
        $contribution = civicrm_api3('Contribution', 'getsingle', $contributionParams);
        //extract the custom value table id
        $objectId = $contribution[$config->getPaymentArrangementGroup('table_name').'_id'];
      }

      $objects = CRM_Odoosync_Objectlist::singleton();
      $objects->post($op,$config->getPaymentArrangementGroup('table_name'), $objectId);
    }
    if ($groupID == $config->getContactPaymentArrangementGroup('id')) {
      $op = 'edit'; //if this custom field is deleted it doesn't mean that the contact is deleted.
      $objects = CRM_Odoosync_Objectlist::singleton();
      $objects->post($op,'civicrm_contact', $entityID);
    }
  }
}

/**
 * Implementation of hook_civicrm_odoo_alter
 * 
 * Through this hook we will make sure that every contact created in Odoo is a company (even if it is an individual)
 * 
 * @param xmlrpcval $parameters
 * @param type $entity
 * @param type $entity_id
 * @param type $action
 */
function spodoosync_civicrm_odoo_alter_parameters(&$parameters, $resource, $entity, $entity_id, $action) {
  if ($entity == 'civicrm_contribution') {
    $parameters['civicrm_id'] = new xmlrpcval($entity_id, 'int');
    $parameters['payment_term'] = new xmlrpcval(0, 'int');
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $entity_id));
    if (!empty($contribution['instrument_id'])) {
      $payment_instrument_to_payment_term = CRM_Spodoosync_PaymentInstrumentToOdooPaymentTerm::singleton();
      $payment_term = $payment_instrument_to_payment_term->getOdooPaymentTerm($contribution['instrument_id']);
      if (!empty($payment_term)) {
        $parameters['payment_term'] = new xmlrpcval($payment_term, 'int');
      }
    }
  }
  if ($entity == 'civicrm_contact') {
    //sync field retour post
    CRM_Spodoosync_RetourPost::syncRetourPostToOdoo($entity_id, $parameters);

    unset($parameters['title']);
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $entity_id));
    $parameters['civicrm_id'] = new xmlrpcval($contact['id'], 'int');
    if ($contact['contact_type'] == 'Individual') {
      $parameters['is_company'] = new xmlrpcval(true, 'boolean');
      $parameters['firstname'] = new xmlrpcval($contact['first_name'], 'string');
      $parameters['prename'] = new xmlrpcval($contact['middle_name'], 'string');
      $parameters['lastname'] = new xmlrpcval($contact['last_name'], 'string');
      
      $title = false;
      if (!empty($contact['individual_prefix'])) {
        $title = $contact['individual_prefix'];
      } elseif (!empty($contact['gender'])) {
        switch ($contact['gender']) {
          case 'Female':
            $title = 'Mevr.';
            break;
          case 'Male':
            $title = 'Dhr.';
            break;
        }
      }
      if ($title) {
        $title_id = CRM_Spodoosync_PrefixToTitle::getOdooId($title);
        if ($title_id) {
          $parameters['title'] = new xmlrpcval($title_id, 'int');
        }
      }
      if (!empty($contact['birth_date'])) {
        $birth_date = new DateTime($contact['birth_date']);
        $parameters['birthdate'] = new xmlrpcval($birth_date->format('d-m-Y') ,'string');
      }

      $initials = CRM_Spodoosync_Initials::getInitialsForContact($entity_id);
      $parameters['initials'] = new xmlrpcval($initials, 'string');
    }
    
    spodoosync_alter_parameters_contact_payment_arrangement($parameters, $entity_id);
  }
}

function spodoosync_alter_parameters_contact_payment_arrangement(&$parameters, $contact_id) {
  $config = CRM_Paymentarrangement_Config::singleton();
  $apiParameters['id'] = $contact_id;
  $apiParameters['return.custom_'.$config->getContactPaymentArrangementField('id')] = '1';
  $apiParameters['return.custom_'.$config->getContactPaymentArrangementDetailsField('id')] = '1';
  $contact = civicrm_api3('Contact', 'getsingle', $apiParameters);
  
  $payment_arrangement = $contact['custom_'.$config->getContactPaymentArrangementField('id')];
  $payment_arrangement_details = $contact['custom_'.$config->getContactPaymentArrangementDetailsField('id')];
  if ($payment_arrangement) {
    $parameters['payment_note'] = new xmlrpcval($payment_arrangement_details, 'string');
  } else {
    $parameters['payment_note'] = new xmlrpcval('', 'string');
  }
}

function spodoosync_civicrm_odoo_synchronisator(CRM_Odoosync_Model_ObjectDefinition $objectDefinition, &$synchronisator) {
  if ($objectDefinition instanceof CRM_OdooContactSync_AddressDefinition) {
    $synchronisator = 'CRM_Spodoosync_Synchronisator_AddressSynchronisator';
  }
  if ($objectDefinition instanceof CRM_OdooContactSync_ContactDefinition) {
    $synchronisator = 'CRM_Spodoosync_Synchronisator_ContactSynchronisator';
  }
}

function spodoosync_paymentarrangement() {
  if (class_exists('CRM_Paymentarrangement_Config')) {
    return true;
  }
  return false;
}

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function spodoosync_civicrm_config(&$config) {
  _spodoosync_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function spodoosync_civicrm_xmlMenu(&$files) {
  _spodoosync_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function spodoosync_civicrm_install() {
  $error = false;
  $isOdooSyncInstalled = false;
  try {
    $extensions = civicrm_api3('Extension', 'get');
    foreach ($extensions['values'] as $ext) {
      if ($ext['status'] == 'installed') {
        switch ($ext['key']) {
          case 'org.civicoop.odoosync':
            $isOdooSyncInstalled = true;
            break;
        }
      }
    }
  } catch (Exception $e) {
    $error = true;
  }


  if ($error || !$isOdooSyncInstalled) {
    throw new Exception('This extension requires org.civicoop.odoosync');
  }


  return _spodoosync_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function spodoosync_civicrm_uninstall() {
  return _spodoosync_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function spodoosync_civicrm_enable() {
  return _spodoosync_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function spodoosync_civicrm_disable() {
  return _spodoosync_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function spodoosync_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _spodoosync_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function spodoosync_civicrm_managed(&$entities) {
  return _spodoosync_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function spodoosync_civicrm_caseTypes(&$caseTypes) {
  _spodoosync_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function spodoosync_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _spodoosync_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
