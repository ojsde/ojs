<?php
/**
 * @defgroup classes_statistics
 */

/**
 * @file classes/statistics/MetricsDAO.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetricsDAO
 * @ingroup classes_statistics
 *
 * @brief Operations for retrieving and adding statistics data.
 */


class MetricsDAO extends DAO {

	/**
	 * Retrieve a range of aggregate, filtered, ordered metric values, i.e.
	 * a statistics report.
	 *
	 * @see <http://pkp.sfu.ca/wiki/index.php/OJSdeStatisticsConcept#Input_and_Output_Formats_.28Aggregation.2C_Filters.2C_Metrics_Data.29>
	 * for a full specification of the input and output format of this method.
	 *
	 * @param $metricType string|array metrics selection
	 * @param $columns string|array column (aggregation level) selection
	 * @param $filters array report-level filter selection
	 * @param $orderBy array order criteria
	 * @param $range null|DBResultRange paging specification
	 *
	 * @return null|array The selected data as a simple tabular result set or
	 *  null if metrics are not supported by this plug-in, the specified report
	 *  is invalid or cannot be produced or another error occurred.
	 */
	function getMetrics($metricType, $columns = array(), $filters = array(), $orderBy = array(), $range = null) {
		// Canonicalize and validate parameter format.
		if (is_scalar($metricType)) $metricType = array($metricType);
		if (is_scalar($columns)) $columns = array($columns);
		if (!(is_array($filters) && is_array($orderBy))) return null;

		// Validate parameter content.
		foreach ($metricType as $metricTypeElement) {
			if (!is_string($metricTypeElement)) return null;
		}
		$validColumns = array(
			STATISTICS_DIMENSION_JOURNAL_ID, STATISTICS_DIMENSION_ISSUE_ID,
			STATISTICS_DIMENSION_ARTICLE_ID, STATISTICS_DIMENSION_COUNTRY,
			STATISTICS_DIMENSION_ASSOC_TYPE, STATISTICS_DIMENSION_ASSOC_ID,
			STATISTICS_DIMENSION_MONTH, STATISTICS_DIMENSION_DAY,
			STATISTICS_DIMENSION_METRIC_TYPE
		);
		foreach ($columns as $column) {
			if (!in_array($column, $validColumns)) return null;
		}
		$validColumns[] = STATISTICS_METRIC;
		foreach ($filters as $filterColumn => $value) {
			if (!in_array($filterColumn, $validColumns)) return null;
		}
		$validDirections = array(STATISTICS_ORDER_ASC, STATISTICS_ORDER_DESC);
		foreach ($orderBy as $orderColumn => $direction) {
			if (!in_array($orderColumn, $validColumns)) return null;
			if (!in_array($direction, $validDirections)) return null;
		}

		// Validate correct use of the (non-additive) metric type dimension. We
		// either require a filter on a single metric type or the metric type
		// must be present as a column.
		if (empty($metricType)) return null;
		if (count($metricType) !== 1) {
			if (!in_array(STATISTICS_DIMENSION_METRIC_TYPE, $columns)) {
				array_push($columns, STATISTICS_DIMENSION_METRIC_TYPE);
			}
		}

		// Add the metric type as filter.
		$filters[STATISTICS_DIMENSION_METRIC_TYPE] = $metricType;

		// Build the select and group by clauses.
		if (empty($columns)) {
			$selectClause = 'SELECT SUM(metric) AS metric';
			$groupByClause = '';
		} else {
			$selectedColumns = implode(', ', $columns);
			$selectClause = "SELECT $selectedColumns, SUM(metric) AS metric";
			$groupByClause = "GROUP BY $selectedColumns";
		}

		// Build the where and having clauses.
		$params = array();
		$whereClause = '';
		$havingClause = '';
		$isFirst = true;
		foreach ($filters as $column => $values) {
			// The filter array contains STATISTICS_* constants for the filtered
			// hierarchy aggregation level as keys.
			if ($column === STATISTICS_METRIC) {
				$havingClause = 'HAVING ';
				$currentClause =& $havingClause;
			} else {
				if ($isFirst && $column) {
					$whereClause = 'WHERE ';
					$isFirst = false;
				} else {
					$whereClause .= ' AND ';
				}
				$currentClause =& $whereClause;
			}

			if (is_array($values) && isset($values['from'])) {
				// Range filter: The value is a hashed array with from/to entries.
				if (!isset($values['to'])) return null;
				$currentClause .= "($column BETWEEN ? AND ?)";
				$params[] = $values['from'];
				$params[] = $values['to'];
			} else {
				// Element selection filter: The value is a scalar or an
				// unordered array of one or more hierarchy element IDs.
				if (is_array($values) && count($values) === 1) {
					$values = array_pop($values);
				}
				if (is_scalar($values)) {
					$currentClause .= "$column = ?";
					$params[] = $values;
				} else {
					if (empty($values)) return null;
					$placeholders = array_pad(array(), count($values), '?');
					$placeholders = implode(', ', $placeholders);
					$currentClause .= "$column IN ($placeholders)";
					foreach ($values as $value) {
						$params[] = $value;
					}
				}
			}

			unset($currentClause);
		}

		// Build the order-by clause.
		$orderByClause = '';
		if (count($orderBy) > 0) {
			$isFirst = true;
			foreach ($orderBy as $orderColumn => $direction) {
				if ($isFirst) {
					$orderByClause = 'ORDER BY ';
				} else {
					$orderByClause .= ', ';
				}
				$orderByClause .= "$orderColumn $direction";
			}
		}

		// Build the report.
		$sql = "$selectClause FROM metrics $whereClause $groupByClause $havingClause $orderByClause";
		if (is_a($range, 'DBResultRange')) {
			if ($range->getCount() > STATISTICS_MAX_ROWS) {
				$range->setCount(STATISTICS_MAX_ROWS);
			}
			$result = $this->retrieveRange($sql, $params, $range);
		} else {
			$result = $this->retrieveLimit($sql, $params, STATISTICS_MAX_ROWS);
		}

		// Return the report.
		return $result->GetAll();
	}

