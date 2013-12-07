<?php
//    Pastèque Web back office
//
//    Copyright (C) 2013 Scil (http://scil.coop)
//
//    This file is part of Pastèque.
//
//    Pastèque is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    Pastèque is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with Pastèque.  If not, see <http://www.gnu.org/licenses/>.

namespace Pasteque;

class Report {

    const TOTAL_SUM = "sum";
    const TOTAL_AVG = "average";

    /* array use for ponderate the average */
    protected $ponderate;
    private $sql; //string
    public $headers; //array
    public $fields; //array
    protected $params;//array
    //key fields contain in $this->field: values functions
    protected $filters; //associatif array of array
    protected $grouping; //string
    protected $subtotals; //array
    protected $totals; //array

    public function __construct($sql, $headers, $fields) {
        $this->sql = $sql;
        $this->headers = $headers;
        $this->fields = $fields;
        $this->params = array();
        $this->filters = array();
        $this->grouping = NULL;
        $this->subtotals = array();
        $this->totals = array();
    }

    public function setParam($param, $value, $type = \PDO::PARAM_STR) {
        $this->params[$param] = array("value" => $value, "type" => $type);
    }

    public function getParams() {
        return $this->params;
    }

    public function run() {
        return new ReportRun($this);
    }

    public function getSql() {
        return $this->sql;
    }

    public function addFilter($field, $function) {
        if (!isset($this->filters[$field])) {
            $this->filters[$field] = array();
        }
        $this->filters[$field][] = $function;
    }

    public function getFilters() {
        return $this->filters;
    }

    public function setGrouping($field) {
        $this->grouping = $field;
    }
    public function isGrouping() {
        return $this->grouping !== NULL;
    }
    public function getGrouping() {
        return $this->grouping;
    }

    public function addSubtotal($field, $type) {
        if ($type === NULL && isset($this->subtotals[$field])) {
            unset($this->subtotals[$field]);
        } else {
            $this->subtotals[$field] = $type;
        }
    }

    public function getSubtotals() {
        return $this->subtotals;
    }
    public function hasSubtotals() {
        return count($this->subtotals) > 0;
    }

    public function addTotal($field, $type) {
        if ($type === NULL && isset($this->totals[$field])) {
            unset($this->totals[$field]);
        } else {
            $this->totals[$field] = $type;
        }
    }

    public function getTotals() {
        return $this->totals;
    }
    public function hasTotals() {
        return count($this->totals) > 0;
    }

    /** add the field ponderated by the field
     * do nothing if $ponderatedBy or $fields doesn't exist in $fields */
    public function addPonderate($field, $ponderated) {
        if (in_array($ponderated, $this->fields) && in_array($field, $this->fields)) {
                $this->ponderate[$field] = $ponderated;
        }
    }
    /** return true if the $field  ponderate something
     * false else */
    public function isPondered($field) {
        return isset($this->ponderate[$field]);
    }
    /** return the field ponderate doesn't check if field exist */
        public function getPonderate($field) {
        return $this->ponderate[$field];
    }

}

class ReportRun {

    protected $report;
    public $subtotals;
    public $totals;
    protected $tmpSubtotals;
    protected $tmpTotals;
    protected $groupRowCount;
    protected $totalRowCount;
    protected $currentGroup;
    protected $groupStart;
    protected $groupStop;
    protected $empty;
    protected $pnd;

    public function __construct($report) {
        $this->report = $report;
        $pdo = PDOBuilder::getPDO();
        $this->stmt = $pdo->prepare($report->getSql());
        foreach ($report->getParams() as $key => $param) {
            $this->stmt->bindValue($key, $param['value'], $param['type']);
        }
        $this->groupRowCount = 0;
        $this->totalRowCount = 0;
        $this->resetTmpSubtotals();
        $this->tmpTotals = array();
        $this->totals = array();
        $this->subtotals = array();
        foreach ($this->report->getTotals() as $field => $type) {
            $this->tmpTotals[$field] = 0;
        }
        $this->currentGroup = NULL;
        $this->stmt->execute();
        // Check for results
        if (!$this->stmt->fetch()) {
            $this->empty = TRUE;
        }
        $this->stmt->closeCursor();
        $this->stmt->execute();

        $this->pnd = array();
        foreach (array_keys($this->report->getTotals()) as $field) {
            $this->pnd[$field] = 0;
        }
    }

    public function isEmpty() {
        return $this->empty;
    }

    protected function resetTmpSubtotals() {
        $this->tmpSubtotals = array();
        foreach ($this->report->getSubtotals() as $field => $type) {
            $this->tmpSubtotals[$field] = 0;
        }
    }

