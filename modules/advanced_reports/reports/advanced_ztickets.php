<?php
//    Pastèque Web back office, Users module
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

namespace AdvancedReports;

$db = \Pasteque\DB::get();

// Cash session request
$sqls[] = "SELECT "
        . "CASHREGISTERS.NAME, CLOSEDCASH.HOSTSEQUENCE, CLOSEDCASH.MONEY, "
        . "CLOSEDCASH.DATESTART, CLOSEDCASH.DATEEND, "
        . "CLOSEDCASH.OPENCASH, CLOSEDCASH.CLOSECASH, CLOSEDCASH.EXPECTEDCASH, "
        . "COUNT(DISTINCT(RECEIPTS.ID)) AS TICKETS, "
        . "SUM(TICKETLINES.PRICE * TICKETLINES.UNITS) AS SALES, "
        . "SUM(TICKETLINES.PRICE * TICKETLINES.UNITS * (1 + TAXES.RATE)) "
        . "AS SALESVAT "
        . "FROM CLOSEDCASH "
        . "LEFT JOIN CASHREGISTERS ON "
        . "CLOSEDCASH.CASHREGISTER_ID = CASHREGISTERS.ID "
        . "LEFT JOIN RECEIPTS ON RECEIPTS.MONEY = CLOSEDCASH.MONEY "
        . "LEFT JOIN TICKETLINES ON TICKETLINES.TICKET = RECEIPTS.ID "
        . "LEFT JOIN TAXES ON TICKETLINES.TAXID = TAXES.ID "
        . "WHERE CLOSEDCASH.DATESTART > :start "
        . "AND CLOSEDCASH.DATESTART <= :stop "
        . "GROUP BY CLOSEDCASH.MONEY, CASHREGISTERS.NAME, HOSTSEQUENCE, "
        . "DATESTART, DATEEND, OPENCASH, CLOSECASH "
        . "ORDER BY CLOSEDCASH.DATESTART DESC";

// Payments request
$sqls[] = "SELECT "
        . "CLOSEDCASH.MONEY, PAYMENTS.PAYMENT AS __KEY__, "
        . "SUM(PAYMENTS.TOTAL) AS __VALUE__ "
        . "FROM "
        . "CLOSEDCASH "
        . "LEFT JOIN RECEIPTS ON RECEIPTS.MONEY = CLOSEDCASH.MONEY "
        . "LEFT JOIN PAYMENTS ON PAYMENTS.RECEIPT = RECEIPTS.ID "
        . "WHERE CLOSEDCASH.DATESTART > :start "
        . "AND CLOSEDCASH.DATESTART <= :stop "
        . "GROUP BY CLOSEDCASH.MONEY, __KEY__ "
        . "ORDER BY CLOSEDCASH.DATESTART DESC";

// Taxes request
$sqls[] = "SELECT "
        . "CLOSEDCASH.MONEY, TAXES.NAME as __KEY__, "
        . $db->concat($db->concat("SUM(TAXLINES.BASE)", "'/'"), "SUM(TAXLINES.AMOUNT)") . " AS __VALUE__ "
        . "FROM CLOSEDCASH "
        . "LEFT JOIN RECEIPTS ON RECEIPTS.MONEY = CLOSEDCASH.MONEY "
        . "LEFT JOIN TAXLINES ON TAXLINES.RECEIPT = RECEIPTS.ID "
        . "LEFT JOIN TAXES ON TAXLINES.TAXID = TAXES.ID "
        . "WHERE CLOSEDCASH.DATESTART > :start "
        . "AND CLOSEDCASH.DATESTART < :stop "
        . "GROUP BY CLOSEDCASH.MONEY, TAXES.NAME "
        . "ORDER BY CLOSEDCASH.DATESTART DESC";

// Categories base request
$red = "SELECT "
        . "CLOSEDCASH.MONEY, "
        . $db->concat($db->concat($db->concat("CATEGORIES.NAME","' ('"),"TAXES.NAME"),"')'") . " AS __KEY__, "
        . $db->concat($db->concat("SUM(TICKETLINES.UNITS * TICKETLINES.PRICE)","'/'"),"SUM(TICKETLINES.UNITS * TICKETLINES.PRICE * TAXES.RATE)") . " AS __VALUE__ "
        . "FROM RECEIPTS, TICKETS, TICKETLINES, PRODUCTS, CATEGORIES, CLOSEDCASH, TAXES "
        . "WHERE CLOSEDCASH.DATESTART > :start "
        . "AND CLOSEDCASH.DATESTART <= :stop "
        . "AND RECEIPTS.ID = TICKETLINES.TICKET "
        . "AND RECEIPTS.ID = TICKETS.ID "
        . "AND TAXES.ID = TICKETLINES.TAXID "
        . "AND TICKETLINES.PRODUCT = PRODUCTS.ID "
        . "AND PRODUCTS.CATEGORY = CATEGORIES.ID "
        . "AND CLOSEDCASH.MONEY = RECEIPTS.MONEY "
        . "GROUP BY CLOSEDCASH.MONEY, TICKETLINES.TAXID, __KEY__ "
        . "ORDER BY CLOSEDCASH.DATESTART DESC";
