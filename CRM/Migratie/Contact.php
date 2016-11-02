<?php

/**
 * Class for Domus Medica Contact Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date July 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_Contact extends CRM_Migratie_Domus {

  /**
   * Method to migrate incoming data
   * 
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      if (!$this->contactExists($this->_sourceData['id'])) {
        // no longer required, see <https://civicoop.plan.io/issues/485>
        //if (!$this->externalIdExists($this->_sourceData['external_identifier'])) {
        // set insert clauses and params
        $this->setClausesAndParams();
        $insertQuery = 'INSERT INTO civicrm_contact SET ' . implode(', ', $this->_insertClauses);
        try {
          CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
          $this->addCustomData();
          return TRUE;
        } catch (Exception $ex) {
          $this->_logger->logMessage('Error', 'Error from CRM_Core_DAO::executeQuery, could not insert contact with data '
            . implode('; ', $this->_sourceData) . ', not migrated. Error message : ' . $ex->getMessage());
        }
      } else {
        $this->_logger->logMessage('Error', 'Contact '.$this->_sourceData['id'].' with contact id '.$this->_sourceData['id']
          .' already exists, not migrated.');
      }
    }
  }

  /**
   * Implementation of method to set the insert clauses and params
   */
  public function setClausesAndParams() {
    $integerColumns = array('id', 'do_not_email', 'do_not_mail', 'do_not_phone', 'do_not_sms', 'do_not_trade', 'is_opt_out',
      'communication_style_id', 'email_greeting_id', 'postal_greeting_id', 'addressee_id', 'gender_id', 'is_deceased', 'is_deleted');
    $this->_insertClauses[] = 'hash = %1';
    $this->_insertParams[1] = array(md5(uniqid(rand(), TRUE)), 'String');
    $index = 1;
    foreach ($this->_sourceData as $key => $value) {
      if (!empty($value)) {
        $index++;
        $this->_insertClauses[] = $key.' = %'.$index;
        if (in_array($key, $integerColumns)) {
          $this->_insertParams[$index] = array($value, 'Integer');
        } else {
          if ($key == 'contact_sub_type') {
            $value = CRM_Core_DAO::VALUE_SEPARATOR.$value.CRM_Core_DAO::VALUE_SEPARATOR;
          }
          $this->_insertParams[$index] = array($value, 'String');
        }
      }
    }
  }

  /**
   * Implementation of method to validate if source data is good enough for contact
   *
   * @return bool
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['id'])) {
      $this->_logger->logMessage('Error', 'Contact has no contact_id, not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }
    //if (isset($this->_sourceData['external_identifier'])) {
      //$this->_sourceData['external_identifier'] = trim($this->_sourceData['external_identifier']);
    //}
    return TRUE;
  }

  /**
   * Method to check if contact already exists
   *
   * @param int $externalId
   * @return bool
   * @access protected
   */
  private function externalIdExists($externalId) {
    if (isset($this->_sourceData['external_identifier']) && !empty(trim($this->_sourceData['external_identifier']))) {
      $query = 'SELECT COUNT(*) FROM civicrm_contact WHERE id = %1';
      $params = array(1 => array($externalId, 'String'));
      $countContact = CRM_Core_DAO::singleValueQuery($query, $params);
      if ($countContact > 0) {
        return TRUE;
      }
    }
    unset($this->_sourceData['external_identifier']);
    $this->_logger->logMessage('Error', 'External Identifier already exists, contact can not be migrated. Source data is '.implode(';', $this->_sourceData));
    return FALSE;
  }

  /**
   * Method to add contact custom data if necessary
   *
   * @access private
   */
  private function addCustomData() {
    // check if contact appear in physician data and if so, insert
    $physicianQuery = 'SELECT * FROM domus_value_physician_data WHERE entity_id = %1';
    $physicianData = CRM_Core_DAO::executeQuery($physicianQuery,
      array(1 => array($this->_sourceData['id'], 'Integer')));
    if ($physicianData->fetch()) {
      $this->migratePhysician($physicianData);
    }
    // check if contact appear in bvba data and if so, insert
    $bvbaQuery = 'SELECT * FROM domus_value_bvba_data WHERE entity_id = %1';
    $bvbaData = CRM_Core_DAO::executeQuery($bvbaQuery,
      array(1 => array($this->_sourceData['id'], 'Integer')));
    if ($bvbaData->fetch()) {
      $this->migrateBvba($bvbaData);
    }
  }

  /**
   * Method to migrate physician data in civicrm custom table
   *
   * @param object $sourceData
   * @access private
   */
  private function migratePhysician($sourceData) {
    $clauses = array();
    $params = array();
    $clauses[] = 'entity_id = %1';
    $params[1] = array($sourceData->entity_id, 'Integer');
    $clauses[] = 'has_practice = %2';
    if (!empty($sourceData->has_practice)) {
      $params[2] = array($sourceData->has_practice, 'Integer');
    } else {
      $params[2] = array(0, 'Integer');
    }
    if (!empty($sourceData->graduation_year)) {
      $clauses[] = 'graduation_year = %3';
      $params[3] = array($sourceData->graduation_year, 'Integer');
    }
    if (!empty($sourceData->riziv_id)) {
      $clauses[] = 'riziv_id = %4';
      $params[4] = array($sourceData->riziv_id, 'String');
    }
    $query = "INSERT INTO civicrm_value_physician_data SET ".implode(',', $clauses);
    CRM_Core_DAO::executeQuery($query, $params);
  }

  /**
   * Method to migrate bvba data in civicrm custom table
   *
   * @param object $sourceData
   * @access private
   */
  private function migrateBvba($sourceData) {
    $clauses = array();
    $params = array();
    $clauses[] = 'entity_id = %1';
    $params[1] = array($sourceData->entity_id, 'Integer');
    $clauses[] = 'bvba_name = %2';
    if (!empty($sourceData->bvba_name)) {
      $params[2] = array($sourceData->bvba_name, 'String');
    }
    $query = "INSERT INTO civicrm_value_bvba_data SET ".implode(',', $clauses);
    CRM_Core_DAO::executeQuery($query, $params);
  }
}