    protected function computeTotals($source, $tmp, $count) {
        $dest = array();
        foreach ($source as $field => $type) {
            switch ($type) {
            case Report::TOTAL_SUM:
                $dest[$field] = $tmp[$field];
                break;
            case Report::TOTAL_AVG:
                if ($this->report->isPondered($field)) {
                    $num = $tmp[$this->report->getPonderate($field)];
                    // if $num is set and not equal 0
                    if ($num) {
                        $dest[$field] = $this->pnd[$field] / $num;
                    }
                } else if ($count != 0) {
                    $dest[$field] = $tmp[$field] / $count;
                } else {
                    $dest[$field] = 0;
                }
                break;
            }
        }
        return $dest;
    }

    protected function applyFilters($values) {
        if (!is_array($values)) {
            return $values;
        }
        $ret = array();
        foreach ($values as $field => $value) {
            $ret[$field] = $value;
        }
        foreach ($this->report->getFilters() as $field => $filters) {
            if (isset($ret[$field])) {
                foreach ($filters as $filter) {
                    $val = $filter($ret[$field]);
                    $ret[$field] = $val;
                }
            }
        }
        return $ret;
    }

    protected function fetchValues() {
        return $this->stmt->fetch(\PDO::FETCH_ASSOC);
    }
    protected function parseValues($values) {
        // Check for group change
        if ($this->report->isGrouping() && $values !== FALSE) {
            $group = $this->report->getGrouping();
            if ($this->currentGroup === NULL) {
                // First row
                $this->currentGroup = $values[$group];
                $this->groupStop = FALSE;
                $this->groupStart = TRUE;
            } else {
                if ($this->currentGroup != $values[$group]) {
                    // Group changed, set subtotals and reinit counts
                    $this->currentGroup = $values[$group];
                    $this->groupStop = TRUE;
                    $this->groupStart = TRUE;
                    $this->subtotals = $this->computeTotals($this->report->getSubtotals(),
                            $this->tmpSubtotals, $this->groupRowCount);
                    $this->subtotals = $this->applyFilters($this->subtotals);
                    $this->resetTmpSubTotals();
                    $this->groupRowCount = 0; // will be incremented to 1
                } else {
                    $this->groupStop = FALSE;
                    $this->groupStart = FALSE;
                }
            }
        }
        // Add values
        if ($values !== FALSE) {
            $this->groupRowCount++;
            $this->totalRowCount++;
            foreach ($this->report->getTotals() as $field => $type) {
                if (isset($values[$field])) {
                    $this->tmpTotals[$field] += $values[$field];
                    if ($this->report->isPondered($field)) {
                        $this->pnd[$field] += $values[$field] * $values[$this->report->getPonderate($field)];
                    }
                }
            }
            if ($this->report->isGrouping()) {
                foreach ($this->report->getSubtotals() as $field => $type) {
                    if(isset($values[$field])) {
                        $this->tmpSubtotals[$field] += $values[$field];
                    }
                }
            }
        } else {
            // End, set totals and last group subtotals
            $this->subtotals = $this->computeTotals($this->report->getSubtotals(),
                    $this->tmpSubtotals, $this->groupRowCount);
            $this->subtotals = $this->applyFilters($this->subtotals);
            $this->totals = $this->computeTotals($this->report->getTotals(),
                    $this->tmpTotals, $this->totalRowCount);
            $this->totals = $this->applyFilters($this->totals);
            $this->groupStop = TRUE;
        }
        return $values;
    }

    public function fetch() {
        $values = $this->fetchValues();
        $values = $this->parseValues($values);
        return $this->applyFilters($values);
    }

    /** The group has changed. Check $subtotals for group total. */
    public function isGroupEnd() {
        return $this->groupStop;
    }

    public function isGroupStart() {
        return $this->groupStart;
    }
    public function getCurrentGroup() {
        return $this->currentGroup;
    }
}

/** Cross-report. A merged report is made of a main report wich is completed by
 * subreports. Subreports adds columns to the first based on merge fields.
 * Subreports has (merge fields count + 2) fields, the field name and its value.
 */
class MergedReport extends Report {

    private $sqls;
    private $mergeFields;
    private $mergedTotals;
    private $mergedSubtotals;
    private $mergedFilters;

    public function __construct($sqls, $headers, $fields, $mergeFields) {
        parent::__construct($sqls[0], $headers, $fields);
        $this->sqls = array_slice($sqls, 1);
        $this->mergeFields = $mergeFields;
        $this->mergedTotals = array();
        $this->mergedSubtotals = array();
        $this->mergedFilters = array();
    }

