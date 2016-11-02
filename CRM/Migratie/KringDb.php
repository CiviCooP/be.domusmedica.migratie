<?php

/**
 * Class for Domus Medica KringDB Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date Oct 2016
 * @license AGPL-3.0
 */
class CRM_Migratie_KringDb {

  private $_kringData = array();
  private $_jobTitleOptionGroupId = NULL;
  private $_kringLidRelationshipTypeId = NULL;
  private $_kringLidCustomTable = NULL;
  private $_customJobTitleColumn = NULL;
  private $_physicianCustomTable = NULL;
  private $_customRizivColumn = NULL;
  private $_correspondentieLocationTypeId = NULL;
  private $_mobilePhoneTypeId = NULL;
  private $_logger = NULL;

  /**
   * CRM_Migratie_KringDb constructor.
   */
  function __construct() {
    $this->_logger = new CRM_Migratie_Logger('kringdb');
    // set option groups for job titles and populate
    $jobTitleCustomField = civicrm_api3('CustomField', 'getsingle', array(
      'custom_group_id' => 'kring_lid_data',
      'name' => 'job_titles'));
    $this->_jobTitleOptionGroupId = $jobTitleCustomField['option_group_id'];
    $this->setAllJobTitles();
    // set relationship type id for kring lid
    $this->_kringLidRelationshipTypeId = civicrm_api3('RelationshipType', 'getvalue', array(
      'name_a_b' => 'is lid van kring',
      'name_b_a' => 'kringlid is',
      'return' => 'id'));
    // set custom group table name for kring lid data
    $this->_kringLidCustomTable = civicrm_api3('CustomGroup', 'getvalue', array(
      'name' => 'kring_lid_data',
      'return' => 'table_name'));
    // set custom field column name for job titles in kring lid data
    $this->_customJobTitleColumn = $jobTitleCustomField['column_name'];
    // set custom group table name for physician data
    $this->_physicianCustomTable = civicrm_api3('CustomGroup', 'getvalue', array(
      'name' => 'physician_data',
      'return' => 'table_name'));
    // set custom field column name for riziv id field in physician data
    $this->_customRizivColumn = civicrm_api3('CustomField', 'getvalue', array(
      'custom_group_id' => 'physician_data',
      'name' => 'riziv_id',
      'return' => 'column_name'));
    // set correspondentie locaiton type id
    $this->_correspondentieLocationTypeId = civicrm_api3('LocationType', 'getvalue', array(
      'name' => 'correspondentieadres',
      'return' => 'id'));
    // set mobile phone type id
    $this->_mobilePhoneTypeId = civicrm_api3('OptionValue', 'getvalue', array(
      'option_group_id' => 'phone_type',
      'name' => 'Mobile',
      'return' => 'value'));
  }

  /**
   * Method to populate job title option group
   */
  private function setAllJobTitles() {
    $counter = 1;
    $dao = CRM_Core_DAO::executeQuery('SELECT DISTINCT(Functienaam) FROM db_kringen');
    while ($dao->fetch()) {
      $countOption = civicrm_api3('OptionValue', 'getcount', array(
        'option_group_id' => $this->_jobTitleOptionGroupId,
        'label' => $dao->Functienaam
      ));
      if ($countOption == 0) {
        $optionValueParams = array(
          'option_group_id' => $this->_jobTitleOptionGroupId,
          'label' => $dao->Functienaam,
          'value' => $counter,
          'weight' => $counter,
          'is_active' => 1,
          'is_reserved' => 1,
          'name' => 'domus_job_title_'.$counter
        );
        $counter++;
        civicrm_api3('OptionValue', 'create', $optionValueParams);
      }
    }
  }

  /**
   * Method to get array with relevant data from incoming dao

   * @param $dao
   * @return array
   */
  private function retrieveDbRow($dao) {
    $result = array();
    $elements = array('naam', 'voornaam', 'Geslacht', 'email', 'mobile', 'ORG-naam', 'Functienaam');
    foreach ($elements as $element) {
      $result[$element] = $dao->$element;
    }
    $result['riziv_id'] = $this->cleanRiziv($dao->rizvnr);
    return $result;
  }

  /**
   * Method to process data row from DB Kringen
   * @param $dataRow
   */
  public function processRow($dataRow) {
    $this->_kringData = $this->retrieveDbRow($dataRow);
    // find kring and add it to kringData
    $this->lookupKring();
    // find or create contact with riziv or naam and add contact to kringData
    $this->lookupContact();
    // check if relationship needs to be added with custom data
    $this->createRelationship();
  }

