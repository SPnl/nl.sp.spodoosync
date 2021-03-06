<?php

require_once 'spodoosync.civix.php';

/** 
 * Implementation of hook_civicrm_pre
 * 
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_post
 */
function spodoosync_civicrm_pre($op,$objectName, $objectId, &$params) {
  // Fix for SP issue #1641:
  CRM_Spodoosync_FixEmptyInvoiceAddress::pre($op, $objectName, $objectId, $params);
}

/** 
 * Implementation of hook_civicrm_post
 * 
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_post
 */
function spodoosync_civicrm_post($op,$objectName, $objectId, &$objectRef) {
	if ($objectName == 'EntityTag' && $objectRef[1] == 'civicrm_contact') {
		$tags = CRM_Spodoosync_Synchronisator_ContactSynchronisator::synchronisableTags();
		if (isset($tags[$objectId])) {
			foreach($objectRef[0] as $contact_id) {
				$objects = CRM_Odoosync_Objectlist::singleton();
  			$objects->post('create','civicrm_contact', $contact_id);
			}
		}
	}
}

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
  $communicatie_group_id = civicrm_api3('CustomGroup', 'getvalue', array('return' => 'id', 'name' => 'communicatie'));
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
  if ($groupID == $communicatie_group_id) {
    $op = 'edit'; //if this custom field is deleted it doesn't mean that the contact is deleted.
    $objects = CRM_Odoosync_Objectlist::singleton();
    $objects->post($op,'civicrm_contact', $entityID);
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
  if ($entity == 'civicrm_contribution' && $action != 'credit') {
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
  } elseif ($entity == 'civicrm_contribution' && $action == 'credit') {
    $parameters['payment_term'] = new xmlrpcval(18, 'int'); //verrekenen
    $parameters['civicrm_id'] = new xmlrpcval($entity_id, 'int');
    if ($entity_id) {
      $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $entity_id));
      $refund_option_value = CRM_Core_OptionGroup::getValue('contribution_status', 'Refunded', 'name');
      if ($contribution['contribution_status_id'] == $refund_option_value) {
        $parameters['payment_term'] = new xmlrpcval(17, 'int'); //terugbetalen
      }
    }
  }
  if ($entity == 'civicrm_contact') {
    // Sync field geen_post and reden_geen_post
    $geenpost = false;
    $geenpost_reden = '';
    $geenpost_dao = CRM_Core_DAO::executeQuery("SELECT geen_post, reden_geen_post FROM civicrm_value_communicatie WHERE entity_id = %1", array(1=>array($entity_id, 'Integer')));
    if ($geenpost_dao->fetch()) {
      if ($geenpost_dao->geen_post == CRM_Core_DAO::VALUE_SEPARATOR.'1'.CRM_Core_DAO::VALUE_SEPARATOR) {
        $geenpost = true;
      }
      if ($geenpost_dao->reden_geen_post) {
        $geenpost_reden = CRM_Core_DAO::singleValueQuery("
          SELECT label FROM civicrm_option_value 
          INNER JOIN civicrm_option_group ON civicrm_option_value.option_group_id = civicrm_option_group.id 
          WHERE civicrm_option_group.name = 'reden_geen_post' AND civicrm_option_value.value =%1",
          array(1=>array($geenpost_dao->reden_geen_post, 'String'))
        );
      }
    }
    $parameters['geenpost'] = new xmlrpcval($geenpost, 'boolean');
    $parameters['geenpost_reden'] = new xmlrpcval($geenpost_reden, 'string');

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
  if ($objectDefinition instanceof CRM_OdooContributionSync_ContributionDefinition) {
    $synchronisator = 'CRM_Spodoosync_Synchronisator_ContributionSynchronisator';
  }
}

function spodoosync_civicrm_odoo_object_definition_dependency(&$deps, CRM_Odoosync_Model_ObjectDefinition $def, $entity_id, $action, $data=false) {
  if ($def instanceof CRM_OdooContributionSync_ContributionDefinition) {
    if (is_array($data) && isset($data['contact_id'])) {
      $contact_id = $data['contact_id'];
    } else {
      try {
        $contact_id = civicrm_api3('Contribution', 'getvalue', array('return' => 'contact_id', 'id' => $entity_id));
      } catch (Exception $e) {
        return;
      }
    }

    _spodoosync_get_odoo_contribution_dependencies($deps, $entity_id);
  }
}

/**
 * Set dependencies for a contribution object for syncing with Odoo
 *
 * @param $deps
 * @param $contribution_id
 */
function _spodoosync_get_odoo_contribution_dependencies(&$deps, $contribution_id) {
  if (spodoosync_paymentarrangement()) {
    $config = CRM_Paymentarrangement_Config::singleton();
    $sql = "SELECT `id` FROM `".$config->getPaymentArrangementGroup('table_name')."` WHERE `entity_id` = %1";
    $dao = CRM_Core_DAO::executeQuery($sql, array(1 => array($contribution_id, 'Integer')));
    if ($dao->fetch() && $dao->id) {
      $deps[] = new CRM_Odoosync_Model_Dependency($config->getPaymentArrangementGroup('table_name'), $dao->id);
    }
  }
}

function spodoosync_paymentarrangement() {
  if (class_exists('CRM_Paymentarrangement_Config')) {
    return true;
  }
  return false;
}

function spodoosync_civicrm_buildForm($formName, &$form) {
  CRM_Spodoosync_ContactInOdoo::buildForm($formName, $form);

  if ($formName == 'CRM_Contact_Form_Merge') {
    // add a form validation rule when contacts are merged
    $form->addFormRule(array('CRM_Spodoosync_ContactInOdoo', 'mergeFormRule'), $form);
  }
	
	// Set invoice_address checkbox to checked when the first address is added
	if ($formName == 'CRM_Contact_Form_Contact' && $form->_action == CRM_Core_Action::ADD && !$form->_contactId) {
		// This form is used for a new contact;
		$defaults['address'][1]['is_billing'] = TRUE;
		$form->setDefaults($defaults);
	}
	if ($formName == 'CRM_Contact_Form_Inline_Address' && $form->_action == CRM_Core_Action::ADD) {
		// If we add a new address and this is the first address set the is_billing to one.
		$defaults = $form->getVar('_values');
		if (empty($defaults['address'])) {
			// We can only retrieve the location number from the REQUEST variable. It is set on the form class as a private property.
			$locBlockNo = CRM_Utils_Request::retrieve('locno', 'Positive', CRM_Core_DAO::$_nullObject, TRUE, NULL, $_REQUEST);
			if ($locBlockNo == 1) {
				$newDefaults['address'][1]['is_billing'] = 1;
				$form->setDefaults($newDefaults);
			}
		}
	}
}

function spodoosync_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  CRM_Spodoosync_ContactInOdoo::validateForm($formName, $fields, $files, $form, $errors);
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
    $extensions = civicrm_api3('Extension', 'get', array('options' => array('limit' => false)));
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
