<?php
/**
 * Membership.Migrate API for Domus migration of memberships
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 * @author Erik Hommel <erik.hommel@civicoop.org>
 * @date Sep 2016
 * @license AGPL-3.0
 */
function civicrm_api3_membership_Migrate($params) {
  set_time_limit(0);
  $entity = "membership";
  $returnValues = array();
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migratie_Logger($entity);
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM domus_membership WHERE is_processed = 0 LIMIT 2500');
  while ($daoSource->fetch()) {
    $civiMembership = new CRM_Migratie_Membership($entity, $daoSource, $logger);
    $newMembership = $civiMembership->migrate();
    if ($newMembership == FALSE) {
      $logCount++;
    } else {
      $createCount++;
    }
    $updateQuery = 'UPDATE domus_membership SET is_processed = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
  }
  if (empty($daoSource->N)) {
    $returnValues[] = 'No more memberships to migrate';
  } else {
    $returnValues[] = $createCount.' memberships migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'Membership', 'Migrate');
}

