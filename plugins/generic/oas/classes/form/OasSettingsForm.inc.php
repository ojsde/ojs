<?php

/**
 * @file plugins/generic/oas/classes/form/OasSettingsForm.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasSettingsForm
 * @ingroup plugins_generic_oas_classes_form
 *
 * @brief Form to configure the OA-S plug-in.
 */


import('lib.pkp.classes.form.Form');

// These are the first few letters of an md5 of '##placeholder##'.
define('OAS_PLUGIN_PASSWORD_PLACEHOLDER', '##5ca39841ab##');

class OasSettingsForm extends Form {

	/** @var $_plugin OasPlugin */
	var $_plugin;

	/**
	 * Constructor
	 * @param $plugin OasPlugin
	 */
	function OasSettingsForm(&$plugin) {
		$this->_plugin =& $plugin;
		parent::Form($plugin->getTemplatePath() . 'settingsForm.tpl');

		// SALT server configuration.
		// The username is used in HTTP basic authentication and according to RFC2617 it therefore may not contain a colon.
		$this->addCheck(new FormValidatorRegExp($this, 'saltApiUsername', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.oas.settings.saltApiUsernameRequired', '/^[^:]+$/'));
		$this->addCheck(new FormValidator($this, 'saltApiPassword', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.oas.settings.saltApiPasswordRequired'));

		// OAI password.
		$this->addCheck(new FormValidator($this, 'oaiPassword', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.oas.settings.oaiPasswordRequired'));
	}


	//
	// Implement template methods from Form.
	//
	/**
	 * @see Form::initData()
	 */
	function initData() {
		$plugin =& $this->_plugin;
		foreach ($this->_getFormFields() as $fieldName) {
			$this->setData($fieldName, $plugin->getSetting(0, $fieldName));
		}
		// We do not echo back real passwords.
		$this->setData('saltApiPassword', OAS_PLUGIN_PASSWORD_PLACEHOLDER);
		$this->setData('oaiPassword', OAS_PLUGIN_PASSWORD_PLACEHOLDER);
	}

	/**
	 * @see Form::readInputData()
	 */
	function readInputData() {
		// Read regular form data.
		$this->readUserVars($this->_getFormFields());
		$request = PKPApplication::getRequest();
		$plugin =& $this->_plugin;

		// Set the passwords to the ones saved in the DB
		// if we only got a placeholder from the form.
		$saltApiPassword = $request->getUserVar('saltApiPassword');
		if ($saltApiPassword === OAS_PLUGIN_PASSWORD_PLACEHOLDER) {
			$saltApiPassword = $plugin->getSetting(0, 'saltApiPassword');
		}
		$this->setData('saltApiPassword', $saltApiPassword);
		$oaiPassword = $request->getUserVar('oaiPassword');
		if ($oaiPassword === OAS_PLUGIN_PASSWORD_PLACEHOLDER) {
			$oaiPassword = $plugin->getSetting(0, 'oaiPassword');
		}
		$this->setData('oaiPassword', $oaiPassword);
	}

	/**
	 * @see Form::execute()
	 */
	function execute() {
		$plugin =& $this->_plugin;
		$formFields = $this->_getFormFields();
		$formFields[] = 'saltApiPassword';
		$formFields[] = 'oaiPassword';
		foreach($formFields as $formField) {
			$plugin->updateSetting(0, $formField, $this->getData($formField), 'string');
		}
	}


	//
	// Private helper methods
	//
	/**
	 * Return the field names of this form.
	 * @return array
	 */
	function _getFormFields() {
		return array(
			'saltApiUsername', 'privacyMessage'
		);
	}
}

?>