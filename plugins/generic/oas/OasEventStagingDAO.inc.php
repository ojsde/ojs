<?php


/**
 * @file plugins/generic/oas/OasEventStagingDAO.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasEventStagingDAO
 * @ingroup plugins_generic_oas
 *
 * @brief Class for temporary staging of OA-S context objects before they
 *  are transferred to the OA-S service provider.
 *
 *  We extend the OAI DAO base class so that we have core OAI features
 *  available which we need for event log transmission via OAI-PMH.
 */

import('lib.pkp.classes.oai.PKPOAIDAO');

class OasEventStagingDAO extends PKPOAIDAO {

	/**
	 * Constructor
	 */
	function OasEventStagingDAO() {
		parent::PKPOAIDAO();
	}


	//
	// Implement template methods from PKPOAIDAO.
	//
	/**
	 * @see PKPOAIDAO::getRecordSelectStatement()
	 *
	 * Comments:
	 * - Records must contain a 'last_modified' column which represents the
	 *   timestamp of the record.
	 */
	function getRecordSelectStatement() {
		return 'SELECT es.timestamp as last_modified, es.*';
	}

	/**
	 * @see PKPOAIDAO::getRecordJoinClause()
	 *
	 * Comments:
	 * - All data comes from a single table.
	 */
	function getRecordJoinClause($eventId = null, $setIds = array(), $set = null) {
		return 'INNER JOIN oas_event_staging es ON (m.i = 0' . (isset($eventId) ? ' AND es.event_id = ?' : '') . ')';
	}

	/**
	 * @see PKPOAIDAO::getAccessibleRecordWhereClause()
	 *
	 * Comments:
	 * - We do not filter event logs at all when returning them. Event log
	 *   maintenance is done by deleting old records automatically.
	 * - We have to filter on the "mutex" table (alias: "m") that's used to
	 *   simulate UNION ALL statements to avoid a Cartesian join.
	 */
	function getAccessibleRecordWhereClause() {
		return '';
	}

	/**
	 * @see PKPOAIDAO::getDateRangeWhereClause()
	 *
	 * Comments:
	 * - $from and $until are matched directly against the event timestamp in
	 *   the database.
	 * - $from and $until are not necessarily set. When not set the earliest
	 *   (latest) possible point in time will be assumed, i.e. the event
	 *   list will not be limited in the beginning (end).
	 * - We order by event ID as this is a unique order and fast (indexed). We
	 *   need an order so that $offset and $limit designate a stable and
	 *   unique partition of the data.
	 */
	function getDateRangeWhereClause($from, $until) {
		return (isset($from) ? ' AND es.timestamp >= '. $this->datetimeToDB($from) : '')
			. (isset($until) ? ' AND es.timestamp <= ' . $this->datetimeToDB($until) : '')
			. ' ORDER BY es.event_id';
	}

	/**
	 * @see PKPOAIDAO::setOAIData()
	 *
	 * Comments:
	 * - The returned record identifier consists of the identifier prefix +
	 *   the auto-incremented event record ID from the database.
	 */
	function &setOAIData(&$record, $row, $isRecord = true) {
		$record->identifier = $this->oai->eventIdToIdentifier($row['event_id']);
		$record->sets = array();
		$row['ref_ids'] = $this->convertFromDB($row['ref_ids'], 'object');
		$row['srvtype_schsvc'] = $this->convertFromDB($row['srvtype_schsvc'], 'object');
		$record->data = $row;
		return $record;
	}

	/**
	 * @see PKPOAIDAO::getEarliestDatestamp()
	 */
	function getEarliestDatestamp($setIds = array()) {
		return parent::getEarliestDatestamp('SELECT	MIN(es.timestamp)', $setIds);
	}