	/**
	 * Purge a load batch before re-loading it.
	 *
	 * @param $loadId string
	 */
	function purgeLoadBatch($loadId) {
		$this->update('DELETE FROM metrics WHERE load_id = ?', $loadId);
	}

	/**
	 * Insert an entry into metrics table.
	 *
	 * @param $record array
	 */
	function insertRecord(&$record) {
		$recordToStore = array();

		// Required dimensions.
		$requiredDimensions = array('load_id', 'assoc_type', 'assoc_id', 'metric_type');
		foreach ($requiredDimensions as $requiredDimension) {
			if (!isset($record[$requiredDimension])) {
				throw new Exception('Cannot load record: missing dimension "' . $requiredDimension . '".');
			}
			$recordToStore[$requiredDimension] = $record[$requiredDimension];
		}
		$recordToStore['assoc_type'] = (int)$recordToStore['assoc_type'];
		$recordToStore['assoc_id'] = (int)$recordToStore['assoc_id'];

		// Foreign key lookup for the publication object dimension.
		$isArticleFile = false;
		switch($recordToStore['assoc_type']) {
			case ASSOC_TYPE_GALLEY:
			case ASSOC_TYPE_SUPP_FILE:
				if ($recordToStore['assoc_type'] == ASSOC_TYPE_GALLEY) {
					$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO'); /* @var $galleyDao ArticleGalleyDAO */
					$articleFile = $galleyDao->getGalley($recordToStore['assoc_id']);
					if (!is_a($articleFile, 'ArticleGalley')) {
						throw new Exception('Cannot load record: invalid galley id.');
					}
				} else {
					$suppFileDao = DAORegistry::getDAO('SuppFileDAO'); /* @var $suppFileDao SuppFileDAO */
					$articleFile = $suppFileDao->getSuppFile($recordToStore['assoc_id']);
					if (!is_a($articleFile, 'SuppFile')) {
						throw new Exception('Cannot load record: invalid supplementary file id.');
					}
				}
				$articleId = $articleFile->getArticleId();
				$isArticleFile = true;
				// Don't break but go on to retrieve the article.

			case ASSOC_TYPE_ARTICLE:
				if (!$isArticleFile) $articleId = $recordToStore['assoc_id'];
				$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO'); /* @var $publishedArticleDao PublishedArticleDAO */
				$article = $publishedArticleDao->getPublishedArticleByArticleId($articleId, null, true);
				if (is_a($article, 'PublishedArticle')) {
					$issueId = $article->getIssueId();
				} else {
					$issueId = null;
					$articleDao = DAORegistry::getDAO('ArticleDAO'); /* @var $articleDao ArticleDAO */
					$article = $articleDao->getArticle($articleId, null, true);
				}
				if (!is_a($article, 'Article')) {
					throw new Exception('Cannot load record: invalid article id.');
				}
				$journalId = $article->getJournalId();
				break;

			case ASSOC_TYPE_ISSUE_GALLEY:
				$articleId = null;
				$issueGalleyDao = DAORegistry::getDAO('IssueGalleyDAO'); /* @var $issueGalleyDao IssueGalleyDAO */
				$issueGalley = $issueGalleyDao->getGalley($recordToStore['assoc_id']);
				if (!is_a($issueGalley, 'IssueGalley')) {
					throw new Exception('Cannot load record: invalid issue galley id.');
				}
				$issueId = $issueGalley->getIssueId();
				$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
				$issue = $issueDao->getIssueById($issueId, null, true);
				if (!is_a($issue, 'Issue')) {
					throw new Exception('Cannot load record: issue galley without issue.');
				}
				$journalId = $issue->getJournalId();
				break;

			default:
				throw new Exception('Cannot load record: invalid association type.');
		}
		$recordToStore['journal_id'] = $journalId;
		$recordToStore['issue_id'] = $issueId;
		$recordToStore['article_id'] = $articleId;

		// We require either month or day in the time dimension.
		if (isset($record['day'])) {
			if (!String::regexp_match('/[0-9]{8}/', $record['day'])) {
				throw new Exception('Cannot load record: invalid date.');
			}
			$recordToStore['day'] = $record['day'];
			$recordToStore['month'] = substr($record['day'], 0, 6);
			if (isset($record['month']) && $recordToStore['month'] != $record['month']) {
				throw new Exception('Cannot load record: invalid month.');
			}
		} elseif (isset($record['month'])) {
			if (!String::regexp_match('/[0-9]{6}/', $record['month'])) {
				throw new Exception('Cannot load record: invalid month.');
			}
			$recordToStore['month'] = $record['month'];
		} else {
			throw new Exception('Cannot load record: Missing time dimension.');
		}

		// Country is optional.
		if (isset($record['country_id'])) $recordToStore['country_id'] = (int)$record['country_id'];

		// The metric must be set. If it is 0 we ignore the record.
		if (!isset($record['metric'])) {
			throw new Exception('Cannot load record: metric is missing.');
		}
		if (!is_numeric($record['metric'])) {
			throw new Exception('Cannot load record: invalid metric.');
		}
		$recordToStore['metric'] = (int) $record['metric'];

		// Save the record to the database.
		$fields = implode(', ', array_keys($recordToStore));
		$placeholders = implode(', ', array_pad(array(), count($recordToStore), '?'));
		$params = array_values($recordToStore);
		$this->update("INSERT INTO metrics ($fields) VALUES ($placeholders)", $params);
	}
}

?>
