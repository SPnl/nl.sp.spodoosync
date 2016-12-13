<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Spodoosync_Config {

  private static $singleton;

  public $direct_debit_payment_instrument_id;

  private function __construct() {
    $this->direct_debit_payment_instrument_id = civicrm_api3('OptionValue', 'getvalue' , array(
      'option_group_id' => 'payment_instrument',
      'name' => 'sp_automatischincasse',
      'return' => 'value',
    ));
  }

  /**
   * @return \CRM_Spodoosync_Config
   */
  public static function singleton() {
    if (!self::$singleton) {
      self::$singleton = new CRM_Spodoosync_Config();
    }
    return self::$singleton;
  }

}