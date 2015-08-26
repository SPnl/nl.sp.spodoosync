<?php

class CRM_Spodoosync_Synchronisator_ContributionSynchronisator extends CRM_OdooContributionSync_ContributionSynchronisator {

  public function performInsert(CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $contribution = $this->getContribution($sync_entity->getEntityId());
    $receive_date = new DateTime($contribution['receive_date']);
    if ($receive_date->format('Y') < 2015) {
      throw new Exception('Do not sync invoices before 2015');
    }
    return parent::performInsert($sync_entity);
  }

  public function performUpdate($odoo_id, CRM_Odoosync_Model_OdooEntity $sync_entity) {
    $contribution = $this->getContribution($sync_entity->getEntityId());
    $receive_date = new DateTime($contribution['receive_date']);
    if ($receive_date->format('Y') < 2015) {
      $this->performDelete($odoo_id, $sync_entity);
      throw new Exception('Do not sync invoices before 2015');
    }
    return parent::performUpdate($odoo_id, $sync_entity);
  }



  }