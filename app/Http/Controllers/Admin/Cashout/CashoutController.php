<?php

namespace App\Http\Controllers\Admin\Cashout;

use App\Enums\Wallet\CashoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Cashout;
use App\Services\Wallet\CashoutService;
use App\Services\Wallet\WalletService;
use App\Support\Views\Flash;
use Illuminate\Http\Request;

class CashoutController extends Controller
{
    private $filters = [];

    public function index(Request $request)
    {
        $status = $request->string('status')->value;
        $status = in_array($status, array_column(CashoutStatus::cases(), 'value')) ? $status : 'all';

        $this->filters = [
            'search' => $request->string('search')->value,
            'status' => $status,
        ];

        $cashouts = Cashout::with(['user'])->when(($this->filters['status'] != 'all'), function($query) {
            $query->where('status', CashoutStatus::tryFrom($this->filters['status']));
        })->when((! empty($this->filters['search'])), function ($query) {
            $query->where(function($query) {
                $query->where('request_code', 'like', "%{$this->filters['search']}%")
                    ->orWhereHas('user', function($query) {
                        $query->where('first_name', 'like', "%{$this->filters['search']}%")
                            ->orWhere('last_name', 'like', "%{$this->filters['search']}%")
                            ->orWhere('username', 'like', "%{$this->filters['search']}%")
                            ->orWhere('bio', 'like', "%{$this->filters['search']}%")
                            ->orWhere('email', 'like', "%{$this->filters['search']}%");
                    });
            });
        })->latest()->paginate(10);

        return view('admin::cashouts.index.index', [
            'cashouts' => $cashouts,
            'filters' => $this->filters,
        ]);
    }

    public function show(Request $request, int $cashoutId)
    {
        $cashoutData = $this->getCashoutData($cashoutId);

        return view('admin::cashouts.show.index', [
            'cashoutData' => $cashoutData,
        ]);
    }

    public function destroy(Request $request, int $cashoutId)
    {
        $cashoutData = $this->getCashoutData($cashoutId);

        if($cashoutData->status->isPending()) {
            // Return the amount to the user's wallet.

            (new CashoutService($cashoutData->user))
                ->returnAmountToWallet($cashoutData->amount);
        }

        // TODO: Move logic to CashoutService.
        $cashoutData->delete();

        return redirect()->route('admin.cashouts.index')->with('flashMessage', (new Flash(content: __('admin/flash.cashout.deleted_success')))->get());
    }

    public function reject(Request $request, int $cashoutId)
    {
        $cashoutData = $this->getCashoutData($cashoutId);

        if($cashoutData->status->isPending()) {
            (new CashoutService($cashoutData->user))
                ->rejectCashout($cashoutId);

            // Return the amount to the user's wallet.
            (new CashoutService($cashoutData->user))
                ->returnAmountToWallet($cashoutData->amount);

            return redirect()->route('admin.cashouts.show', $cashoutId)->with('flashMessage', (new Flash(content: __('admin/flash.cashout.rejected_success')))->get());
        }

        abort(404);
    }

    public function markAsPaid(Request $request, int $cashoutId)
    {
        $cashoutData = $this->getCashoutData($cashoutId);

        if($cashoutData->status->isPending()) {
            (new CashoutService($cashoutData->user))
                ->markAsPaid($cashoutId);

            return redirect()->route('admin.cashouts.show', $cashoutId)->with('flashMessage', (new Flash(content: __('admin/flash.cashout.marked_as_paid_success')))->get());
        }

        abort(404);
    }

    private function getCashoutData(int $cashoutId)
    {
        return Cashout::with(['user'])->findOrFail($cashoutId);
    }
}
