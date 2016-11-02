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
function civicrm_api3_kringdb_Migrate($params) {
  set_time_limit(0);
  $returnValues = array();
  $kringRow = new CRM_Migratie_KringDb();
  $dao = CRM_Core_DAO::executeQuery('SELECT * FROM db_kringen');
  while ($dao->fetch()) {
    $kringRow->processRow($dao);
  }
  return civicrm_api3_create_success($returnValues, $params, 'KringDb', 'Migrate');
}

