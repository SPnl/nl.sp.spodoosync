<?php

require_once 'spodoosync.civix.php';

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
  if ($entity == 'civicrm_contact') {
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $entity_id));
    if ($contact['contact_type'] == 'Individual') {
      $parameters['is_company'] = new xmlrpcval(true, 'boolean');
      $parameters['fristname'] = new xmlrpcval($contact['first_name'], 'string');
      $parameters['prename'] = new xmlrpcval($contact['middle_name'], 'string');
      $parameters['lastname'] = new xmlrpcval($contact['last_name'], 'string');
      if (!empty($contact['individual_prefix'])) {
        $parameters['title'] = new xmlrpcval($contact['individual_prefix'], 'string');
      }
      if (!empty($contact['birth_date'])) {
        $birth_date = new DateTime($contact['birth_date']);
        $parameters['birthdate'] = new xmlrpcval($birth_date->format('Y-m-d') ,'string');
      }
    }
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
