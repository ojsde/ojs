<?php
/**
 * @defgroup plugins_generic_oas_classes_task
 */

/**
 * @file plugins/generic/oas/classes/task/OasFileLoader.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasFileLoader
 * @ingroup plugins_generic_oas_classes_task
 *
 * @brief Scheduled task to load statistics files from the OA-S server.
 */

import('lib.pkp.classes.task.FileLoader');


class OasFileLoader extends FileLoader {

	private $_siteId;

	/**
	 * Constructor
	 */
	function OasFileLoader() {
		// Determine the base folder.
		$baseFolder = rtrim(Config::getVar('files', 'files_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'oas';
		$args = array($baseFolder);
		parent::FileLoader($args);

		// Get the installation ID.
		$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		$this->_siteId = $pluginSettingsDao->getSetting(0, 'OasPlugin', 'uniqueSiteId');
	}

	//
	// Implement template methods from FileLoader
	//
	/**
	 * @see FileLoader::processFile()
	 */
	public function processFile($filePath) {
		$handle = fopen($filePath, 'r');
		if ($handle === false) throw new Exception('Error while opening file "' . $filePath . '".');

		// Delete entries from previous loads in the metrics table (if any).
		$metricsDao = DAORegistry::getDAO('MetricsDAO'); /* @var $metricsDao MetricsDAO */
		$loadId = basename($filePath);
		$metricsDao->purgeLoadBatch($loadId);

		// (Re-)Load the file.
		$headerLine = fgetcsv($handle, 0, ';');
		$id = 1;
		while ($nextLine = fgetcsv($handle, 0, ';')) {
			// Extract one record.
			$rawData = array_combine($headerLine, $nextLine);

			// Transform the record.
			$record = array('load_id' => $loadId);

			// Identifier lookup.
			$identifierParts = explode(':', $rawData['identifier']);

			// Validate the identifier format.
			if (!(count($identifierParts) == 2 && $identifierParts[0] == 'ojs')) {
				throw new Exception('Cannot load record: Invalid identifier.');
			}
			$identifierParts = explode('-', $identifierParts[1]);
			if (count($identifierParts) < 3) throw new Exception('Cannot load record: Invalid identifier.');

			// Validate the site ID.
			$siteId = array_shift($identifierParts);
			if (empty($siteId) || $siteId !== $this->_siteId) throw new Exception('Cannot load record: Invalid site ID.');

			// Validate and identify the publication object.
			$pubObjectId = array_pop($identifierParts);

			// Publication object type.
			$pubObjectType = null;
			if (String::regexp_match_get('/^[agis]{1,2}/', $pubObjectId, $pubObjectType) == 1) {
				$pubObjectType = $pubObjectType[0];
			} else {
				throw new Exception('Cannot load record: Unknown object type.');
			}
			if ($pubObjectType == 'a') {
				// A journal ID (which we ignore).
				$expectedSecondaryIds = 1;
			} else {
				// In the case of galleys and supp files also get an article/issue ID.
				$expectedSecondaryIds = 2;
			}
			if (count($identifierParts) !== $expectedSecondaryIds) throw new Exception('Cannot load record: Invalid publication object ID format.');
			switch($pubObjectType) {
				case 'a':
					$assocType = ASSOC_TYPE_ARTICLE;
					break;

				case 'g':
					$assocType = ASSOC_TYPE_GALLEY;
					break;

				case 's':
					$assocType = ASSOC_TYPE_SUPP_FILE;
					break;

				case 'ig':
					$assocType = ASSOC_TYPE_ISSUE_GALLEY;
					break;

				default:
					throw new Exception('Cannot load record: Unknown object type.');
			}
			$record['assoc_type'] = $assocType;

			// Publication object ID.
			$pubObjectId = substr($pubObjectId, strlen($pubObjectType));
			if (!is_numeric($pubObjectId)) throw new Exception('Cannot load record: Invalid publication object ID format.');
			$record['assoc_id'] = $pubObjectId;

			// Other dimensions.
			$record['day'] = str_replace('-', '', $rawData['date']);
			$record['metric_type'] = 'oas::counter';
			$record['metric'] = (int) $rawData['counter'];
			// TODO: find out when to load counter_abstract and when counter.
			// This can only be done when we get our test data back as the exact
			// semantics of these fields has not been specified by OA-S.

			// Load the record into the database.
			$metricsDao->insertRecord($record);
		}

		fclose($handle);
		return true;
	}
}

?>
