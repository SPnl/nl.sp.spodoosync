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

}
