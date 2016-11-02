<?php

/**
 * Class for Domus Medica Website Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date Nov 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_Website extends CRM_Migratie_Domus {

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
        $insertQuery = 'INSERT INTO civicrm_website SET '.implode(', ', $this->_insertClauses);
        try {
          CRM_Core_DAO::executeQuery($insertQuery, $this->_insertParams);
          return TRUE;
        } catch (Exception $ex) {
          $this->_logger->logMessage('Error', 'Error from CRM_Core_DAO::executeQuery, could not insert website with data '
            .implode('; ', $this->_sourceData).', not migrated. Error message : '.$ex->getMessage());
        }
      } else {
        $this->_logger->logMessage('Error', 'Could not find a contact with contact_id '
          .$this->_sourceData['contact_id'].' for website, not migrated.');
      }
    }
    return FALSE;
  }

  /**
   * Implementation of method to set the insert clauses and params for email
   * 
   * @access private
   */
  public function setClausesAndParams() {
    $this->_insertClauses[] = 'contact_id = %1';
    $this->_insertParams[1] = array($this->_sourceData['contact_id'], 'Integer');
    $this->_insertClauses[] = 'website_type_id = %2';
    $this->_insertParams[2] = array($this->_sourceData['website_type_id'], 'Integer');
    $this->_insertClauses[] = 'url = %3';
    $this->_insertParams[3] = array($this->_sourceData['url'], 'String');
  }

  /**
   * Implementation of method to validate if source data is good enough for website
   *
   * @return bool
   */
  public function validSourceData() {

    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'Email has no contact_id, website not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    if (empty($this->_sourceData['url'])) {
      $this->_logger->logMessage('Error', 'Website has an empty url, website not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }

    return TRUE;
  }
}