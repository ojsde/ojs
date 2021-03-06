{**
 * plugins/generic/oas/templates/settingsForm.tpl
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * OA-S plugin settings
 *}
{strip}
{assign var="pageTitle" value="plugins.generic.oas.settings.oasSettings"}
{include file="common/header.tpl"}
{/strip}
<div id="oasSettings">
	<script type="text/javascript">
		$(function() {ldelim}
			// Attach the form handler.
			$('#oasSettingsForm').pkpHandler('$.pkp.controllers.form.FormHandler');
		{rdelim});
	</script>
	<form class="pkp_form" id="oasSettingsForm" method="post" action="{plugin_url path="settings"}">
		{include file="common/formErrors.tpl"}

		<h3>{translate key="plugins.generic.oas.settings.privacySettings"}</h3>

		<div id="description"><p>{translate key="plugins.generic.oas.settings.privacyDescription"}</p></div>
		<div class="separator"></div>
		<br />

		{fieldLabel name="privacyMessage" key="plugins.generic.oas.settings.privacyMessage"}
		<textarea name="privacyMessage" id="privacyMessage" class="textField">{$privacyMessage|escape}</textarea>

		<br/>
		
		<h3>{translate key="plugins.generic.oas.settings.oasDataProviderSettings"}</h3>

		<div id="description"><p>{translate key="plugins.generic.oas.settings.oasDataProviderDescription"}</p></div>
		<div class="separator"></div>
		<br />

		<table width="100%" class="data">
			<tr valign="top">
				<td class="label">{fieldLabel name="oaiPassword" required="true" key="plugins.generic.oas.settings.oaiPassword"}</td>
				<td class="value"><input type="password" name="oaiPassword" id="oaiPassword" value="{$oaiPassword|escape}" size="15" maxlength="25" class="textField" />
					<br />
					<span class="instruct">{translate key="plugins.generic.oas.settings.oaiPasswordInstructions"}</span>
				</td>
			</tr>
		</table>

		<br/>
		
		<h3>{translate key="plugins.generic.oas.settings.oasServiceProviderSettings"}</h3>

		<div id="description"><p>{translate key="plugins.generic.oas.settings.oasServiceProviderDescription"}</p></div>
		<div class="separator"></div>
		<br />

		<table width="100%" class="data">
			<tr valign="top">
				<td class="label">{fieldLabel name="oasServerUrl" required="true" key="plugins.generic.oas.settings.oasServerUrl"}</td>
				<td class="value"><input type="text" name="oasServerUrl" id="oasServerUrl" value="{$oasServerUrl|escape}" size="15" maxlength="50" class="textField" />
					<br />
					<span class="instruct">{translate key="plugins.generic.oas.settings.oasServerUrlInstructions"}</span>
				</td>
			</tr>
			<tr valign="top">
				<td class="label">{fieldLabel name="oasServerUsername" required="true" key="plugins.generic.oas.settings.oasServerUsername"}</td>
				<td class="value"><input type="text" name="oasServerUsername" id="oasServerUsername" value="{$oasServerUsername|escape}" size="15" maxlength="50" class="textField" />
					<br />
					<span class="instruct">{translate key="plugins.generic.oas.settings.oasServerUsernameInstructions"}</span>
				</td>
			</tr>
			<tr valign="top">
				<td class="label">{fieldLabel name="oasServerPassword" required="true" key="plugins.generic.oas.settings.oasServerPassword"}</td>
				<td class="value"><input type="password" name="oasServerPassword" id="oasServerPassword" value="{$oasServerPassword|escape}" size="15" maxlength="25" class="textField" />
					<br />
					<span class="instruct">{translate key="plugins.generic.oas.settings.oasServerPasswordInstructions"}</span>
				</td>
			</tr>
		</table>

		<br/>
		
		<h3>{translate key="plugins.generic.oas.settings.saltApiSettings"}</h3>

		<div id="description"><p>{translate key="plugins.generic.oas.settings.saltApiDescription"}</p></div>
		<div class="separator"></div>
		<br />

		<table width="100%" class="data">
			<tr valign="top">
				<td class="label">{fieldLabel name="saltApiUsername" required="true" key="plugins.generic.oas.settings.saltApiUsername"}</td>
				<td class="value"><input type="text" name="saltApiUsername" id="saltApiUsername" value="{$saltApiUsername|escape}" size="15" maxlength="50" class="textField" />
					<br />
					<span class="instruct">{translate key="plugins.generic.oas.settings.saltApiUsernameInstructions"}</span>
				</td>
			</tr>
			<tr valign="top">
				<td class="label">{fieldLabel name="saltApiPassword" required="true" key="plugins.generic.oas.settings.saltApiPassword"}</td>
				<td class="value"><input type="password" name="saltApiPassword" id="saltApiPassword" value="{$saltApiPassword|escape}" size="15" maxlength="25" class="textField" />
					<br />
					<span class="instruct">{translate key="plugins.generic.oas.settings.saltApiPasswordInstructions"}</span>
				</td>
			</tr>
		</table>

		<br/>

		<input type="submit" name="save" class="button defaultButton" value="{translate key="common.save"}"/><input type="button" class="button" value="{translate key="common.cancel"}" onclick="history.go(-1)"/>

		<br/>
		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
		<br/>
		
		<a id="statisticsAdmin"> </a>
		<h3>{translate key="plugins.generic.oas.settings.admin"}</h3>
		<script type="text/javascript">
			function jumpToAdminAnchor() {ldelim}
				$form = $('#oasSettings form');
				// Return directly to the rebuild index section.
				$form.attr('action', $form.attr('action') + '#statisticsAdmin');
				return true;
			{rdelim}
		</script>

		<div id="description"><p>{translate key="plugins.generic.oas.settings.adminDescription"}</p></div>
		<div class="separator"></div>
		<br />
		
		<input type="submit" name="updateStatistics" value="{translate key="plugins.generic.oas.settings.adminUpdate"}" onclick="jumpToAdminAnchor()" class="action" />
		<br/>
		{$updateStatisticsMessage}
		<br/>
		
	</form>
</div>
{include file="common/footer.tpl"}
