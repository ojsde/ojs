<?php

/**
 * @file plugins/generic/oas/OasHandler.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasHandler
 * @ingroup plugins_generic_oas
 *
 * @brief Handle OA-S page requests (opt-out, privacy information, etc.)
 */

import('classes.handler.Handler');

define('OAS_OAI_USERNAME', 'ojs-oas'); // We use a fixed username to reduce configuration requirements.

class OasHandler extends Handler {

	/**
	 * Constructor
	 */
	function OasHandler() {
		parent::Handler();
	}


	//
	// Public operations
	//
	/**
	 * Show a page with privacy information and an
	 * opt-out option.
	 *
	 * @param $args array
	 * @param $request Request
	 */
	function privacyInformation($args, $request) {
		$this->validate(null, $request);

		// Check whether this is an opt-out request.
		if ($request->isPost()) {
			if ($request->getUserVar('opt-out')) {
				// Set a cookie that is valid for one year.
				$request->setCookieVar('oas-opt-out', true, time() + 60*60*24*365);
			}
			if ($request->getUserVar('opt-in')) {
				// Delete the opt-out cookie.
				$request->setCookieVar('oas-opt-out', false, time() - 60*60);
			}
		}

		// Display the privacy info page.
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pageTitle', 'plugins.generic.oas.optout.title');
		$templateMgr->assign('oasDisplayPrivacyInfo', true);
		$templateMgr->assign('hasOptedOut', ($request->getCookieVar('oas-opt-out') ? true : false));
		$plugin = $this->_getPlugin();
		$templateMgr->display($plugin->getTemplatePath().'privacyInformation.tpl');
	}

	/**
	 * The OAI endpoint for OA-S event log data export.
	 *
	 * @param $args array
	 * @param $request Request
	 */
	function oai($args, $request) {
		// Only enable the interface after a password has been set.
		$plugin = $this->_getPlugin();
		$oaiPassword = $plugin->getSetting(0, 'oaiPassword');
		if (empty($oaiPassword)) {
			$authorized = false;
			echo 'Please configure an OAI password before trying to ' .
				'access the OJS OA-S OAI interface.';
			exit;
		}

		// Authorization.
		$oaiUsername = OAS_OAI_USERNAME;
		if (!isset($_SERVER['PHP_AUTH_USER']) ||
				$_SERVER['PHP_AUTH_USER'] !== $oaiUsername ||
				$_SERVER['PHP_AUTH_PW'] !== $oaiPassword) {
			header('WWW-Authenticate: Basic realm="OJS OA-S OAI"');
			header('HTTP/1.0 401 Unauthorized');
			echo 'Access denied.';
			exit;
		}

		// Authorized OAI access.
		$plugin = $this->_getPlugin();
		$plugin->import('classes/oai/OasOAI');
		$oai = new OasOAI($request);
		$oai->execute();
	}


	//
	// Private helper methods
	//
	/**
	 * Get the OA-S plugin object
	 * @return OasPlugin
	 */
	function &_getPlugin() {
		$plugin =& PluginRegistry::getPlugin('generic', OAS_PLUGIN_NAME);
		return $plugin;
	}
}

?>
