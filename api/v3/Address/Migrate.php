<?php
/**
 * Address.Migrate API for Domus migration of addresses
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
function civicrm_api3_address_Migrate($params) {
  set_time_limit(0);
  $entity = "address";
  $returnValues = array();
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migratie_Logger($entity);
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM domus_address WHERE is_processed = 0 ORDER BY contact_id');
  while ($daoSource->fetch()) {
    $civiAddress = new CRM_Migratie_Address($entity, $daoSource, $logger);
    $newAddress = $civiAddress->migrate();
    if ($newAddress == FALSE) {
      $logCount++;
    } else {
      $createCount++;
    }
    $updateQuery = 'UPDATE domus_address SET is_processed = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
  }
  if (empty($daoSource->N)) {
    $returnValues[] = 'No more addresses to migrate';
  } else {
    $returnValues[] = $createCount.' addresses migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'Address', 'Migrate');
}

