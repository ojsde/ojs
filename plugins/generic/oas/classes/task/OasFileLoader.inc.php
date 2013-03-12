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

	/**
	 * Constructor
	 */
	function OasFileLoader() {
		// Determine the base folder.
		$baseFolder = rtrim(Config::getVar('files', 'files_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'oas';
		$args = array($baseFolder);
		parent::FileLoader($args);
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
		$id = 0;
		while ($nextLine = fgetcsv($handle, 0, ';')) {
			// Extract one record.
			$rawData = array_combine($headerLine, $nextLine);

			// Transform the record.
			$record = array('load_id' => $loadId);
			$record['assoc_type'] = ASSOC_TYPE_ARTICLE; // TODO: dummy
			$record['assoc_id'] = $id++; // TODO: dummy
			$record['day'] = str_replace('-', '', $rawData['date']);
			$record['metric_type'] = 'oas::counter';
			$record['metric'] = (int) $rawData['counter'];
			// TODO: find out when to load counter_abstract and when counter.

			// Load the record into the database.
			$metricsDao->insertRecord($record);
		}

		fclose($handle);
		return true;
	}
}

?>
