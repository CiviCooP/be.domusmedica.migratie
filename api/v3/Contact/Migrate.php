<?php
/**
 * Contact.Migrate API for Domus migration of contacts
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
function civicrm_api3_contact_Migrate($params) {
  set_time_limit(0);
  $returnValues = array();
  $entity = 'contact';
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migratie_Logger($entity);
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM domus_contact WHERE is_processed = 0 ORDER BY id');
  while ($daoSource->fetch()) {
    $civiContact = new CRM_Migratie_Contact($entity, $daoSource, $logger);
    $newContact = $civiContact->migrate();
    if ($newContact == FALSE) {
      $logCount++;
    } else {
      $createCount++;
    }
    $updateQuery = 'UPDATE domus_contact SET is_processed = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
  }
  // set max contact id + 1 as the auto increment key for contact_id
  $maxId = CRM_Core_DAO::singleValueQuery('SELECT MAX(id) FROM civicrm_contact');
  $maxId++;
  CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_contact AUTO_INCREMENT = '.$maxId);
  
  if (empty($daoSource->N)) {
    $returnValues[] = 'No more contacts to migrate';
  } else {
    $returnValues[] = $createCount.' contacts migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'Contact', 'Migrate');
}

