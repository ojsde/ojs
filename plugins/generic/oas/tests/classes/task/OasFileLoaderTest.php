<?php

/**
 * @file plugins/generic/oas/tests/classes/task/OasFileLoaderTest.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasFileLoaderTest
 * @ingroup plugins_generic_oas_tests_classes_task
 * @see OasFileLoader
 *
 * @brief Test class for the OasFileLoader class
 */


import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.oas.classes.task.OasFileLoader');

/**
 * @covers OasFileLoader
 */
class OasFileLoaderTest extends DatabaseTestCase {

	//
	// Implementing protected template methods from DatabaseTestCase
	//
	/**
	 * @see DatabaseTestCase::getAffectedTables()
	 */
	protected function getAffectedTables() {
		return array('metrics');
	}


	//
	// Implementing protected template methods from PKPTestCase
	//
	/**
	 * @see PKPTestCase::tearDown()
	 */
	protected function tearDown() {
		$fileLoader = new OasFileLoader();
		$testFile =  DIRECTORY_SEPARATOR . '2012-11-10_2012-11-10.csv';
		@unlink($fileLoader->getStagePath() . $testFile);
		@unlink($fileLoader->getRejectPath() . $testFile);
		@unlink($fileLoader->getArchivePath() . $testFile);
		parent::tearDown();
	}


	//
	// Unit tests
	//
	public function testFileLoader() {
		$fileLoader = new OasFileLoader();

		// Install the processing folder structure if necessary.
		$fileLoader->checkFolderStructure(true);

		// Copy the test file to the hot folder.
		copy(
			dirname(__FILE__) . DIRECTORY_SEPARATOR . 'test.csv',
			$fileLoader->getStagePath() . DIRECTORY_SEPARATOR . '2012-11-10_2012-11-10.csv'
		);
		$fileLoader->execute();

		// Check that the file was correctly loaded.
		$expectedResult = array(
			array(ASSOC_TYPE_ARTICLE, 1, 20121110, 201211, 'oas::counter', '2012-11-10_2012-11-10.csv', 8, null, 1, 1, 1),
			array(ASSOC_TYPE_GALLEY, 2, 20121110, 201211, 'oas::counter', '2012-11-10_2012-11-10.csv', 12, null, 1, 1, 1),
			array(ASSOC_TYPE_GALLEY, 3, 20121110, 201211, 'oas::counter', '2012-11-10_2012-11-10.csv', 6, null, 1, 1, 1),
			array(ASSOC_TYPE_SUPP_FILE, 1, 20121110, 201211, 'oas::counter', '2012-11-10_2012-11-10.csv', 5, null, 1, 1, 1),
			array(ASSOC_TYPE_ISSUE_GALLEY, 1, 20121110, 201211, 'oas::counter', '2012-11-10_2012-11-10.csv', 10, null, 1, 1, null)
		);
		$metricDao = DAORegistry::getDAO('MetricsDAO');
		$result = $metricDao->retrieve(
				"SELECT COUNT(*) AS cnt FROM metrics WHERE load_id = '2012-11-10_2012-11-10.csv'"
			)->FetchRow();
		$this->assertEquals('5', $result['cnt']);
		foreach ($expectedResult as $expectedRecord) {
			$result = $metricDao->retrieve(
					"SELECT assoc_type, assoc_id, day, month, metric_type, load_id, metric, country_id, journal_id, issue_id, article_id
					 FROM metrics
					 WHERE load_id = '2012-11-10_2012-11-10.csv'
					   AND assoc_type = ? AND assoc_id = ?",
					array($expectedRecord[0], $expectedRecord[1])
				)->FetchRow();
			$this->assertNotEmpty($result);
			foreach ($expectedRecord as $index => $value) {
				$this->assertEquals($expectedRecord[$index], $result[$index], 'Error while testing assoc type ' . $expectedRecord[0] . ' and index ' . $index . '.');
			}
		}
	}
};
?>