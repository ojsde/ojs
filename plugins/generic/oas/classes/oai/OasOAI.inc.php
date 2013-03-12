<?php

/**
 * @defgroup plugins_generic_oas_classes_oai
 */

/**
 * @file plugins/generic/oas/classes/oai/OasOAI.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasOAI
 * @ingroup plugins_generic_oas_classes_oai
 *
 * @brief OA-S data export interface conforming to the OAI-PMH standard.
 */

import('lib.pkp.classes.oai.OAI');
import('plugins.generic.oas.classes.oai.OasOAIMetadataFormat_DC');
import('plugins.generic.oas.classes.oai.OasOAIMetadataFormat_OAS');

class OasOAI extends OAI {
	/** @var $site Site associated site object */
	var $site;

	/**
	 * @var $dao OasEventStagingDAO to retrieve usage event records from the database
	 * TODO: Why is this not in the base class? Is there an OAI implementation without
	 * a corresponding DAO?
	 */
	var $dao;


	/**
	 * Constructor
	 *
	 * @param $request Request
	 */
	function OasOAI($request) {
		$this->site = $request->getSite();

		// We generate a unique repository ID to
		// avoid unnecessary configuration effort.
		$repositoryId = $request->getServerHost() . $request->getBasePath();
		$repositoryId = 'oas.' . str_replace('/', '.', $repositoryId);
		$config = new OAIConfig($request->getRequestUrl(), $repositoryId);
		$config->maxSets = 0; // Sets are not supported.
		parent::OAI($config);
		$this->dao =& DAORegistry::getDAO('OasEventStagingDAO');
		$this->dao->setOAI($this);
	}


	//
	// Implement template methods from OAI
	//
	/**
	 * @see OAI::metadataFormats()
	 */
	function metadataFormats($namesOnly = false, $identifier = null) {
		if (!(is_null($identifier) || $this->validIdentifier($identifier))) return array();

		// The OA-S OAI interface must support Dublin Core format
		// as well as the OA-S custom ContextObject format.
		if ($namesOnly) {
			return array('oai_dc', 'ctxo');
		} else {
			return array(
				'oai_dc' => new OasOAIMetadataFormat_DC(),
				'ctxo' => new OasOAIMetadataFormat_OAS()
			);
		}
	}

	/**
	 * @see OAI::validIdentifier()
	 */
	function validIdentifier($identifier) {
		return $this->identifierToEventId($identifier) !== false;
	}

	/**
	 * @see OAI::identifierExists()
	 */
	function identifierExists($identifier) {
		$recordExists = false;
		$eventId = $this->identifierToEventId($identifier);
		if ($eventId) {
			$recordExists = $this->dao->recordExists($eventId);
		}
		return $recordExists;
	}

	/**
	 * @see OAI::resumptionToken()
	 *
	 * TODO: Why is this not in the OAI base class? This
	 * only depends on methods implemented in PKPOAIDAO.
	 */
	function &resumptionToken($tokenId) {
		$this->dao->clearTokens();
		$token = $this->dao->getToken($tokenId);
		if (!isset($token)) {
			$token = false;
		}
		return $token;
	}

	/**
	 * @see OAI::saveResumptionToken()
	 *
	 * TODO: Why is this not in the OAI base class?
	 */
	function &saveResumptionToken($offset, $params) {
		$token = new OAIResumptionToken(null, $offset, $params, time() + $this->config->tokenLifetime);
		$this->dao->insertToken($token);
		return $token;
	}

	/**
	 * @see OAI::repositoryInfo()
	 */
	function &repositoryInfo() {
		$info = new OAIRepository();
		$info->repositoryName = $this->site->getLocalizedTitle();
		$info->adminEmail = $this->site->getLocalizedContactEmail();

		$info->sampleIdentifier = $this->eventIdToIdentifier(1);
		$info->earliestDatestamp = $this->dao->getEarliestDatestamp();

		$info->toolkitTitle = 'Open Journal Systems - OA-S plugin';
		$versionDao =& DAORegistry::getDAO('VersionDAO');
		$currentVersion =& $versionDao->getCurrentVersion('plugins.generic', 'oas', true);
		$info->toolkitVersion = $currentVersion->getVersionString();
		$info->toolkitURL = 'http://pkp.sfu.ca/ojs/';

		return $info;
	}

	/**
	 * @see OAI::identifiers()
	 */
	function &identifiers($metadataPrefix, $from, $until, $set, $offset, $limit, &$total) {
		return $this->dao->getIdentifiers(array(), $from, $until, $set, $offset, $limit, $total);
	}

	/**
	 * @see OAI::records()
	 */
	function &records($metadataPrefix, $from, $until, $set, $offset, $limit, &$total) {
		return $this->dao->getRecords(array(), $from, $until, $set, $offset, $limit, $total);
	}

	/**
	 * @see OAI::record()
	 */
	function &record($identifier) {
		$record = false;
		$eventId = $this->identifierToEventId($identifier);
		if ($eventId) {
			$record =& $this->dao->getRecord($eventId);
		}
		return $record;
	}


	//
	// Public methods.
	//
	/**
	 * Convert usage event ID to OAI identifier.
	 * @param $eventId int
	 * @return string
	 */
	function eventIdToIdentifier($eventId) {
		return $this->_getOaiPrefix() . $eventId;
	}

	/**
	 * Convert OAI identifier to usage event ID.
	 * @param $identifier string
	 * @return int
	 */
	function identifierToEventId($identifier) {
		$prefix = $this->_getOaiPrefix();
		if (strstr($identifier, $prefix)) {
			return (int) str_replace($prefix, '', $identifier);
		} else {
			return false;
		}
	}


	//
	// Private helper methods.
	//
	function _getOaiPrefix() {
		return 'oai:' . $this->config->repositoryId . ':';
	}
}

?>
