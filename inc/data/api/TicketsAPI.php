<?php
//    POS-Tech API
//
//    Copyright (C) 2012 Scil (http://scil.coop)
//
//    This file is part of POS-Tech.
//
//    POS-Tech is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    POS-Tech is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with POS-Tech.  If not, see <http://www.gnu.org/licenses/>.

namespace Pasteque;

class TicketsAPI extends APIService {

    protected function check() {
        switch ($this->action) {
        case 'save':
            return isset($this->params['tickets'])
                    && isset($this->params['cash_id']);
        }
        return false;
    }

    protected function proceed() {
        switch ($this->action) {
        case 'save':
            // Receive ticket data as json
            $json = json_decode($this->params['tickets']);
            $cashId = $this->params['cash_id'];
            $location = NULL;
            if (isset($this->params['location'])) {
                $location = StocksService::getLocationId($this->params['location']);
                if ($location === NULL) {
                    $ret = FALSE;
                    break;
                }
            }
            $ticketsCount = count($json);
            $successes = 0;
            $pdo = PDOBuilder::getPDO();
            $pdo->beginTransaction();
            foreach ($json as $jsonTkt) {
                $label = $jsonTkt->ticket->label;
                $cashierId = $jsonTkt->cashier->id;
                $customerId = $jsonTkt->ticket->customer;
                $date = $jsonTkt->date;
                $lines = array();
                foreach ($jsonTkt->ticket->lines as $jsline) {
                    // Get line info
                    $line = count($lines) + 1;
                    $productId = $jsline->product->id;
                    $quantity = $jsline->quantity;
                    $price = $jsline->product->price;
                    $taxId = $jsline->product->taxId;
                    $newLine = new TicketLineLight($line, $productId, $quantity, 
                                                   $price, $taxId);
                    $lines[] = $newLine;
                }
                $payments = array();
                foreach ($jsonTkt->payments as $jspay) {
                    $type = $jspay->mode->code;
                    $amount = $jspay->amount;
                    $payment = new Payment($type, $amount);
                    $payments[] = $payment;
                }
                $tktLght = new TicketLight($label, $cashierId, $date, $lines,
                                           $payments, $cashId, $customerId);
                $ticket = TicketsService::buildLight($tktLght);
                if ($location !== NULL) {
                    if (TicketsService::save($ticket, $location)) {
                        $successes++;
                    }
                } else {
                    if (TicketsService::save($ticket)) {
                        $successes++;
                    }
                }
            }
            $ret = ($successes == $ticketsCount);
            if ($ret === TRUE) {
                $pdo->commit();
                $this->succeed($ret);
            } else {
                $pdo->rollback();
                $this->fail(APIError::$ERR_GENERIC);
            }
            break;
        }
    }
}

?>
