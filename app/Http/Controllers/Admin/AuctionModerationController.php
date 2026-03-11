<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Http\Helpers\ApiResponse; // تأكد من المسار الصحيح لهيلبر الـ API عندك
use Illuminate\Http\Request;

class AuctionModerationController extends Controller
{
    /**
     * واجهة الأدمن للموافقة على المزاد
     * مفهوم هندسي: Resource Transformation
     * هنا نقوم بتغيير حالة الكائن ليقوم الـ Observer بالباقي
     */
    public function approve($id)
    {
        $auction = Auction::findOrFail($id);

        // بمجرد التحديث، سيتم استدعاء الـ AuctionObserver تلقائياً
        $auction->update([
            'moderation_status' => 'approved'
        ]);

        return ApiResponse::success('تمت الموافقة على المزاد وتفعيله بنجاح');
    }

    public function reject($id)
    {
        $auction = Auction::findOrFail($id);

        $auction->update([
            'moderation_status' => 'rejected',
            'is_active' => false
        ]);

        return ApiResponse::success('تم رفض المزاد بنجاح');
    }
}