    public function run() {
        return new MergedReportRun($this);
    }

    public function getSubsqls() {
        return $this->sqls;
    }

    public function getMergeFields() {
        return $this->mergeFields;
    }
    public function addMergedField($sqlIndex, $field) {
        if (!in_array($field, $this->fields)) {
            $this->fields[] = $field;
            $this->headers[] = $field;
        }
        if (isset($this->mergedTotals[$sqlIndex])) {
            $this->addTotal($field, $this->mergedTotals[$sqlIndex]);
        }
        if (isset($this->mergedSubtotals[$sqlIndex])) {
            $this->addSubtotal($field, $this->mergedTotals[$sqlIndex]);
        }
        if (isset($this->mergedFilters[$sqlIndex])) {
            foreach ($this->mergedFilters[$sqlIndex] as $function) {
                $this->addFilter($field, $function);
            }
        }
    }

    public function addMergedTotal($sqlIndex, $type) {
        $this->mergedTotals[$sqlIndex] = $type;
    }

    public function addMergedSubtotal($sqlIndex, $type) {
        $this->mergedSubtotals[$sqlIndex] = $type;
    }

    public function addMergedFilter($sqlIndex, $function) {
        if (!isset($this->mergedFilters[$sqlIndex])) {
            $this->mergedFilters[$sqlIndex] = array();
        }
        $this->mergedFilters[$sqlIndex][] = $function;
    }
}

class MergedReportRun extends ReportRun {

    private $runs;
    private $substmts;
    private $lastValues;

    public function __construct($mergedReport) {
        $this->report = $mergedReport;
        $pdo = PDOBuilder::getPDO();
        $this->stmt = $pdo->prepare($mergedReport->getSql());
        foreach ($mergedReport->getParams() as $key => $param) {
            $this->stmt->bindValue($key, $param['value'], $param['type']);
        }
        $this->substmts = array();
        foreach ($mergedReport->getSubsqls() as $sql) {
            $stmt = $pdo->prepare($sql);
            $this->substmts[] = $stmt;
            foreach ($mergedReport->getParams() as $key => $param) {
                $stmt->bindValue($key, $param['value'], $param['type']);
            }
        }
        $this->groupRowCount = 0;
        $this->totalRowCount = 0;
        $this->tmpTotals = array();
        $this->totals = array();
        $this->subtotals = array();
        $this->lastValues = array();
        $this->currentGroup = NULL;
        $this->stmt->execute();
        // Check for results
        if (!$this->stmt->fetch()) {
            $this->empty = TRUE;
        }
        $this->stmt->closeCursor();
        $this->stmt->execute();
        // Make a first run of substatements to get new fields
        for ($i = 0; $i < count($this->substmts); $i++) {
            $stmt = $this->substmts[$i];
            $stmt->execute();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->report->addMergedField($i, $row["__KEY__"]);
            }
            // Reopen
            $stmt->closeCursor();
            $stmt->execute();
        }
        $this->resetTmpSubtotals();
        foreach ($this->report->getTotals() as $field => $type) {
            $this->tmpTotals[$field] = 0;
        }
    }

    protected function checkMergeValues($refData, $currData) {
        foreach ($refData as $key => $value) {
            if (!isset($currData[$key]) || $currData[$key] != $value) {
                return FALSE;
            }
        }
        return TRUE;
    }

    protected function fetchValues() {
        $allValues = parent::fetchValues();
        $mergeData = array();
        foreach ($this->report->getMergeFields() as $mergeField) {
            $mergeData[$mergeField] = $allValues[$mergeField];
        }
        for ($i = 0; $i < count($this->substmts); $i++) {
            $substmt = $this->substmts[$i];
            if (! isset($this->lastValues[$i])) {
                $this->lastValues[$i] = $substmt->fetch(\PDO::FETCH_ASSOC);
            }
            while ($this->checkMergeValues($mergeData, $this->lastValues[$i])) {
                $currValues = $this->lastValues[$i];
                $allValues[$currValues["__KEY__"]] = $currValues["__VALUE__"];
                $this->lastValues[$i] = $substmt->fetch(\PDO::FETCH_ASSOC);
            }
        }
        return $allValues;
    }
}

global $REPORTS;
$REPORTS = array();

function register_report($module, $name, $report) {
    global $REPORTS;
    $REPORTS[$module . ":" . $name] = $report;
}
function get_report($module, $name) {
    report_content($module, $name);
    global $REPORTS;
    if (isset($REPORTS[$module . ":" . $name])) {
        return $REPORTS[$module . ":" . $name];
    } else {
        return NULL;
    }
}
?>
