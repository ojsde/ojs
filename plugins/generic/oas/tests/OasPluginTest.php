<?php

/**
 * @file plugins/generic/oas/tests/OasPluginTest.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasPluginTest
 * @ingroup plugins_generic_oas_tests
 * @see OasPlugin
 *
 * @brief Test class for the OasPlugin class
 */


import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.core.PKPRouter');
import('plugins.generic.oas.OasPlugin');

/**
 * @covers OasPlugin
 */
class OasPluginTest extends DatabaseTestCase {

	//
	// Implementing protected template methods from DatabaseTestCase
	//
	/**
	 * @see DatabaseTestCase::getAffectedTables()
	 */
	protected function getAffectedTables() {
		return array('plugin_settings', 'oas_event_staging');
	}


	//
	// Implementing protected template methods from PKPTestCase
	//
	/**
	 * @see PKPTestCase::getMockedRegistryKeys()
	 */
	protected function getMockedRegistryKeys() {
		$mockedRegistryKeys = parent::getMockedRegistryKeys();
		$mockedRegistryKeys[] = 'templateManager';
		return $mockedRegistryKeys;
	}

	/**
	 * @see PKPTestCase::setUp()
	 */
	protected function setUp() {
		parent::setUp();
		$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		$pluginSettingsDao->updateSetting(0, 'oasplugin', 'enabled', '1');
	}


