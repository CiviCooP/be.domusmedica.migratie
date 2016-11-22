<?php
/**
 * BirthDate.Correct API Fix migration error with birth date
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_birth_date_Correct($params) {
  // first empty all birth date for individuals
  CRM_Core_DAO::executeQuery("UPDATE civicrm_contact SET birth_date = NULL where birth_date IS NOT NULL");
  $dao = CRM_Core_DAO::executeQuery("SELECT id, birth_date FROM domus_contact WHERE birth_date IS NOT NULL");
  while ($dao->fetch()) {
    $parts = explode('/', $dao->birth_date);
    $birthDate = (string) $parts[2].'-'.$parts[1].'-'.$parts[0];
    $sql = 'UPDATE civicrm_contact SET birth_date = %1 WHERE id = %2';
    $sqlParams = array(
      1 => array($birthDate, 'String'),
      2 => array($dao->id, 'Integer')
    );
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
 }
  return civicrm_api3_create_success(array(), $params, 'BirthDate', 'Correct');
}

