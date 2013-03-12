<?php

/**
 * @file plugins/generic/oas/classes/oai/OasOAIMetadataFormat_DC.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasOAIMetadataFormat_DC
 * @ingroup plugins_generic_oas_classes_oai
 *
 * @brief OA-S OAI metadata format class -- Dublin Core.
 */

import('lib.pkp.plugins.oaiMetadataFormats.dc.PKPOAIMetadataFormat_DC');
import('lib.pkp.classes.metadata.MetadataTypeDescription');
import('lib.pkp.classes.metadata.MetadataDescriptionDummyAdapter');

class OasOAIMetadataFormat_DC extends PKPOAIMetadataFormat_DC {

	/**
	 * Constructor
	 */
	function OasOAIMetadataFormat_DC() {
		parent::OAIMetadataFormat(
			'oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
			'http://www.openarchives.org/OAI/2.0/oai_dc/'
		);
	}


	//
	// Implement template methods from PKPOAIMetadataFormat_DC
	//
	/**
	 * @see PKPOAIMetadataFormat_DC::toXml()
	 */
	function toXml($record, $format = null) {
		// Create a generic DC meta-data description with extraction capability.
		$eventDescription = new MetadataDescription('plugins.metadata.dc11.schema.Dc11Schema', ASSOC_TYPE_ANY);
		$eventDescription->addSupportedMetadataAdapter(
			new MetadataDescriptionDummyAdapter($eventDescription, METADATA_DOA_EXTRACTION_MODE)
		);

		// Add basic DC data (see MyOAIDataProvider::record_helper() in
		// http://sourceforge.net/p/openaccessstati/code-0/3/tree/trunk/data-provider/index.php).
		$dcId = 'http://oa-statistik.sub.uni-goettingen.de/ns/logs/webdoc/?id=' . urlencode($record->identifier);
		$eventDescription->addStatement('dc:identifier', $dcId);
		$eventDescription->addStatement('dc:description', 'Logdaten Server webdoc.sub.gwdg.de');

		// Create DC XML.
		return parent::toXml($eventDescription, $format);
	}
}

?>
