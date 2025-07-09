<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DemoAccount;
use Illuminate\Support\Facades\DB;

class DemoController extends Controller
{
    // Helper: get or create demo account for user
    protected function getOrCreateDemoAccount($userId)
    {
        $account = DemoAccount::where('user_id', $userId)->first();
        if (!$account) {
            $initialHoldings = []; // Start with no holdings - users must buy first
            $account = DemoAccount::create([
                'user_id' => $userId,
                'balance' => 10000,
                'holdings' => json_encode($initialHoldings),
                'trades' => json_encode([]),
            ]);
        }
        return $account;
    }

    // GET /api/demo/account
    public function getAccount(Request $request)
    {
        $user = auth()->user();
        $account = $this->getOrCreateDemoAccount($user->id);
        $account->holdings = json_decode($account->holdings, true);
        $account->trades = json_decode($account->trades, true);
        return response()->json($account);
    }

    // POST /api/demo/trade
    public function trade(Request $request)
    {
        $user = auth()->user();
        $account = $this->getOrCreateDemoAccount($user->id);
        $data = $request->validate([
            'asset' => 'required|string',
            'type' => 'required|in:Buy,Sell',
            'amount' => 'required|numeric',
            'price' => 'required|numeric',
        ]);
        $holdings = json_decode($account->holdings, true) ?: [];
        $trades = json_decode($account->trades, true) ?: [];
        $holdingIndex = collect($holdings)->search(fn($h) => $h['asset'] === $data['asset']);
        if ($holdingIndex === false) {
            $holdings[] = [ 'asset' => $data['asset'], 'amount' => 0 ];
            $holdingIndex = count($holdings) - 1;
        }
        $pnl = 0;
        if ($data['type'] === 'Buy') {
            $cost = $data['amount'] * $data['price'];
            if ($cost > $account->balance) {
                return response()->json(['message' => 'Insufficient demo balance'], 400);
            }
            $account->balance -= $cost;
            $holdings[$holdingIndex]['amount'] += $data['amount'];
        } elseif ($data['type'] === 'Sell') {
            if ($holdings[$holdingIndex]['amount'] <= 0) {
                return response()->json(['message' => 'You must buy this asset first before you can sell it'], 400);
            }
            if ($data['amount'] > $holdings[$holdingIndex]['amount']) {
                return response()->json(['message' => 'Insufficient asset in demo account. You only have ' . $holdings[$holdingIndex]['amount'] . ' ' . $data['asset']], 400);
            }
            $proceeds = $data['amount'] * $data['price'];
            // For demo purposes, we'll calculate a simple PnL based on a hypothetical buy price
            // In a real system, you'd track the actual buy price for each position
            $hypotheticalBuyPrice = $data['price'] * 0.9; // Assume 10% profit for demo
            $pnl = $proceeds - ($data['amount'] * $hypotheticalBuyPrice);
            $account->balance += $proceeds;
            $holdings[$holdingIndex]['amount'] -= $data['amount'];
        }
        $trades = array_merge([
            [
                'asset' => $data['asset'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'price' => $data['price'],
                'pnl' => $pnl,
                'time' => now()->toISOString(),
            ]
        ], $trades);
        $account->holdings = json_encode($holdings);
        $account->trades = json_encode($trades);
        $account->updated_at = now();
        $account->save();
        $account->holdings = $holdings;
        $account->trades = $trades;
        return response()->json($account);
    }

    // POST /api/demo/reset
    public function reset(Request $request)
    {
        $user = auth()->user();
        $account = $this->getOrCreateDemoAccount($user->id);
        $initialHoldings = []; // Reset to no holdings - users must buy first
        $account->balance = 10000;
        $account->holdings = json_encode($initialHoldings);
        $account->trades = json_encode([]);
        $account->updated_at = now();
        $account->save();
        $account->holdings = $initialHoldings;
        $account->trades = [];
        return response()->json($account);
    }
} 