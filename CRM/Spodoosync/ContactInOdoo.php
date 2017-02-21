<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Spodoosync_ContactInOdoo {
  
  public static function validateForm($formName, &$fields, &$files, &$form, &$errors) {
    if ($formName == 'CRM_Contact_Form_Contact' || $formName == 'CRM_Contact_Form_Inline_CustomData') {
      $groupId = self::getCustomGroupId();
      $fieldId = self::getCustomFieldId();
      $groupTree = $form->getVar('_groupTree');
      if (isset($groupTree[$groupId]) && isset($groupTree[$groupId]['fields'][$fieldId])) {
        $elementName = $groupTree[$groupId]['fields'][$fieldId]['element_name'];
        $value = $fields[$elementName];
        $contactId = $form->getVar('_contactId');
        $currentValue = self::checkContactInOdoo($contactId);
        if ($value && !$currentValue) {
          $objectList = CRM_Odoosync_Objectlist::singleton();
          $objectList->restoreSyncItem('civicrm_contact', $contactId);
        }
      }
    }
  }

  public static function mergeFormRule($fields, $files, $self) {
    $errors = array();

    // check if left contact has "sync with odoo = yes"
    if (CRM_Spodoosync_ContactInOdoo::checkContactInOdoo($self->_oid)) {
      $errors['_qf_default'] = ts("Het contact aan de linkerkant bestaat in Odoo. Samenvoegen niet toegelaten!");
    }

    return $errors;
  }

  /**
   * Returns whether the field Contact in Odoo is set to yes.
   *
   * @param $contact_id
   * @return bool
   */
  public static function checkContactInOdoo($contact_id) {
    if (!$contact_id) {
      return false;
    }
    $sql = "SELECT `in_odoo` FROM `civicrm_value_odoo_contact` WHERE `entity_id` = %1";
    $params[1] = array($contact_id, 'Integer');
    $contact_in_odoo = CRM_Core_DAO::singleValueQuery($sql, $params);
    return $contact_in_odoo ? true : false;
  }

  /**
   * When Contact in Odoo is set to yes disable the field.
   *
   * When a contact is not synced to Odoo the user can
   * define whether the contact hsould be synced to Odoo.
   * Once the contact is in Odoo it is not possible to undo
   * the sync.
   * That is why we disable the field Contact in Odoo when it is set to yes
   *
   * @param $formName
   * @param $form
   */
  public static function buildForm($formName, &$form) {
    if ($formName == 'CRM_Contact_Form_Contact' || $formName == 'CRM_Contact_Form_Inline_CustomData') {
      $groupId = self::getCustomGroupId();
      $fieldId = self::getCustomFieldId();
      $groupTree = $form->getVar('_groupTree');
      if (isset($groupTree[$groupId]) && isset($groupTree[$groupId]['fields'][$fieldId])) {
        $elementName = $groupTree[$groupId]['fields'][$fieldId]['element_name'];
        $value = $groupTree[$groupId]['fields'][$fieldId]['element_value'];
        if ($form->elementExists($elementName)) {
          $element = $form->getElement($elementName);
          if ($value) {
            $element->freeze();
          }
        }
      }
    }
  }
  
  protected static function getCustomGroupId() {
    $cfsp = CRM_Spgeneric_CustomField::singleton();
    return $cfsp->getGroupId('odoo');
  }

  protected static function getCustomFieldId() {
    $cfsp = CRM_Spgeneric_CustomField::singleton();
    return $cfsp->getFieldId('odoo', 'in_odoo');
  }

}
