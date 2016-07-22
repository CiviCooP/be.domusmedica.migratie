<?php
/**
 * EntityTag.Migrate API for Domus migration of notes
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
function civicrm_api3_note_Migrate($params) {
  set_time_limit(0);
  $returnValues = array();
  $entity = 'note';
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migratie_Logger($entity);
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM domus_note WHERE is_processed = 0 ORDER BY entity_id');
  while ($daoSource->fetch()) {
    $civiNote = new CRM_Migratie_Note($entity, $daoSource, $logger);
    $newNote = $civiNote->migrate();
    if ($newNote == FALSE) {
      $logCount++;
    } else {
      $createCount++;
    }
    $updateQuery = 'UPDATE domus_note SET is_processed = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
  }
  if (empty($daoSource->N)) {
    $returnValues[] = 'No more notes to migrate';
  } else {
    $returnValues[] = $createCount.' notes migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'Note', 'Migrate');
}