	//
	// Unit tests
	//
	public function testStartUsageEvent() {
		$articleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$suppFileDao = DAORegistry::getDAO('SuppFileDAO');
		$issueGalleyDao = DAORegistry::getDAO('IssueGalleyDAO');
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$userDao = DAORegistry::getDAO('UserDAO');

		// Fake request.
		$_SERVER['REMOTE_ADDR'] = '189.122.65.110';
		$_SERVER['REMOTE_HOST'] = 'test.cedis.de';
		$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit test agent';
		$_SERVER['HTTP_REFERER'] = 'http://referer.cedis.de/';
		$request = $this->mockRequest('test/article/view/1', 1);

		// Fake template display event.
		$hookname = 'TemplateManager::display';
		$article = $articleDao->getPublishedArticleByArticleId(1);
		Registry::delete('templateManager');
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('article', $article);
		$args = array($templateMgr);

		// Test article abstract event (as administrative event).
		import('classes.security.Role');
		$expectedEvent = array(
			'pubObject' => $article,
			'assocType' => ASSOC_TYPE_ARTICLE,
			'canonicalUrl' => Config::getVar('general', 'base_url') . '/index.php/test/article/view/1',
			'mimeType' => 'text/html',
			'identifiers' => array(
				'other::ojs' => 'ojs:513ccb12cf3a7-j1-a1',
				'doi' => '10.1234/t.v1i1.1',
				'other::urn' => 'urn:nbn:de:0000-t.v1i1.18'
			),
			'docSize' => 0,
			'downloadSuccess' => true,
			'serviceUri' => Config::getVar('general', 'base_url') . '/index.php/test',
			'ip' => '189.122.65.110',
			'host' => 'test.cedis.de',
			'user' => $userDao->getById(1),
			'roles' => array(ROLE_ID_SITE_ADMIN, ROLE_ID_JOURNAL_MANAGER, ROLE_ID_EDITOR, ROLE_ID_AUTHOR),
			'userAgent' => 'PHPUnit test agent',
			'referrer' => 'http://referer.cedis.de/',
			'classification' => 'administrative'
		);
		$oasPlugin = $this->getOasPlugin($expectedEvent); // Must be called after mocking the request.
		$this->assertFalse($oasPlugin->startUsageEvent($hookname, $args));

		// Test HTML galley event (as bot event).
		$_SERVER['HTTP_USER_AGENT'] = 'GoogleBot';
		$request = $this->mockRequest('test/article/view/1/3');
		$galley = $articleGalleyDao->getGalley(3, 1);
		Registry::delete('templateManager');
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('article', $article);
		$templateMgr->assign('galley', $galley);
		$args = array($templateMgr);
		$expectedEvent['pubObject'] = $galley;
		$expectedEvent['assocType'] = ASSOC_TYPE_GALLEY;
		$expectedEvent['canonicalUrl'] .= '/3';
		$expectedEvent['docSize'] = 4170;
		$expectedEvent['identifiers'] = array(
			'other::ojs' => 'ojs:513ccb12cf3a7-j1-a1-g3',
			'doi' => '10.1234/t.v1i1.1.g3',
			'other::urn' => 'urn:nbn:de:0000-t.v1i1.1.g36'
		);
		$expectedEvent['user'] = null; // This is now an anonymous request.
		$expectedEvent['roles'] = array();
		$expectedEvent['userAgent'] = 'GoogleBot';
		$expectedEvent['classification'] = 'bot';
		$oasPlugin = $this->getOasPlugin($expectedEvent);
		$this->assertFalse($oasPlugin->startUsageEvent($hookname, $args));

		// Test that viewing a galley page that is not an HTML galley will not
		// log an event.
		$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit test agent';
		$request = $this->mockRequest('test/article/view/1/2');
		$galley = $articleGalleyDao->getGalley(2, 1);
		Registry::delete('templateManager');
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('article', $article);
		$templateMgr->assign('galley', $galley);
		$args = array($templateMgr);
		$oasPlugin = $this->getOasPlugin(false); // false = no event should be generated.
		$this->assertFalse($oasPlugin->startUsageEvent($hookname, $args));

		// Test PDF galley event.
		$hookname = 'ArticleHandler::viewFile';
		$args = array($article, $galley);
		$expectedEvent['pubObject'] = $galley;
		$expectedEvent['canonicalUrl'] = 'http://localhost/pkp-ojs/index.php/test/article/download/1/2';
		$expectedEvent['mimeType'] = 'application/pdf';
		$expectedEvent['identifiers'] = array(
			'other::ojs' => 'ojs:513ccb12cf3a7-j1-a1-g2',
			'doi' => '10.1234/t.v1i1.1.g2',
			'other::urn' => 'urn:nbn:de:0000-t.v1i1.1.g20'
		);
		$expectedEvent['docSize'] = 15951;
		$expectedEvent['downloadSuccess'] = false;
		$expectedEvent['userAgent'] = 'PHPUnit test agent';
		$expectedEvent['classification'] = null;
		$oasPlugin = $this->getOasPlugin($expectedEvent);
		$this->assertFalse($oasPlugin->startUsageEvent($hookname, $args));

		// Test supplementary file event.
		$hookname = 'ArticleHandler::downloadSuppFile';
		$request = $this->mockRequest('/test/article/downloadSuppFile/1/1');
		$suppFile = $suppFileDao->getSuppFile(1, 1);
		$args = array($article, $suppFile);
		$expectedEvent['pubObject'] = $suppFile;
		$expectedEvent['assocType'] = ASSOC_TYPE_SUPP_FILE;
		$expectedEvent['canonicalUrl'] = 'http://localhost/pkp-ojs/index.php/test/article/downloadSuppFile/1/1';
		$expectedEvent['identifiers'] = array(
			'other::ojs' => 'ojs:513ccb12cf3a7-j1-a1-s1',
			'doi' => '10.1234/t.v1i1.1.s1',
			'other::urn' => 'urn:nbn:de:0000-t.v1i1.1.s19'
		);
		$oasPlugin = $this->getOasPlugin($expectedEvent);
		$this->assertFalse($oasPlugin->startUsageEvent($hookname, $args));

		// Test issue galley event.
		$hookname = 'IssueHandler::viewFile';
		$request = $this->mockRequest('/test/issue/viewIssue/1/1');
		$issue = $issueDao->getIssueById(1);
		$issueGalley = $issueGalleyDao->getGalley(1, 1);
		$args = array($issue, $issueGalley);
		$expectedEvent['pubObject'] = $issueGalley;
		$expectedEvent['assocType'] = ASSOC_TYPE_ISSUE_GALLEY;
		$expectedEvent['canonicalUrl'] = 'http://localhost/pkp-ojs/index.php/test/issue/download/1/1';
		$expectedEvent['identifiers'] = array(
			'other::ojs' => 'ojs:513ccb12cf3a7-j1-i1-ig1'
		);
		$expectedEvent['mimeType'] = 'application/octet-stream';
		$oasPlugin = $this->getOasPlugin($expectedEvent);
		$this->assertFalse($oasPlugin->startUsageEvent($hookname, $args));
	}

	public function testPrivacyInfo() {
		$articleDao = DAORegistry::getDAO('PublishedArticleDAO'); /* @var $articleDao PublishedArticleDAO */
		$article = $articleDao->getPublishedArticleByArticleId(1);

		// Test with a page that collects OA-S statistics.
		$hookname = 'TemplateManager::display';
		$request = $this->mockRequest('test/article/view/1');
		Registry::delete('templateManager');
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('article', $article);
		$args = array($templateMgr);
		$oasPlugin = $this->getOasPlugin(); // Must be called after mocking the request.
		$oasPlugin->startUsageEvent($hookname, $args);
		$this->assertTrue($templateMgr->get_template_vars('oasDisplayPrivacyInfo'));

		// Test with a page that doesn't collect OA-S statistics.
		$request = $this->mockRequest('test/other-page/other-op');
		Registry::delete('templateManager');
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('article', $article);
		$args = array($templateMgr);
		$oasPlugin = $this->getOasPlugin();
		$oasPlugin->startUsageEvent($hookname, $args);
		$this->assertNull($templateMgr->get_template_vars('oasDisplayPrivacyInfo'));
	}

