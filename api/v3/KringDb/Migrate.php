<?php
/**
 * KringDb.Migrate API for Domus migration of Kringen DB voor functies en contacten
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 * @author Erik Hommel <erik.hommel@civicoop.org>
 * @date Oct 2016
 * @license AGPL-3.0
 */
function civicrm_api3_kring_db_Migrate($params) {
  set_time_limit(0);
  $returnValues = array();
  $kringRow = new CRM_Migratie_KringDb();
  $dao = CRM_Core_DAO::executeQuery('SELECT * FROM db_kringen WHERE is_processed = 0');
  while ($dao->fetch()) {
    $kringRow->processRow($dao);
    $updateQuery = 'UPDATE db_kringen SET is_processed = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($dao->id, 'Integer')));

  }
  return civicrm_api3_create_success($returnValues, $params, 'KringDb', 'Migrate');
}

