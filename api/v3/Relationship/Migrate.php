<?php
/**
 * Relationship.Migrate API for Domus migration of relationships
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 * @author Erik Hommel <erik.hommel@civicoop.org>
 * @date Jul 2016
 * @license AGPL-3.0
 */
function civicrm_api3_relationship_Migrate($params) {
  set_time_limit(0);
  $returnValues = array();
  $entity = 'relationship';
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migratie_Logger($entity);
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM domus_relationship WHERE is_processed = 0 LIMIT 2500');
  while ($daoSource->fetch()) {
    $civiRelationship = new CRM_Migratie_Relationship($entity, $daoSource, $logger);
    $newRelationship = $civiRelationship->migrate();
    if ($newRelationship == FALSE) {
      $logCount++;
    } else {
      $createCount++;
    }
    $updateQuery = 'UPDATE domus_relationship SET is_processed = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
  }
  if (empty($daoSource->N)) {
    $returnValues[] = 'No more relationships to migrate';
  } else {
    $returnValues[] = $createCount.' relationships migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'Relationship', 'Migrate');
}

