<?php

/**
 * Gemeenten.Load API - Migration of option group with name gemeenten
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_gemeenten_Load($params) {
  $returnValues = array();
  $dao = CRM_Core_DAO::executeQuery('SELECT * FROM domus_gemeenten');
  while ($dao->fetch()) {
    $gemeenteParams = array(
      'option_group_id' => 'gemeenten',
      'label' => $dao->domus_label,
      'value' => $dao->domus_value
    );
    civicrm_api3('OptionValue', 'create', $gemeenteParams);
  }
  return civicrm_api3_create_success($returnValues, $params, 'Gemeenten', 'Load');
}

