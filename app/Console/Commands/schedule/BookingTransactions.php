<?php

namespace App\Console\Commands\schedule;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use MatinUtils\NodeTracker\Models\Booking;
use MatinUtils\NodeTracker\Models\User;
use MatinUtils\NodeTracker\PriceInfo;

class BookingTransactions extends Command
{
    protected $signature = 'bookingTranssactions {nodeId? : Node Id}';
    protected $description = 'Detect Missing Transactions';
    protected $booking, $days = 1, $optionB, $optionG, $optionS;

    public function handle()
    {
        app('log')->info('bookingTranssactions start ' . app('log-system')->getPid());
        $this->optionB = true; //> $this->option('fb');
        $this->optionG = true; //> $this->option('fg');
        $this->optionS = true; //> $this->option('fs');
        $requestedNode =  $this->argument('nodeId');
        foreach (allNodeIds() as $nodeId) {
            $this->info("Node $nodeId, Days: $this->days");
            if (!empty($requestedNode) && $nodeId != $requestedNode) continue;
            switchNode($nodeId);
            $bookings = $this->getBooking();
            $data = [];
            foreach ($bookings as $booking) {
                $this->booking = $booking;
                if ($this->checkStatus(['initialized', 'validated'])) continue;
                $priceInfo = $booking->price_info;

                $transactions = $this->getTransactions($booking);
                $buyerTransactions = $transactions->where('entity_type', 'MatinUtils\NodeTracker\Models\Buyer');
                $buyer = empty($buyerTransactions->first()?->entity) ? $booking->booked_by : $buyerTransactions->first()->entity?->id . '-' . $buyerTransactions->first()->entity?->alias;
                $sellerTransactions = $transactions->where('entity_type', 'MatinUtils\NodeTracker\Models\Seller');
                $seller = empty($sellerTransactions->first()?->entity) ? $booking->seller->id . '-' . $booking->seller->alias : $sellerTransactions->first()->entity?->id . '-' . $sellerTransactions->first()->entity?->alias;
                $gatewayTransactions = $transactions->where('entity_type', 'MatinUtils\NodeTracker\Models\Gateway');
                $gateway = empty($gatewayTransactions->first()?->entity) ? $this->booking->invoiceItems[0]?->invoice?->gatewaySession?->gateway_id . '-' : $gatewayTransactions->first()->entity?->id . '-' . $gatewayTransactions->first()->entity?->title;

                if (($this->optionB && $this->optionG && $this->optionS) && $this->pass($sellerTransactions, $buyerTransactions, $gatewayTransactions)) continue;

                $data[] = [
                    $booking->created_at->format('Y-m-d H:m'),
                    $booking->getSerial(),
                    $booking->status . ' / ' . $booking->invoiceItems[0]->invoice->payment_status,
                    $seller,
                    PriceInfo::getSellerCurrency($priceInfo) . ' ' . PriceInfo::getSellerNetPrice($priceInfo, 'bySellerCurrency'),
                    $buyer,
                    PriceInfo::getBuyerCurrency($priceInfo) . ' ' . PriceInfo::getBuyerPrice($priceInfo, 'bySellerCurrency'),
                    $sellerTransactions->count(),
                    $gatewayTransactions->count(),
                    $buyerTransactions->count(),
                    $gateway,

                ];
            }
            $cols = ['Date', 'Serial', 'Status book/payment', 'Seller', 'SellerPrice', 'Buyer', 'BuyerPrice', 'ST', 'GT', 'BT', 'Gateway'];
            $this->table($cols, $data);
            $this->info("Count: " . count($data));
            // $this->writeOnFile($data, $bookings, $cols);
            $this->sendNotification($data, $bookings, $cols);
            // app('log')->info('bookingTranssactions: ' . count($data) . 'items found from total ' . count($bookings) . ' bookings for node ' . node()->domain);
        }
    }