  /**
   * Method to format the RIZIV number if there are '-' or '/' or spaces in the string
   *
   * @param $rizivId
   * @return string
   */
  private function cleanRiziv($rizivId) {
    $formattedRiziv = trim($rizivId);
    // first check for '-'
    $minusParts = explode('-', $formattedRiziv, -1);
    if (!empty($minusParts)) {
      $cleanedParts = array();
      foreach ($minusParts as $minusPart) {
        $cleanedParts[] = trim($minusPart);
      }
      $formattedRiziv = implode('', $cleanedParts);
      return $formattedRiziv;
    }
    // next check for '/'
    $slashParts = explode('/', $formattedRiziv, -1);
    if (!empty($slashParts)) {
      $cleanedParts = array();
      foreach ($slashParts as $slashPart) {
        $cleanedParts[] = trim($slashPart);
      }
      $formattedRiziv = implode('', $cleanedParts);
      return $formattedRiziv;
    }
    // finally check for spaces
    $spaceParts = explode(' ', $formattedRiziv, -1);
    if (!empty($spaceParts)) {
      $cleanedParts = array();
      foreach ($spaceParts as $spacePart) {
        $cleanedParts[] = trim($spacePart);
      }
      $formattedRiziv = implode('', $cleanedParts);
      return $formattedRiziv;
    }
    // if nothing return trimmed incoming string
    return $formattedRiziv;
  }

  /**
   * Method to lookup the kring based on organization name
   */
  private function lookupKring() {
    $sql = 'SELECT id FROM civicrm_contact WHERE is_deleted = %1 AND contact_type = %2 AND contact_sub_type LIKE %3 
      AND organization_name = %4';
    $params = array(
      1 => array(0, 'Integer'),
      2 => array('Organization', 'String'),
      3 => array('%Huisartsenkring%', 'String'),
      4 => array($this->_kringData['ORG_naam'], 'String'));
    $contactId = CRM_Core_DAO::singleValueQuery($sql, $params);
    if ($contactId) {
      $this->_kringData['kring_id'] = $contactId;
    } else {
      $this->_logger->logMessage('Warning', 'No kring found with name '.$this->_kringData['ORG_naam']
        .', no relationship created and no job title set');
      $this->_kringData['kring_id'] = NULL;
    }
  }

  /**
   * Method to find contact with either riziv and if that gives no result, try on first_name and last_name
   */
  private function lookupContact() {
    // try with riziv id and return if found
    if (!empty($this->_kringData['riziv_id'])) {
      $sqlRiziv = 'SELECT entity_id FROM '.$this->_physicianCustomTable.' WHERE '.$this->_customRizivColumn.' = %1';
      $contactId = CRM_Core_DAO::singleValueQuery($sqlRiziv, array(1 => array($this->_kringData['riziv_id'], 'String')));
      if ($contactId) {
        $this->_kringData['contact_id'] = $contactId;
        return;
      }
    }
    $sqlNames = 'SELECT id FROM civicrm_contact WHERE first_name = %1 AND last_name = %2';
    $paramsNames = array(
      1 => array($this->_kringData['voornaam'], 'String'),
      2 => array($this->_kringData['naam'], 'String'));
    $dao = CRM_Core_DAO::executeQuery($sqlNames, $paramsNames);
    if ($dao->N > 1) {
      $this->_logger->logMessage('Error', 'More contacts found with name '.$this->_kringData['voornaam'].' '
        .$this->_kringData['naam'].', no relationship created and no job title set');
    } else {
      if ($dao->fetch()) {
        $this->_kringData['contact_id'] = $dao->id;
      } else {
        $this->createContact();
      }
    }
  }

