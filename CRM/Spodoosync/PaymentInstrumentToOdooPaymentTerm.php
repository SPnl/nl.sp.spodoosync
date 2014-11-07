<?php

class CRM_Spodoosync_PaymentInstrumentToOdooPaymentTerm {
  
  private static $_singleton;
  
  private $payments_terms = array();
  
  private $payment_instruments = array();
  
  private $valid_payment_terms = array(
    'sp_acceptgiro' => 'accept giro',
    'sp_automatischincasse' => 'Direct debit',
  );
  
  private $connector;
  
  private function __construct() {
    $pi_gid = civicrm_api3('OptionGroup', 'getvalue', array('return' => 'id', 'name' => 'payment_instrument'));
    $pis = civicrm_api3('OptionValue', 'get', array('option_group_id' => $pi_gid));
    foreach($pis['values'] as $pi) {
      $this->payment_instruments[$pi['id']] = $pi;
    }
    
    $this->connector = CRM_Odoosync_Connector::singleton();
  }
  
  /**
   * @return CRM_Spodoosync_PaymentInstrumentToOdooPaymentTerm
   */
  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Spodoosync_PaymentInstrumentToOdooPaymentTerm();
    }
    return self::$_singleton;
  }
  
  /**
   * Returns the Odoo payment term for a civicrm payment instrument
   * 
   * Returns false if the payment instrument is not a valid Odoo payment term
   * 
   * @param int $location_type_id
   * @return string|false
   */
  public function getOdooPaymentTerm($payment_instrument_id) {
    if (!isset($this->payments_terms[$payment_instrument_id])) {
      $this->payments_terms[$payment_instrument_id] = $this->getPaymentTerm($payment_instrument_id);
    }
    return $this->payments_terms[$payment_instrument_id];
  }
  
  protected function findPaymentTermId($payment_term_name) {
    $keys = array(
        new xmlrpcval(array(
          new xmlrpcval('name', 'string'),
          new xmlrpcval('=', 'string'),
          new xmlrpcval($payment_term_name, 'string'),
        ), "array"),
    );
    
    $connector = CRM_Odoosync_Connector::singleton();
    $ids = $connector->search('account.payment.term', $keys);
    foreach($ids as $id_element) {
      return $id_element->scalarval();
    }
    
    return false;
  }
  
  protected function getPaymentTerm($payment_instrument_id) {
    if (!$this->isValidPaymentInstrument($payment_instrument_id)) {
      return false;
    }
    
    $payment_instrument_name = $this->payment_instruments[$payment_instrument_id]['name'];
    $payment_term_name = $this->valid_payment_terms[$payment_instrument_name];
    return $this->findPaymentTermId($payment_term_name);
  }
  
  protected function isValidPaymentInstrument($payment_instrument_id) {
    $valid_payment_instruments = array_keys($this->valid_payment_terms);
    if (!isset($this->payment_instruments[$payment_instrument_id])) {
      return false;
    }
    if (in_array($this->payment_instruments[$payment_instrument_id]['name'], $valid_payment_instruments)) {
      return true;
    }
    return false;
  }
  
}
