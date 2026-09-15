<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\InsightsService;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    protected InsightsService $insights;

    public function __construct(InsightsService $insights)
    {
        $this->insights = $insights;
    }

    /**
     * Insights overview — tabbed page
     */
    public function index()
    {
        $products = Product::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        return view('insights.index', compact('products', 'suppliers'));
    }

    /**
     * AJAX: Supplier Comparison for a product
     */
    public function supplierComparison(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);
        $data = $this->insights->supplierComparisonForProduct($request->product_id);

        return response()->json($data);
    }

    /**
     * AJAX: Product rankings (profit, loss, or return ratio)
     */
    public function rankings(Request $request)
    {
        $request->validate(['type' => 'required|in:profit,loss,return_ratio']);

        return match ($request->type) {
            'profit' => response()->json($this->insights->topProfitableProducts()),
            'loss' => response()->json($this->insights->topLossMakingProducts()),
            'return_ratio' => response()->json($this->insights->lowestReturnRatioProducts()),
        };
    }

    /**
     * AJAX: which marketplace actually makes money
     */
    public function platformComparison(Request $request)
    {
        $request->validate(['months' => 'nullable|integer|min:1|max:60']);
        $months = $request->filled('months') ? (int) $request->months : null;

        return response()->json([
            'months' => $months,
            'platforms' => $this->insights->platformComparison($months),
        ]);
    }

    /**
     * AJAX: returns/RTO split by payment mode (COD vs prepaid)
     */
    public function paymentModes(Request $request)
    {
        $request->validate(['months' => 'nullable|integer|min:1|max:60']);
        $months = $request->filled('months') ? (int) $request->months : null;

        return response()->json([
            'months' => $months,
            'modes' => $this->insights->paymentModeBreakdown($months),
        ]);
    }

    /**
     * AJAX: Health score rankings
     */
    public function healthScores()
    {
        return response()->json($this->insights->healthScoreRankings());
    }

    /**
     * AJAX: Time trend for product or supplier
     */
    public function timeTrend(Request $request)
    {
        $request->validate([
            'type' => 'required|in:product,supplier',
            'entity_id' => 'required|integer',
            'months' => 'nullable|integer|min:2|max:24',
        ]);

        $data = $this->insights->timeTrend(
            $request->type,
            $request->entity_id,
            $request->months ?? 6
        );

        return response()->json($data);
    }
}
