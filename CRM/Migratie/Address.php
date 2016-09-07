<?php

/**
 * Class for Domus Medica Address Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date July 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_Address extends CRM_Migratie_Domus {

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
        $insertQuery = 'INSERT INTO civicrm_address SET '.implode(', ', $this->_insertClauses);
        try {
          CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
          return TRUE;
        } catch (Exception $ex) {
          $this->_logger->logMessage('Error', 'Error from CRM_Core_DAO::executeQuery, could not insert address with data '
            .implode('; ', $this->_sourceData).', not migrated. Error message : '.$ex->getMessage());
        }         
      } else {
        $this->_logger->logMessage('Error', 'Could not find a contact with contact_id '
          .$this->_sourceData['contact_id'].' for address, not migrated.');
      }
    }
    return FALSE;
  }

  /**
   * Implementation of method to set the insert clauses and params for address
   * 
   * @access private
   */
  public function setClausesAndParams() {
    // set all address columns that always have a value and check is primary
    $this->checkIsPrimary();
    $this->_insertClauses[] = 'contact_id = %1';
    $this->_insertParams[1] = array($this->_sourceData['contact_id'], 'Integer');
    $this->_insertClauses[] = 'is_primary = %2';
    $this->_insertParams[2] = array($this->_sourceData['is_primary'], 'Integer');
    $this->_insertClauses[] = 'location_type_id = %3';
    $this->_insertParams[3] = array($this->_sourceData['location_type_id'], 'Integer');
    $this->_insertClauses[] = 'is_billing = %4';
    $this->_insertParams[4] = array(0, 'Integer');
    $this->_insertClauses[] = 'manual_geo_code = %4';
    $this->_insertClauses[] = 'country_id = %5';
    $this->_insertParams[5] = array($this->_sourceData['country_id'], 'Integer');
    $index = 5;
    // flexible columns only if not empty
    $flexibleColumns = array('street_address', 'city', 'postal_code');
    foreach ($flexibleColumns as $columnName) {
      if (!empty($this->_sourceData[$columnName])) {
        $index++;
        $this->_insertClauses[] = $columnName.' = %'.$index;
        $this->_insertParams[$index] = array($this->_sourceData[$columnName], 'String');
      }
    }
  }
  
  /**
   * Implementation of method to validate if source data is good enough for address
   *
   * @return bool
   */
  public function validSourceData() {

    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'Address has no contact_id, address not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    if (!$this->validLocationType()) {
      return FALSE;
    }

    if (!empty($this->_sourceData['country_id'])) {
      try {
        $count = civicrm_api3('Country', 'getcount', array('id' => $this->_sourceData['country_id']));
        if ($count != 1) {
          $this->_logger->logMessage('Warning', 'Address with contact_id ' . $this->_sourceData['contact_id']
            . ' does not have a valid country_id (' . $count . ' of ' . $this->_sourceData['country_id']
            . 'found), address created without country');
          $this->_sourceData['country_id'] = 0;
        }
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Warning', 'Error retrieving country from CiviCRM for address with contact_id '
          . $this->_sourceData['contact_id'] . ' and country_id' . $this->_sourceData['country_id']
          . ', address migrated without country. Error from API Country getcount : ' . $ex->getMessage());
        $this->_sourceData['country_id'] = 0;
      }
    }
    return TRUE;
  }
}