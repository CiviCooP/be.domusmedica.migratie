<?php

/**
 * Class for Domus Medica Phone Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date July 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_Phone extends CRM_Migratie_Domus {

  /**
   * Method to migrate incoming data
   * 
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      if ($this->contactExists($this->_sourceData['contact_id'])) {
        // set insert clauses and params
        $this->setClausesAndParams();
        $insertQuery = 'INSERT INTO civicrm_phone SET '.implode(', ', $this->_insertClauses);
        try {
          CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
          return TRUE;
        } catch (Exception $ex) {
          $this->_logger->logMessage('Error', 'Error from CRM_Core_DAO::executeQuery, could not insert phone with data '
            .implode('; ', $this->_sourceData).', not migrated. Error message : '.$ex->getMessage());
        }
      } else {
        $this->_logger->logMessage('Error', 'Could not find a contact with contact_id '
          .$this->_sourceData['contact_id'].' for phone, not migrated.');
      }
    }
  }

  /**
   * Implementation of method to set the insert clauses and params for phone
   */
  public function setClausesAndParams() {
    $this->checkIsPrimary();
    $this->_insertClauses[] = 'contact_id = %1';
    $this->_insertParams[1] = array($this->_sourceData['contact_id'], 'Integer');
    $this->_insertClauses[] = 'is_primary = %2';
    $this->_insertParams[2] = array($this->_sourceData['is_primary'], 'Integer');
    $this->_insertClauses[] = 'location_type_id = %3';
    $this->_insertParams[3] = array($this->_sourceData['location_type_id'], 'Integer');
    $this->_insertClauses[] = 'is_billing = %4';
    $this->_insertParams[4] = array(0, 'Integer');
    $this->_insertClauses[] = 'phone_type_id = %5';
    $this->_insertParams[5] = array($this->_sourceData['phone_type_id'], 'Integer');
    $this->_insertClauses[] = 'phone = %6';
    $this->_insertParams[6] = array($this->_sourceData['phone'], 'String');
  }

  /**
   * Method to check if phone type is valid
   *
   * @return bool
   * @access private
   */

  private function validPhoneType() {
    if (!isset($this->_sourceData['phone_type_id'])) {
      $this->_logger->logMessage('Warning', 'Phone of contact_id '.$this->_sourceData['contact_id']
        .'has no phone_type_id, phone_type_id 1 used');
      $this->_sourceData['phone_type_id'] = 1;
    } else {
      try {
        $count = civicrm_api3('OptionValue', 'getcount', array(
          'value' => $this->_sourceData['phone_type_id'],
          'option_group_id' => 'phone_type'));
        if ($count != 1) {
          $this->_logger->logMessage('Warning', 'Phone with contact_id ' . $this->_sourceData['contact_id']
            . ' does not have a valid phone_type_id (' . $count . ' of ' . $this->_sourceData['phone_type_id']
            . 'found), phone created with phone_type_id 1');
          $this->_sourceData['phone_type_id'] = 1;
        }
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Error retrieving phone_type_id from CiviCRM for phone with contact_id '
          . $this->_sourceData['contact_id'] . ' and phone_type_id' . $this->_sourceData['phone_type_id']
          . ', phone ignored. Error from API LocationType getcount : ' . $ex->getMessage());
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Implementation of method to validate if source data is good enough for phone
   *
   * @return bool
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'Phone has no contact_id, Phone not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    if (empty($this->_sourceData['phone'])) {
      $this->_logger->logMessage('Error', 'Phone has an empty phone number, Phone not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    if (!$this->validLocationType()) {
      return FALSE;
    }
    
    if (!$this->validPhoneType()) {
      return FALSE;
    }
    return TRUE;
  }
}