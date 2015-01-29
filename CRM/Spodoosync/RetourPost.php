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
            $this->migratie_set = civicrm_api3('CustomGroup', 'getsingle', array('name' => 'Migratie_Contacten'));
            $this->retourpost_field = civicrm_api3('CustomField', 'getsingle', array('custom_group_id' => $this->migratie_set['id'], 'name' => 'Retourpost'));
        } catch (Exception $e) {
            //do nothing
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