  /**
   * Method to create contact if no contact found
   */
  private function createContact() {
    $this->_logger->logMessage('Warning', 'No contact found for riziv '.$this->_kringData['riziv_id']. ' and name '
      .$this->_kringData['voornaam'].' '.$this->_kringData['naam'].', new contact created');
    $sourceGender = strtolower($this->_kringData['Geslacht']);
    switch ($sourceGender) {
      case 'man':
        $genderId = 2;
        break;
      case 'vrouw':
        $genderId = 1;
        break;
      default:
        $genderId = 4;
        break;
    }
    $contactParams = array(
      'contact_type' => 'Individual',
      'source' => 'Migratie DB Kringen',
      'first_name' => $this->_kringData['voornaam'],
      'last_name' => $this->_kringData['naam'],
      'gender_id' => $genderId
    );

    try {
      $createdContact = civicrm_api3('Contact', 'create', $contactParams);
      $this->_kringData['contact_id'] = $createdContact['id'];
      $this->addContactCustomData($createdContact['id']);
      $this->addContactEmail($createdContact['id']);
      $this->addContactMobile($createdContact['id']);
    } catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Error', 'Unable to create contact riziv '.$this->_kringData['riziv_id']. ' and name '
        .$this->_kringData['voornaam'].' '.$this->_kringData['naam'].', no relationship created and no job tilte set');
    }
  }

  /**
   * Method to add mobile phone for contact if required
   * @param $contactId
   */
  private function addContactMobile($contactId) {
    if (!empty($this->_kringData['mobile'])) {
      $phoneParams = array(
        'contact_id' => $contactId,
        'location_type_id' => $this->_correspondentieLocationTypeId,
        'phone' => $this->_kringData['mobile'],
        'phone_type_id' => $this->_mobilePhoneTypeId,
        'is_primary' => 1
      );
      civicrm_api3('Phone', 'create', $phoneParams);
    }
  }

  /**
   * Method to add email for contact if required
   * @param $contactId
   */
  private function addContactEmail($contactId) {
    if (!empty($this->_kringData['email'])) {
      $emailParams = array(
        'contact_id' => $contactId,
        'location_type_id' => $this->_correspondentieLocationTypeId,
        'email' => $this->_kringData['email'],
        'is_primary' => 1
      );
      civicrm_api3('Email', 'create', $emailParams);
    }
  }

  /**
   * Method to add custom data (riziv id) for contact if required
   * @param $contactId
   */
  private function addContactCustomData($contactId) {
    if (!empty($this->_kringData['riziv_id'])) {
      $sql = 'INSERT INTO '.$this->_physicianCustomTable.' (entity_id, '.$this->_customRizivColumn.') VALUES(%1, %2)';
      CRM_Core_DAO::executeQuery($sql, array(
        1 => array($contactId, 'Integer'),
        2 => array($this->_kringData['riziv_id'], 'String')));
    }
  }

  /**
   * Method to check if relationsip exists (and add job title if it does)
   *
   * @return bool
   */
  private function relationshipExists() {
    try {
      $relationshipId = civicrm_api3('Relationship', 'getvalue', array(
        'contact_id_a' => $this->_kringData['contact_id'],
        'contact_id_b' => $this->_kringData['kring_id'],
        'relationship_type_id' => $this->_kringLidRelationshipTypeId
      ));
      $this->addJobTitle($relationshipId);
      return TRUE;
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to create relationship if required
   */
  private function createRelationship() {
    if (!empty($this->_kringData['kring_id']) && !empty($this->_kringData['contact_id'])) {
      if ($this->relationshipExists() == FALSE) {
        $relationshipParams = array(
          'contact_id_a' => $this->_kringData['contact_id'],
          'contact_id_b' => $this->_kringData['kring_id'],
          'relationship_type_id' => $this->_kringLidRelationshipTypeId
        );
        try {
          $createdRelationship = civicrm_api3('Relationship', 'create', $relationshipParams);
          $this->addJobTitle($createdRelationship['id']);
        } catch (CiviCRM_API3_Exception $ex) {
          $this->_logger->logMessage('Error', 'Unable to create realtionship between contact id'.$this->_kringData['contact_id']
            . ' and kring contact '.$this->_kringData['kring_id']);
        }
      }
    }
  }

  /**
   * Method to add job title to custom data for relationship taking multiple into account
   *
   * @param $relationshipId
   */
  private function addJobTitle($relationshipId) {
    if (!empty($this->_kringData['Functienaam'])) {
      // first retrieve value of job title from civicrm_option_value
      try {
        $newJobTitleValue = civicrm_api3('OptionValue', 'getvalue', array(
          'option_group_id' => $this->_jobTitleOptionGroupId,
          'label' => $this->_kringData['Functienaam'],
          'return' => 'value'));
        // then get existing custom record if there
        $existingSql = 'SELECT ' . $this->_customJobTitleColumn . ' FROM ' . $this->_kringLidCustomTable . ' WHERE entity_id = %1';
        $existingJobTitles = CRM_Core_DAO::singleValueQuery($existingSql, array(1 => array($relationshipId, 'Integer')));
        if ($existingJobTitles) {
          $newJobTitles = $existingJobTitles . $newJobTitleValue . CRM_Core_DAO::VALUE_SEPARATOR;
          $sql = 'UPDATE '.$this->_kringLidCustomTable.' SET '.$this->_customJobTitleColumn.' = %1 WHERE entity_id = %2';
        } else {
          $newJobTitles = CRM_Core_DAO::VALUE_SEPARATOR.$newJobTitleValue.CRM_Core_DAO::VALUE_SEPARATOR;
          $sql = 'INSERT INTO '.$this->_kringLidCustomTable.' ('.$this->_customJobTitleColumn.', entity_id) VALUES(%1, %2)';
        }
        $params = array(
          1 => array($newJobTitles, 'String'),
          2 => array($relationshipId, 'Integer'));
        CRM_Core_DAO::executeQuery($sql, $params);
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Waring', 'Unable to find job ' . $this->_kringData['Functienaam']);
      }
    }
  }
}