	//
	// Public methods.
	//
	/**
	* Log a new usage event to the staging table.
	*
	* @param $usageEvent array
	* @param $salt string A SALT value for IP hashing.
	*
	* @return integer|null The ID of the new database entry or null if something
	*  went wrong (e.g. when a valid SALT was not provided).
	*/
	function stageUsageEvent($usageEvent, $salt) {
		// If the salt is empty then we're not allowed to save anything.
		if (empty($salt)) return null;

		// We currently only use 'administration' classification. TODO: Add more if we actually use them.
		$validClassifiers = array(OAS_PLUGIN_CLASSIFICATION_ADMIN);
		$identifiers = is_array($usageEvent['identifiers']) ? $usageEvent['identifiers'] : array();

		// Set the service type (if any). See: http://www.openurl.info/registry/docs/xsd/info:ofi/fmt:xml:xsd:sch_svc
		$serviceTypes = array();
		switch($usageEvent['assocType']) {
			case ASSOC_TYPE_ARTICLE:
				$serviceTypes[] = 'abstract';
				break;

			case ASSOC_TYPE_GALLEY:
				$serviceTypes[] = 'fulltext';
				break;
		}

		// Has the IP. We do this here so that it will be impossible to
		// store non-hashed IPs which would be a privacy legislation
		// violation under German law without explicit user consent.
		$hashedIp = $this->_hashIp($usageEvent['ip'], $salt);
		$hashedC = $this->_hashIp($this->_getCClassNet($usageEvent['ip']), $salt);

		// Never store unhashed IPs!
		if ($hashedIp === false || $hashedC === false) return false;

		$this->update(
			sprintf(
				'INSERT INTO oas_event_staging
					(timestamp, admin_size, admin_document_size, admin_format, admin_service,
					  ref_ids, ref_ent_id, requ_document_url, requ_hashed_ip, requ_hashed_c,
					  requ_hostname, requ_classification, requ_user_agent, srvtype_schsvc)
				 VALUES
					(%s, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $this->datetimeToDB($usageEvent['time'])
			),
			array(
				(int) ($usageEvent['downloadSuccess'] ? $usageEvent['docSize'] : 0),
				(int) $usageEvent['docSize'],
				$usageEvent['mimeType'],
				$usageEvent['serviceUri'],
				$this->convertToDB($identifiers, $type = 'object'),
				$usageEvent['referrer'],
				$usageEvent['canonicalUrl'],
				$hashedIp,
				$hashedC,
				$usageEvent['host'],
				(in_array($usageEvent['classification'], $validClassifiers) ? $usageEvent['classification'] : null),
				$usageEvent['userAgent'],
				$this->convertToDB($serviceTypes, $type = 'object')
			)
		);
		return $this->_getInsertId('oas_event_staging', 'event_id');
	}

	/**
	* Update the download success flag of a usage
	* event.
	*
	* @param $usageEventId integer
	* @param $downloadSuccess boolean
	*/
	function setDownloadSuccess($usageEventId, $downloadSuccess) {
		// We simulate download success by setting the downloaded size to
		// the document size. This signals to OA-S that the event
		// should be considered a successful download. If the download
		// was not successful we simply leave the field as initialized (i.e.
		// size = 0) which signals to OA-S that the event should be
		// considered an aborted download.
		if ($downloadSuccess) {
		$this->update(
			'UPDATE oas_event_staging SET admin_size = admin_document_size
				WHERE event_id = ?', (int)$usageEventId);
		}
	}

	/**
	 * Delete usage events older than OAS_PLUGIN_MAX_STAGING_TIME.
	 * @param $deleteOlderThan integer A Unix timestamp.
	 */
	function clearExpiredEvents($deleteOlderThan) {
		$deleteOlderThan = Core::getCurrentDate($deleteOlderThan);
		$this->update(
			sprintf(
				'DELETE FROM oas_event_staging WHERE timestamp < %s',
				$this->dateToDB($deleteOlderThan)
			)
		);
	}


	//
	// Private helper methods.
	//
	/**
	 * Hash (SHA256) the given IP using the given SALT.
	 *
	 * NB: This implementation was taken from OA-S directly. See
	 * http://sourceforge.net/p/openaccessstati/code-0/3/tree/trunk/logfile-parser/lib/logutils.php
	 * We just do not implement the PHP4 part as OJS dropped PHP4 support.
	 *
	 * @param $ip string
	 * @param $salt string
	 * @return string|boolean The hashed IP or boolean false if something went wrong.
	 */
	function _hashIp($ip, $salt) {
		if(function_exists('mhash')) {
			return bin2hex(mhash(MHASH_SHA256, $ip.$salt));
		} else {
			assert(function_exists('hash'));
			if (!function_exists('hash')) return false;
			return hash('sha256', $ip.$salt);
		}
	}

	/**
	* Get "C-class" of an IP adress, i.e. the first three bytes
	*
	* NB: This implementation was taken from OA-S directly. See
	* http://sourceforge.net/p/openaccessstati/code-0/3/tree/trunk/logfile-parser/lib/oasparser.php
	*
	* @param $ip The IP to shorten.
	* @return string C-class formatted as xxx.xxx.xxx.0.
	*/
	function _getCClassNet($ip) {
		return preg_replace('/^([0-9]+\.[0-9]+\.[0-9]+)\.[0-9]+$/','\1.0', $ip);
	}
}

?>