$sqls[] = $red;

$fields = array("NAME", "HOSTSEQUENCE", "DATESTART", "DATEEND", "OPENCASH",
        "CLOSECASH", "EXPECTEDCASH", "TICKETS", "SALES", "SALESVAT");
$mergeFields = array("MONEY");
$headers = array(
        \i18n("CashRegister.label"),
        \i18n("Session"),
        \i18n("Session.openDate"),
        \i18n("Session.closeDate"),
        \i18n("Session.openCash"),
        \i18n("Session.closeCash"),
        \i18n("Session.expectedCash"),
        \i18n("Tickets", PLUGIN_NAME),
        \i18n("Sales", PLUGIN_NAME),
        \i18n("Sales with VAT", PLUGIN_NAME)
);

$report = new \Pasteque\MergedReport(PLUGIN_NAME, "advanced_ztickets",
        \i18n("Advanced Z tickets", PLUGIN_NAME),
        $sqls, $headers, $fields, $mergeFields);

$report->addInput("start", \i18n("Start date"), \Pasteque\DB::DATE);
$report->setDefaultInput("start", time() - (time() % 86400) - 7 * 86400);
$report->addInput("stop", \i18n("Stop date"), \Pasteque\DB::DATE);
$report->setDefaultinput("stop", time() - (time() % 86400) + 86400);

function cashMatch($val, $values) {
    if ($val != $values['CLOSECASH']) {
        return "<span style=\"color:#b00;\">" . \i18nCurr($val) . "</span>";
    }
    return $val;
}

$report->addFilter("DATESTART", "\Pasteque\stdtimefstr");
$report->addFilter("DATESTART", "\i18nDatetime");
$report->addFilter("DATEEND", "\Pasteque\stdtimefstr");
$report->addFilter("DATEEND", "\i18nDatetime");
$report->setVisualFilter("OPENCASH", "\i18nCurr", \Pasteque\Report::DISP_USER);
$report->setVisualFilter("OPENCASH", "\i18nFlt", \Pasteque\Report::DISP_CSV);
$report->setVisualFilter("CLOSECASH", "\i18nCurr", \Pasteque\Report::DISP_USER);
$report->setVisualFilter("CLOSECASH", "\i18nFlt", \Pasteque\Report::DISP_CSV);
$report->setVisualFilter("EXPECTEDCASH", "\AdvancedReports\cashMatch", \Pasteque\Report::DISP_USER);
$report->setVisualFilter("EXPECTEDCASH", "\i18nFlt", \Pasteque\Report::DISP_CSV);
$report->setVisualFilter("SALES", "\i18nCurr", \Pasteque\Report::DISP_USER);
$report->setVisualFilter("SALES", "\i18nFlt", \Pasteque\Report::DISP_CSV);
$report->setVisualFilter("SALESVAT", "\i18nCurr", \Pasteque\Report::DISP_USER);
$report->setVisualFilter("SALESVAT", "\i18nFlt", \Pasteque\Report::DISP_CSV);
$report->setMergedVisualFilter(0, "\i18nCurr", \Pasteque\Report::DISP_USER);
$report->setMergedVisualFilter(0, "\i18nFlt", \Pasteque\Report::DISP_CSV);
$report->addMergedHeaderFilter(0, "\i18n");
$report->addMergedHeaderFilter(1, "\i18n");
$report->setMergedVisualFilter(1, "\AdvancedReports\\vatI18nCurr",\Pasteque\Report::DISP_USER);
$report->setMergedVisualFilter(1, "\AdvancedReports\\vatI18nFlt",\Pasteque\Report::DISP_CSV);
$report->addMergedHeaderFilter(2, "\i18n");
$report->setMergedVisualFilter(2, "\AdvancedReports\\vatI18nCurr",\Pasteque\Report::DISP_USER);
$report->setMergedVisualFilter(2, "\AdvancedReports\\vatI18nFlt",\Pasteque\Report::DISP_CSV);

function vatI18nCurr($input) {
    $amounts = explode("/", $input);
    return \i18nCurr($amounts[0]) . " / " . \i18nCurr($amounts[1]);
}

function vatI18nFlt($input) {
    $amounts = explode("/", $input);
    return \i18nFlt($amounts[0]) . " / " . \i18nFlt($amounts[1]);
}

\Pasteque\register_report($report);
