<?php

/**
 * @file plugins/generic/oas/OasReportPlugin.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OasReportPlugin
 * @ingroup plugins_generic_oas
 *
 * @brief OA-S report plugin (and metrics provider)
 */


import('classes.plugins.ReportPlugin');

define('OAS_METRIC_TYPE_COUNTER', 'oas::counter');

class OasReportPlugin extends ReportPlugin {

	/**
	 * @see PKPPlugin::register()
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'OasReportPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.oas.report.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.reports.oas.report.description');
	}

	/**
	 * @see ReportPlugin::display()
	 */
	function display(&$args) {
		return parent::display($args);
	}

	/**
	 * @see ReportPlugin::getMetrics()
	 */
	function getMetrics($metricType = null, $columns = null, $filters = null, $orderBy = null, $range = null) {
		// Validate the metric type.
		if (!(is_scalar($metricType) || count($metricType) === 1)) return null;
		if (is_array($metricType)) $metricType = array_pop($metricType);
		if ($metricType !== OAS_METRIC_TYPE_COUNTER) return null;

		// This plug-in uses the MetricsDAO to store metrics. So we simply
		// delegate there.
		$metricsDao = DAORegistry::getDAO('MetricsDAO'); /* @var $metricsDao MetricsDAO */
		return $metricsDao->getMetrics($metricType, $columns, $filters, $orderBy, $range);
	}

	/**
	 * @see ReportPlugin::getMetricTypes()
	 */
	function getMetricTypes() {
		return array(OAS_METRIC_TYPE_COUNTER);
	}

	/**
	 * @see ReportPlugin::getMetricDisplayType()
	 */
	function getMetricDisplayType($metricType) {
		if ($metricType !== OAS_METRIC_TYPE_COUNTER) return null;
		return __('plugins.reports.oas.metricType.counter');
	}

	/**
	 * @see ReportPlugin::getMetricFullName()
	 */
	function getMetricFullName($metricType) {
		if ($metricType !== OAS_METRIC_TYPE_COUNTER) return null;
		return __('plugins.reports.oas.metricType.counter.full');
	}
}

?>
