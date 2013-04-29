<?php

/**
 * @file tests/functional/pages/search/FunctionalSearchSortingByMetricTest.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FunctionalSearchSortingByMetricTest
 * @ingroup tests_functional_pages_search
 * @see ArticleSearch
 *
 * @brief Integration/Functional test for the "sorting" and "sorting-by-metric"
 * features of the OJS search.
 *
 * FEATURE: search result set sorting
 */


import('tests.functional.pages.search.FunctionalSearchBaseTestCase');

class FunctionalSearchSortingTest extends FunctionalSearchBaseTestCase {

	//
	// Implement template methods from WebTestCase
	//
	/**
	 * @see WebTestCase::getAffectedTables()
	 */
	protected function getAffectedTables() {
		return array('metrics');
	}

	//
	// Implement template methods from PKPTestCase
	//
	/**
	 * @see PKPTestCase::setUp()
	 */
	protected function setUp() {
		parent::setUp();
		$pluginSettingsDao =& DAORegistry::getDAO('PluginSettingsDAO'); /* @var $pluginSettingsDao PluginSettingsDAO */
		$pluginSettingsDao->updateSetting(0, 'luceneplugin', 'enabled', false);
	}


	//
	// Tests
	//
	/**
	 * SCENARIO OUTLINE: Result ordering
	 *   GIVEN I am looking at the result page of a {search type}-journal
	 *         result set for the search phrase {keywords}
	 *    WHEN I select {order criterium} and {order direction}
	 *    THEN I will see a different result list re-ordered by the
	 *         changed criterium and in the given direction. This
	 *         can be seen by looking at the first {article id} in
	 *         the result set.
	 *
	 * EXAMPLES:
	 *   search type | keywords                    | order criterium  | order direction      | article id
	 *   ================================================================================================
	 *   single      | chicken AND (wings OR feet) | relevance        | descending (default) | 3
	 *   single      | chicken AND (wings OR feet) | relevance        | ascending            | 5
	 *   single      | chicken AND (wings OR feet) | author           | ascending (default)  | 4
	 *   single      | chicken AND (wings OR feet) | author           | descending           | 5
	 *   single      | chicken AND (wings OR feet) | publication date | descending (default) | 5
	 *   single      | chicken AND (wings OR feet) | article title    | ascending (default)  | 5
	 *   single      | chicken AND (wings OR feet) | article title    | descending           | 4
	 *   multi       | test NOT ranking            | issue publ. date | descending (default) | 3 or 4
	 *   multi       | test NOT ranking            | journal title    | ascending (default)  | 3
	 *   multi       | test NOT ranking            | journal title    | descending           | 1
	 *
	 * SCENARIO: Journal title ordering: single-journal search
	 *    WHEN I am doing a single-journal search
	 *    THEN I will not be able to order the result
	 *         set by journal title.
	 *
	 * SCENARIO: Journal title ordering: multi-journal search
	 *    WHEN I am doing a multi-journal search
	 *    THEN I can order the result set by journal
	 *         title.
	 */
	function testResultOrdering() {
		// Test ordering of a single-journal search.
		$singleJournalExamples = array(
			array('searchResultOrder', 'score', 3), // Default: descending
			array('searchResultOrderDir', 'asc', 5),
			array('searchResultOrder', 'authors', 4), // Default: ascending
			array('searchResultOrderDir', 'desc', 5),
			array('searchResultOrder', 'publicationDate', 5), // Default: descending
			array('searchResultOrder', 'title', 5), // Default: ascending
			array('searchResultOrderDir', 'desc', 4)
		);
		// Execute a query that produces a different score for all
		// articles in the result set.
		$this->simpleSearch('("test article" AND author) OR "chicken have wings" OR "authorname"');
		foreach($singleJournalExamples as $example) {
			$this->checkResultOrderingExample($example);
		}

		// Make sure that there is no journal-title ordering.
		$singleJournalOrderingOptions = $this->getSelectOptions('searchResultOrder');
		$this->assertFalse(in_array('Journal Title', $singleJournalOrderingOptions));


		// Test ordering of a multi-journal search.
		$multiJournalExamples = array(
			array('searchResultOrder', 'issuePublicationDate', array(3, 4)), // Default: descending
			array('searchResultOrder', 'journalTitle', 3), // Default: ascending
			array('searchResultOrderDir', 'desc', 1)
		);
		$this->simpleSearchAcrossJournals('test NOT ranking');
		foreach($multiJournalExamples as $example) {
			$this->checkResultOrderingExample($example);
		}

		// Make sure that journal-title ordering is allowed.
		$multiJournalOrderingOptions = $this->getSelectOptions('searchResultOrder');
		$this->assertTrue(in_array('Journal Title', $multiJournalOrderingOptions));
	}

	/**
	 * SCENARIO: sorting by metric effect
	 *   GIVEN I simulate a metrics table that establishes the following article
	 *         order by descending usage statistics:
	 *           article 4, article 3, article 2, article 1
	 *     AND I executed a search that does not order articles by metric
	 *         [e.g. 'article']
	 *    WHEN I select the "Popularity" order-by option
	 *    THEN the result will be re-ordered (default: descending order) by the
	 *         order established in the ranking file, i.e. article 4, 3, 2, 1.
	 * @group current
	 */
	function testSortingByMetric() {
		// Prepare the metrics table.
		// NB: We actually map the Gherkin article IDs to "real" article IDs
		// to make sure that the metrics DAO can handle them.
		$metricsDao = DAORegistry::getDAO('MetricsDAO'); /* @var $metricsDao MetricsDAO */
		$metricsDao->retrieve('TRUNCATE TABLE metrics');
		$record = array(
			'load_id' => 'functional test data',
			'assoc_type' => ASSOC_TYPE_ARTICLE,
			'day' => '20130415',
			'metric_type' => 'oas::counter'
		);
		$metric = 5;
		foreach (array(9, 10, 11, 12) as $articleId) {
			$record['assoc_id'] = $articleId;
			$record['metric'] = $metric;
			$metricsDao->insertRecord($record);
			$metric += 5;
		}

		// Make sure that we have an additional order-by option "Popularity".
		$this->simpleSearch('article');
		$this->waitForElementPresent('name=searchResultOrder');
		$this->assertSelectHasOption('name=searchResultOrder', 'Popularity');

		// If we sort by "Popularity" then we expect the result order to reverse.
		$this->selectAndWait('name=searchResultOrder', "value=popularity");
		$this->checkRanking(array(4, 3, 2, 1), false, 3);

		$metricsDao->purgeLoadBatch('functional test data');
		foreach (array(9, 10, 11, 12) as $articleId) {
			$record['assoc_id'] = $articleId;
			$record['metric'] = $metric;
			$metricsDao->insertRecord($record);
			$metric -= 5;
		}
		$this->refreshAndWait();
		$this->waitForElementPresent('name=searchResultOrder');
		$this->checkRanking(array(1, 2, 3, 4), false, 3);
	}
}
?>
