<?php

/**
 * Abstract class for Domus Medica Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date July 2016
 * @license AGPL-3.0
 */
abstract class CRM_Migratie_Domus {
  protected $_logger = NULL;
  protected $_sourceData = array();
  protected $_insertClauses = array();
  protected $_insertParams = array();
  protected $_entity = NULL;

  /**
   * CRM_Migratie_Domus constructor.
   *
   * @param string $entity
   * @param object $sourceData
   * @param object $logger
   * @throws Exception when entity invalid
   */
  public function __construct($entity, $sourceData, $logger) {
    $entity = strtolower($entity);
    if (!$this->entityCanBeMigrated($entity)) {
      throw new Exception('Entity '.$entity.' can not be migrated.');
    } else {
      $this->_entity = $entity;
      $this->_sourceData = (array)$sourceData;
      $this->cleanSourceData();
      $this->_logger = $logger;
    }
  }

  /**
   * Method to remove DAO parts of source data and unnecessary is processed
   *
   * @access private
   */
  private function cleanSourceData() {
    foreach ($this->_sourceData as $sourceKey => $sourceValue) {
      if ($sourceKey == 'N' || substr($sourceKey, 0, 1) == '_') {
        unset($this->_sourceData[$sourceKey]);
      }
    }
    if (isset($this->_sourceData['is_processed'])) {
      unset($this->_sourceData['is_processed']);
    }
  }

  /**
   * Method to check if entity can be migrated
   * 
   * @param string $entity
   * @return bool
   * @access private
   */
  private function entityCanBeMigrated($entity) {
    $validEntities = array('address', 'contact', 'email', 'entity_tag', 'note', 'phone');
    if (!in_array($entity, $validEntities)) {
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * Abstract method to migrate incoming data
   */
  abstract function migrate();

  /**
   * Abstract method to set the insert clauses and params
   */
  abstract function setClausesAndParams();

  /**
   * Abstract Method to validate if source data is good enough
   */
  abstract function validSourceData();

  /**
   * Check if is_primary is set to 1, it can actually be set and otherwise set to 0 and log
   *
   * @access protected
   */
  protected function checkIsPrimary() {
    if ($this->_sourceData['is_primary'] == 1) {
      $countQuery = 'SELECT COUNT(*) FROM civicrm_'.$this->_entity.' WHERE contact_id = %1 AND is_primary = %2';
      $countParams = array(
        1 => array($this->_sourceData['contact_id'], 'Integer'),
        2 => array(1, 'Integer')
      );
      $countPrimary = CRM_Core_DAO::singleValueQuery($countQuery, $countParams);
      if ($countPrimary > 0) {
        $this->_logger->logMessage('Warning', $this->_entity.'  for contact ' .
          $this->_sourceData['contact_id'] . ' was Excellent in source but could not be primary in CiviCRM 
          because there is another primary '.$this->_entity.' already. Migrated as non-primary');
        $this->_sourceData['is_primary'] = 0;
      }
    }
  }

  /**
   * Method to check if contact already exists
   * 
   * @param int $contactId
   * @return bool
   * @access protected
   */
  protected function contactExists($contactId) {
    $query = 'SELECT COUNT(*) FROM civicrm_contact WHERE id = %1';
    $params = array(1 => array($contactId, 'Integer'));
    $countContact = CRM_Core_DAO::singleValueQuery($query, $params);
    if ($countContact == 0) {
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * Method to check if location type is valid
   *
   * @return bool
   * @access protected
   */
  protected function validLocationType() {
    if (!isset($this->_sourceData['location_type_id'])) {
      $this->_logger->logMessage('Warning', $this->_entity.' of contact_id '.$this->_sourceData['contact_id']
        .'has no location_type_id, location_type_id 1 used');
      $this->_sourceData['location_type_id'] = 1;
    } else {
      try {
        $count = civicrm_api3('LocationType', 'getcount', array('id' => $this->_sourceData['location_type_id']));
        if ($count != 1) {
          $this->_logger->logMessage('Warning', $this->_entity.' with contact_id ' . $this->_sourceData['contact_id']
            . ' does not have a valid location_type_id (' . $count . ' of ' . $this->_sourceData['location_type_id']
            . 'found), created with location_type_id 1');
          $this->_sourceData['location_type_id'] = 1;
        }
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Error retrieving location type id from CiviCRM for '.$this->_entity
          .' with contact_id '. $this->_sourceData['contact_id'] . ' and location_type_id' 
          . $this->_sourceData['location_type_id']
          . ', ignored. Error from API LocationType getcount : ' . $ex->getMessage());
        return FALSE;
      }
    }
    return TRUE;
  }
}