    protected function getBooking()
    {
        $date = Carbon::today()->subdays($this->days)->toDateString();
        return Booking::select('id', 'status', 'created_at', 'price_info', 'seller_id', 'booked_by', 'travel_service', 'pnr', 'booking_info')
            ->with([
                'invoiceItems.invoice' => fn ($i) => $i->select('id', 'paid_on', 'payment_status'),
                'invoiceItems.invoice.gatewaySession' => fn ($g) => $g->select('id', 'gateway_id'),
                'seller' => fn ($g) => $g->select('id', 'alias'),
                'invoiceItems.invoice.transactions.entity',
            ])
            ->whereNotIn('status', [types('bookingStatus', 'initialized'), types('bookingStatus', 'validated')])
            ->where(function ($bq) use ($date) {
                $bq->whereDate('created_at', '>=', $date)
                    ->orWhereDate('updated_at', '>=', $date);
            })->get();
    }

    protected function getTransactions($book)
    {
        $transactions = new Collection();
        foreach ($book->invoiceItems as $key => $invoiceItem) {
            $transactions = $transactions->merge($invoiceItem->invoice->transactions);
        }
        return $transactions;
    }

    protected function pass($sellerTransactions, $buyerTransactions, $gatewayTransactions)
    {
        $pass = true;
        if ($this->optionB) {
            if ($this->checkPayment(['paid', 'confirmed'])) {
                if ($this->checkStatus(['issued', 'waiting_issue', 'contact_admin', 'on_hold'])) {
                    if ($this->gatewayIsEwallet() && $this->filterManualTransactions($buyerTransactions) != 1) {
                        if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 1) {
                            // dump(__LINE__);
                            $pass = false;
                        }
                    } else if (!$this->gatewayIsEwallet() && $this->filterManualTransactions($buyerTransactions) != 2) {
                        if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 2) {
                            // dump(__LINE__);
                            $pass = false;
                        }
                    }
                }
                if ($this->checkStatus(['canceled']) && $this->checkService('flight')) {
                    if ($this->gatewayIsEwallet() && $this->filterManualTransactions($buyerTransactions) != 2) {
                        if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 2) {
                            // dump(__LINE__);
                            $pass = false;
                        }
                    } else if (!$this->gatewayIsEwallet() && $this->filterManualTransactions($buyerTransactions) != 3) {
                        if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 3) {
                            // dump(__LINE__);
                            $pass = false;
                        }
                    }
                }
                if ($this->checkStatus(['failed'])) {
                    if ($this->gatewayIsEwallet() && $this->filterManualTransactions($buyerTransactions) != 2) {
                        if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 2) {
                            // dump(__LINE__);
                            $pass = false;
                        }
                    } else if (!$this->gatewayIsEwallet() && $this->filterManualTransactions($buyerTransactions) != 3) {
                        if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 3) {
                            // dump(__LINE__);
                            $pass = false;
                        }
                    }
                }
            } else {
                if ($this->checkStatus(['on_hold']) && $this->filterManualTransactions($buyerTransactions) != 0) {
                    if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 0) {
                        // dump(__LINE__);
                        $pass = false;
                    }
                }
                if ($this->checkStatus(['failed', 'canceled'])) {
                    if ($this->checkPayment(['not_paid'])) {
                        if ($this->filterManualTransactions($buyerTransactions) != 0) {
                            if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 0) {
                                $pass = false;
                            }
                        }
                    } else {
                        if ($this->gatewayIsEwallet() && $this->filterManualTransactions($buyerTransactions) != 2) {
                            if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 2) {
                                // dump(__LINE__);
                                $pass = false;
                            }
                        } else if (!$this->gatewayIsEwallet() && $this->filterManualTransactions($buyerTransactions) != 3) {
                            if ($this->filterCompanyBuyerTransactions($buyerTransactions) != 3) {
                                // dump(__LINE__);
                                $pass = false;
                            }
                        }
                    }
                }
            }
        }

        if ($this->optionG) {
            if ($this->checkPayment(['paid', 'confirmed'])) {
                if ($gatewayTransactions->count() != 1) {
                    $pass = false;
                }
                if ($this->checkStatus('failed')) {
                    $pass = false;
                }
            } elseif ($this->checkPayment(['refunded'])) {
                // if ($this->gatewayIsEwallet() || ) {  ////> here we should check if the refund process is successfull there should be 2 gatewayTransactions. if the fateway is ewallet and refund is not successful there should be an extra buyer transaction

                // }
            }
        }
        if ($this->optionS) {
            if ($this->checkStatus('issued') && empty($sellerTransactions->count())) {
                $pass = false;
            }
            if ($this->checkStatus('canceled')) { ///> we don't know if it's canceled from issue or validated or onhold
                if ($this->itemWasIssued()) {
                    if ($this->filterManualTransactions($sellerTransactions) != 2) {
                        $pass = false;
                    }
                } else {
                    if ($this->filterManualTransactions($sellerTransactions) != 0) {
                        $pass = false;
                    }
                }
            }
        }
        return $pass;
    }

    protected function checkStatus($status)
    {
        $status = (array) $status;
        return in_array($this->booking->status, $status);
    }

    protected function checkService($status)
    {
        $status = (array) $status;
        return in_array($this->booking->travel_service, $status);
    }

    protected function checkPayment($status)
    {
        $status = (array) $status;
        return in_array($this->booking->invoiceItems[0]->invoice->payment_status, $status);
    }

    protected function requestedStatus()
    {
        return $this->argument('status') ?? 'all';
    }

    protected function gatewayIsEwallet()
    {
        return $this->booking->invoiceItems[0]->invoice->gatewaySession->gateway_id == 1;
    }

    protected function hasGatewayTransaction($gatewayTransactions)
    {
        return !empty($gatewayTransactions->count());
    }

    protected function itemWasIssued()
    {
        $pnr = $this->booking->pnr;
        $data = explode(':', $pnr);
        return !empty($data[1]) || !empty($this->booking->booking_info['issued_at']);
    }

    protected function filterManualTransactions($sellerTransactions)
    {
        $types = $sellerTransactions->pluck('type')->toArray();
        $filtered = array_filter($types, fn ($val) => $val != 'mj-entry');
        return count($filtered);
    }

    protected function filterCompanyBuyerTransactions($buyerTransactions)
    {
        $counter = 0;
        foreach ($buyerTransactions as $transaction) {
            $user = $this->findUser($transaction->entity->user_id);
            if (empty($user->parent)) {
                $counter++;
            }
        }
        return $counter;
    }

    protected function findUser($userId)
    {
        static $users = [];
        if (empty($users[$userId])) {
            $users[$userId] = User::select('id', 'parent')->find($userId);
        }
        return $users[$userId];
    }

    protected function writeOnFile($data, $bookings, $cols)
    {
        $days = Carbon::today()->subdays($this->days)->format('Y-m-d');
        if (!empty($data)) {
            $filename = node()->id . '-' . node()->domain . "_{$days}_report.csv";
            $f = fopen($filename, 'w');
            fputcsv($f, ['Date', $days, 'first booking reviewed', empty($bookings->first()) ? '-' : $bookings->first()->getSerial(), 'total bookings reviewed', $bookings->count()]);
            fputcsv($f, []);
            fputcsv($f, $cols);
            foreach ($data as $row) {
                fputcsv($f, $row);
            }
        }
    }

    protected function sendNotification($data, $bookings, $cols)
    {
        $body = "Node: " . node()->domain;
        $body .= '<br>Time: ' . Carbon::now()->format('Y-m-d H:i');
        $body .= "<br>";
        $body .= "<br>First Booking Checked: " . (empty($bookings->first()) ? '-' : $bookings->first()->getSerial());
        $body .= "<br>Last Booking Checked: " . (empty($bookings->last()) ? '-' : $bookings->last()->getSerial());
        $body .= "<br>Total bookings checked: " . $bookings->count();
        $body .= "<br>Total items founde: " . count($data ?? []);
        if (!empty($data)) {
            $body .= "<br>";
            $body .= "<br>These bookings might have missing transactions<br>";
            foreach ($data as $value) {
                $body .= "- {$value[1]}<br>";
            }
        }
        sendCustomManagementEmail(
            [
                'to' => nodeConfigs('email'),
                'cc' => ['xaniatmatin@gmail.com', 'it@luxota.net', 'khaled.matein@gmail.com', 'mkarami.trsl@gmail.com']
            ],
            $body,
            'Missing transactions Report - ' . Carbon::now()->format('Y-m-d')
        );
    }
}
