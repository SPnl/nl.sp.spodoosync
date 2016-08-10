<?php
/**
 * Created by PhpStorm.
 * User: jaap
 * Date: 1/29/15
 * Time: 7:40 PM
 */

class CRM_Spodoosync_RetourPost {

    protected static $singleton;

    protected $migratie_set = false;
    protected $retourpost_field = false;

    protected function __construct() {
        try {
          $cfsp = CRM_Spgeneric_CustomField::singleton();
          $this->migratie_set = $cfsp->getGroupByName('Migratie_Contacten');
          $this->retourpost_field = $cfsp->getField('Migratie_Contacten', 'Retourpost');
        } catch (Exception $e) {
          // do nothing
        }
    }

    /**
     * @return CRM_Spodoosync_RetourPost
     */
    public static function singleton() {
        if (!self::$singleton) {
            self::$singleton = new CRM_Spodoosync_RetourPost();
        }
        return self::$singleton;
    }

    public static function syncRetourPostToOdoo($contact_id, &$parameters) {
        $i = CRM_Spodoosync_RetourPost::singleton();

        if (!is_array($i->migratie_set) || !is_array($i->retourpost_field)) {
            return;
        }

        try {
            $retourPost = civicrm_api3('Contact', 'getvalue', array('id' => $contact_id, 'return' => 'custom_'.$i->retourpost_field['id']));
            $parameters['retourpost'] = new xmlrpcval($retourPost, 'boolean');
        } catch (Exception $e) {

            return;
        }
    }

}
