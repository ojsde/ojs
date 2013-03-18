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

		// OAI password.
		$this->addCheck(new FormValidator($this, 'oaiPassword', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.oas.settings.oaiPasswordRequired'));

		// OA-S statistics server configuration.
		$this->addCheck(new FormValidatorUrl($this, 'oasServerUrl', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.oas.settings.oasServerUrlRequired'));
		// The username is used in HTTP basic authentication and according to RFC2617 it therefore may not contain a colon.
		$this->addCheck(new FormValidatorRegExp($this, 'oasServerUsername', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.oas.settings.oasServerUsernameRequired', '/^[^:]+$/'));
		$this->addCheck(new FormValidator($this, 'oasServerPassword', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.oas.settings.oasServerPasswordRequired'));
		
		// SALT server configuration.
		// The username is used in HTTP basic authentication.
		$this->addCheck(new FormValidatorRegExp($this, 'saltApiUsername', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.oas.settings.saltApiUsernameRequired', '/^[^:]+$/'));
		$this->addCheck(new FormValidator($this, 'saltApiPassword', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.oas.settings.saltApiPasswordRequired'));
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
		// We do not echo back passwords.
		foreach ($this->_getPasswordFields() as $fieldName) {
			$this->setData($fieldName, OAS_PLUGIN_PASSWORD_PLACEHOLDER);
		}
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
		foreach($this->_getPasswordFields() as $fieldName) {
			$password = $request->getUserVar($fieldName);
			if ($password === OAS_PLUGIN_PASSWORD_PLACEHOLDER) {
				$password = $plugin->getSetting(0, $fieldName);
			}
			$this->setData($fieldName, $password);
		}
	}

	/**
	 * @see Form::execute()
	 */
	function execute() {
		$plugin =& $this->_plugin;
		$formFields = array_merge($this->_getFormFields(), $this->_getPasswordFields());
		foreach($formFields as $formField) {
			$plugin->updateSetting(0, $formField, $this->getData($formField), 'string');
		}
	}


	//
	// Private helper methods
	//
	/**
	 * Return the field names of this form except password fields.
	 * @return array
	 */
	function _getFormFields() {
		return array(
			'privacyMessage', 'saltApiUsername', 'oasServerUrl', 'oasServerUsername'
		);
	}
	
	/**
	 * Return the field names of password fields.
	 * @return array
	 */
	function _getPasswordFields() {
		return array(
			'saltApiPassword', 'oaiPassword', 'oasServerPassword'
		);
	}
}

?>