	public function testDeletionOfExpiredLogEntries() {
		$articleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$article = $articleDao->getPublishedArticleByArticleId(1);

		// Simulate a (just) expired event.
		$expiredEvent = array(
				'time' => Core::getCurrentDate(time() - OAS_PLUGIN_MAX_STAGING_TIME * 60 - 1),
				'pubObject' => $article,
				'assocType' => ASSOC_TYPE_ARTICLE,
				'canonicalUrl' => Config::getVar('general', 'base_url') . '/index.php/test/article/view/1',
				'mimeType' => 'text/html',
				'identifiers' => array('other::ojs' => 'ojs:513ccb12cf3a7-j1-a1'),
				'docSize' => 0,
				'downloadSuccess' => true,
				'serviceUri' => Config::getVar('general', 'base_url') . '/index.php/test',
				'ip' => '127.0.0.1',
				'host' => null,
				'user' => null,
				'roles' => array(),
				'userAgent' => 'PHPUnit test agent',
				'referrer' => null,
				'classification' => null
		);
		import('plugins.generic.oas.OasEventStagingDAO');
		$eventDao = new OasEventStagingDAO();
		$eventIds = array();
		$eventIds[] = $eventDao->stageUsageEvent($expiredEvent, 'fakeSalt');

		// Simulate a not-yet expired event.
		$activeEvent = $expiredEvent;
		$activeEvent['time'] = Core::getCurrentDate(time() - OAS_PLUGIN_MAX_STAGING_TIME * 60 + 5);
		$eventIds[] = $eventDao->stageUsageEvent($activeEvent, 'fakeSalt');

		// Make sure that both events have actually been persisted.
		$result = $eventDao->retrieve(
				'SELECT COUNT(*) AS cnt FROM oas_event_staging WHERE event_id IN (?, ?)',
				$eventIds
			)->FetchRow();
		$this->assertEquals('2', $result['cnt']);

		// Trigger event log maintenance through a new event.
		$request = $this->mockRequest('test/article/view/1', 1);
		$hookname = 'TemplateManager::display';
		Registry::delete('templateManager');
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('article', $article);
		$args = array($templateMgr);
		$oasPlugin = $this->getOasPlugin();
		$oasPlugin->startUsageEvent($hookname, $args);

		// Only the non-expired event should be left in the database.
		$result = $eventDao->retrieve(
				'SELECT COUNT(*) AS cnt FROM oas_event_staging WHERE event_id IN (?, ?)',
				$eventIds
			)->FetchRow();
		$this->assertEquals('1', $result['cnt']);
	}


	//
	// Private helper methods.
	//
	/**
	 * Instantiate the plug-in for testing.
	 *
	 * @return OasPlugin
	 */
	private function getOasPlugin($expectedEvent = null) {
		PluginRegistry::loadCategory('generic', true, 0);
		$oasPlugin = PluginRegistry::getPlugin('generic', 'oasplugin'); /* @var $oasPlugin OasPlugin */
		$oasPlugin->import('OasEventStagingDAO');
		if (is_null($expectedEvent)) {
			$oasPlugin->_oasEventStagingDao = null;
		} else {
			$mockEventStagingDao = $this->getMock('OasEventStagingDAO');
			if ($expectedEvent === false) {
				$mockEventStagingDao
					->expects($this->never())
					->method('stageUsageEvent');
			} else {
				$mockEventStagingDao
					->expects($this->once())
					->method('stageUsageEvent')
					->with($this->usageEvent($expectedEvent));
			}
			$oasPlugin->_oasEventStagingDao = $mockEventStagingDao;
		}
		$nullVar = null;
		$oasPlugin->request =& $nullVar;
		return $oasPlugin;
	}

	/**
	 * Instantiate a usage event constraint.
	 *
	 * @param $expectedEvent array
	 * @return UsageEventConstraint
	 */
	private function usageEvent($expectedEvent) {
		return new UsageEventConstraint($expectedEvent);
	}
}

/**
 * A custom constraint to evaluate usage events.
 */
class UsageEventConstraint extends PHPUnit_Framework_Constraint_IsEqual {
	public function evaluate($other, $description = '', $returnResult = FALSE) {
		if (!(is_array($other) && array_key_exists('time', $other) &&
				String::regexp_match('/[0-9]{4}(-[0-9]{2}){2} [0-9]{2}(:[0-9]{2}){2}/', $other['time']))) {
			if ($returnResult) return false;
			throw new PHPUnit_Framework_ExpectationFailedException('Usage event: Invalid timestamp');
		}
		unset($other['time']);
		return parent::evaluate($other, $description, $returnResult);
	}
};
?>