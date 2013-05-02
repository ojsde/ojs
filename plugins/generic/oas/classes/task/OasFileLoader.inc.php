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

	/** @var string */
	private $_siteId;

	/** @var PluginSettingsDAO */
	private $_pluginSettingsDao;

	/** @var boolean */
	private $_testMode;


	/**
	 * Constructor
	 */
	function OasFileLoader($testMode = false) {
		$this->_testMode = $testMode;

		// Determine the base folder.
		$baseFolder = rtrim(Config::getVar('files', 'files_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'oas';
		$args = array($baseFolder);
		parent::FileLoader($args);

		// Get the installation ID.
		$this->_pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		$this->_siteId = $this->_getSetting('uniqueSiteId');
	}


	//
	// Implement template methods from FileLoader
	//
	/**
	 * @see FileLoader::execute()
	 */
	public function execute() {
		// Make sure that the folder structure to handle file downloads is
		// in place.
		if (!$this->checkFolderStructure(true)) return false;

		// Download new files from the OA-S server.
		if (!($this->_testMode || $this->_stageStatisticsFiles())) return false;

		// Load the files into the database.
		return parent::execute();
	}


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
			if ($assocType == ASSOC_TYPE_ARTICLE) {
				$record['metric'] = (int) $rawData['counter_abstract'];
			} else {
				$record['metric'] = (int) $rawData['counter'];
			}

			// Load the record into the database.
			$metricsDao->insertRecord($record);
		}

		fclose($handle);
		return true;
	}


	//
	// Private helper methods.
	//
	/**
	 * Poll the OA-S server for new statistics files and
	 * download them to the staging folder.
	 * @return boolean
	 */
	function _stageStatisticsFiles() {
		$oasServerUrl = trim($this->_getSetting('oasServerUrl'), '/');
		$oasServerUsername = $this->_getSetting('oasServerUsername');
		$oasServerPassword = $this->_getSetting('oasServerPassword');
		if (empty($oasServerUrl) || empty($oasServerUsername) || empty($oasServerPassword)) return false;

		// Get the last successfully downloaded file.
		$oasServerLastDownloadedFile = $this->_getSetting('oasServerLastDownloadedFile');
		if (empty($oasServerLastDownloadedFile)) {
			// When this is the first load date then start with a date
			// one week ago.
			$lastDate = date('Y-m-d', strtotime('-7 days'));
		} else {
			// Extract the date from the file name.
			$lastDate = substr($oasServerLastDownloadedFile, 0, 10);
		}
		if (!String::regexp_match('/[0-9]{4}(-[0-9]{2}){2}/', $lastDate)) return false;
		$lastDateTs = strtotime($lastDate);

		// Try loading files up until today.
		$todayTs = strtotime(date('Y-m-d'));

		// Download files into the staging folder.
		$stagePath = $this->getStagePath();
		import('lib.pkp.classes.webservice.WebService');
		$ws = new WebService();
		$ws->setAuthUsername($oasServerUsername);
		$ws->setAuthPassword($oasServerPassword);
		import('lib.pkp.classes.webservice.WebServiceRequest');
		$currentDateTs = $lastDateTs;
		while ($currentDateTs < $todayTs) {
			$currentDateTs += 60 * 60 * 24;
			$year = date('Y', $currentDateTs);
			$month = date('m', $currentDateTs);
			$day = date('Y-m-d', $currentDateTs);
			$filename = "${day}_$day.csv";
			$url =  "$oasServerUrl/$year/$month/$filename";
			$wsReq = new WebServiceRequest($url);
			$wsReq->setAccept('text/plain');
			$csv = $ws->call($wsReq);
			if ($ws->getLastResponseStatus() == 200) {
				$targetPath = "$stagePath/$filename";
				if (file_put_contents($targetPath, $csv) === false) return false;
				$this->_updateSetting('oasServerLastDownloadedFile', $filename, 'string');
			} elseif ($ws->getLastResponseStatus() != 404) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get an OasPlugin setting.
	 * @param $settingName string
	 * @return mixed
	 */
	private function _getSetting($settingName) {
		if (!is_a($this->_pluginSettingsDao, 'PluginSettingsDAO')) return null;
		return $this->_pluginSettingsDao->getSetting(0, 'OasPlugin', $settingName);
	}

	/**
	 * Update an OasPlugin setting.
	 * @param $settingName string
	 * @param $settingValue mixed
	 * @param $settingType string
	 */
	private function _updateSetting($settingName, $settingValue, $settingType) {
		if (!is_a($this->_pluginSettingsDao, 'PluginSettingsDAO')) return null;
		return $this->_pluginSettingsDao->updateSetting(0, 'OasPlugin', $settingName, $settingValue, $settingType);
	}
}

?>
