<?php

/**
 * @file plugins/generic/oas/classes/oai/OasOAIMetadataFormat_OAS.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasOAIMetadataFormat_OAS
 * @ingroup plugins_generic_oas_classes_oai
 *
 * @brief OAI metadata format class -- OA-S / Open Context Object.
 *
 * This code is heavily inspired by the original OA-S demo context builder, see
 * http://sourceforge.net/p/openaccessstati/code-0/3/tree/trunk/logfile-parser/lib/ctxbuilder.php
 */

require_once('plugins/generic/oas/lib/oas/logfile-parser/lib/ctxbuilder.php');

class OasOAIMetadataFormat_OAS extends OAIMetadataFormat {

	/** @var CtxBuilder  */
	var $ctxbuild;

	/**
	 * Constructor
	 */
	function OasOAIMetadataFormat_OAS() {
		parent::OAIMetadataFormat(
			'ctxo', 'http://www.openurl.info/registry/docs/xsd/info:ofi/fmt:xml:xsd:ctx',
			'info:ofi/fmt:xml:xsd:ctx'
		);
		$this->ctxbuild = new CtxBuilder();
		$this->ctxbuild->setIndentString('    ');
	}


	//
	// Implement template methods from OAIMetadataFormat
	//
	/**
	 * @see OAIMetadataFormat::toXML()
	 */
	function toXml(&$record, $format = null) {
		$userAgent = $record->getData('requ_user_agent');
		$referrer = $record->getData('ref_ent_ids');
		$ctx = array(
			'status' => 200, // Always "200 - OK" in our case, we cannot log other events.
			'size' => $record->getData('admin_size'),
			'document_size'=> $record->getData('admin_document_size'),
			'time' => strtotime($record->getData('timestamp')),
			'format' => $record->getData('admin_format'),
			'document_url' => $record->getData('requ_document_url'),
			'ip-hashed' => $record->getData('requ_hashed_ip'),
			'ip-c-hashed'=> $record->getData('requ_hashed_c'),
			'stripped-hostname' => $record->getData('requ_hostname'),
			'classification' => $record->getData('requ_classification'),
			'user-agent'=> (empty($userAgent) ? false : $userAgent),
			'referring-entity' => (empty($referrer) ? false : $referrer),
			'service_id' => $record->getData('admin_service'),
			'document_ids' => array_values($record->getData('ref_ids')),
			'service_types' => array() // Omitted - only relevant for link resolvers.
		);
		$this->ctxbuild->reset();
		$this->ctxbuild->add_ctxo($ctx);
		return $this->ctxbuild->outputMemory();
	}
}

?>
