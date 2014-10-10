<?php

/**
 * Collection of upgrade steps
 */
class CRM_Spodoosync_Upgrader extends CRM_Spodoosync_Upgrader_Base {

  public function install() {
    $this->addPaymentInstruments();
  }
  
  public function upgrade_1001() {
    $this->addPaymentInstruments();
    return true;
  }
  
  protected function addPaymentInstruments() {
    $pi_gid = civicrm_api3('OptionGroup', 'getvalue', array('return' => 'id', 'name' => 'payment_instrument'));
    $this->addOptionValue('sp_acceptgiro', 'Acceptgiro (Beschikbaar in Odoo)', $pi_gid);
    $this->addOptionValue('sp_automatischincasse', 'Automatisch incasso (Beschikbaar in Odoo)', $pi_gid);
  }
  
  protected function addOptionValue($name, $label, $option_group_id) {
    try {
      $exist_id = civicrm_api3('OptionValue', 'getvalue', array('return' => 'id', 'name' => $name, 'option_group_id' => $option_group_id));
      return; //aleardy exist
    } catch (Exception $e) {
      //do nothing
    }
    
    $params['name'] = $name;
    $params['label'] = $label;
    $params['option_group_id'] = $option_group_id;
    civicrm_api3('OptionValue','create', $params);
  }

}
