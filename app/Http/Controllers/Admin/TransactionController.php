<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Level 1 — All Vendors
     */
    public function index(Request $request)
    {
        $vendors = DB::table('sales_transactions')
            ->join('vendors', 'sales_transactions.vendor_id', '=', 'vendors.vendor_id')
            ->select(
                'vendors.vendor_id',
                'vendors.vendor_name',
                DB::raw('COUNT(sales_transactions.policy_id) as total_transactions'),
                DB::raw('SUM(sales_transactions.premium_amount) as total_amount')
            )
            ->whereMonth('sales_transactions.created_at', now()->month)
            ->whereYear('sales_transactions.created_at', now()->year)
            ->where('sales_transactions.is_deleted', false)
            ->groupBy('vendors.vendor_id', 'vendors.vendor_name')
            ->orderByDesc('total_amount')
            ->get();

        $summary = [
            'total_transactions' => $vendors->sum('total_transactions'),
            'total_amount'       => $vendors->sum('total_amount'),
        ];

        return view('admin.transactions.index', compact('vendors', 'summary'));
    }

    /**
     * Level 2 — Vendor → Products
     */
    public function byVendor(Request $request, string $vendorId)
    {
        $vendor = DB::table('vendors')->where('vendor_id', $vendorId)->first();

        if (!$vendor) {
            return redirect()->route('admin.transactions')->withErrors(['error' => 'Vendor not found.']);
        }

        $categories = DB::table('sales_transactions')
            ->join('products', 'sales_transactions.product_id', '=', 'products.product_id')
            ->select(
                'products.product_id',
                'products.product_name',
                'products.category',
                DB::raw('COUNT(sales_transactions.policy_id) as total_transactions'),
                DB::raw('SUM(sales_transactions.premium_amount) as total_amount')
            )
            ->where('sales_transactions.vendor_id', $vendorId)
            ->whereMonth('sales_transactions.created_at', now()->month)
            ->whereYear('sales_transactions.created_at', now()->year)
            ->where('sales_transactions.is_deleted', false)
            ->groupBy('products.product_id', 'products.product_name', 'products.category')
            ->orderByDesc('total_amount')
            ->get();

        $summary = [
            'total_transactions' => $categories->sum('total_transactions'),
            'total_amount'       => $categories->sum('total_amount'),
        ];

        return view('admin.transactions.by-vendor', compact('vendor', 'categories', 'summary'));
    }

    /**
     * Level 3 — Product → Individual Transactions
     */
    public function byProduct(Request $request, string $vendorId, string $productId)
    {
        $vendor  = DB::table('vendors')->where('vendor_id', $vendorId)->first();
        $product = DB::table('products')->where('product_id', $productId)->first();

        if (!$vendor || !$product) {
            return redirect()->route('admin.transactions');
        }

        $transactions = DB::table('sales_transactions')
            ->join('agents', 'sales_transactions.agent_id', '=', 'agents.agent_id')
            ->leftJoin('customers', 'sales_transactions.customer_id', '=', 'customers.customer_id')
            ->select(
                'sales_transactions.policy_id',
                'sales_transactions.policy_number',
                'sales_transactions.premium_amount',
                'sales_transactions.sum_insured',
                'sales_transactions.coverage_start',
                'sales_transactions.coverage_end',
                'sales_transactions.status',
                'sales_transactions.created_at',
                'agents.full_name as agent_name',
                'agents.agent_code',
                'customers.full_name as customer_name'
            )
            ->where('sales_transactions.vendor_id', $vendorId)
            ->where('sales_transactions.product_id', $productId)
            ->whereMonth('sales_transactions.created_at', now()->month)
            ->whereYear('sales_transactions.created_at', now()->year)
            ->where('sales_transactions.is_deleted', false)
            ->orderByDesc('sales_transactions.created_at')
            ->paginate(20);

        $summary = [
            'total_transactions' => $transactions->total(),
            'total_amount'       => DB::table('sales_transactions')
                ->where('vendor_id', $vendorId)
                ->where('product_id', $productId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('is_deleted', false)
                ->sum('premium_amount'),
        ];

        return view('admin.transactions.by-product', compact('vendor', 'product', 'transactions', 'summary'));
    }
}
