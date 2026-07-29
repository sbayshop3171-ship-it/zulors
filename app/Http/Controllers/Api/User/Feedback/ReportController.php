<?php

namespace App\Http\Controllers\Api\User\Feedback;

use Exception;
use App\Models\Post;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\Report\ReportType;
use App\Http\Controllers\Controller;
use App\Services\Feedback\ReportService;
use App\Services\Timeline\UserInterestService;
use App\Services\Safety\SafetyService;
use App\Traits\Http\Api\SupportsApiResponses;

class ReportController extends Controller
{
    use SupportsApiResponses;

    public function getReportReasons(Request $request)
    {
        $request->validate([
            'type' => ['required', 'string', Rule::in(ReportType::values())]
        ]);

        try {
            return $this->responseSuccess([
                'data' => $this->fetchReportReasons($request->type)
            ]);
        }

        catch(Exception $e) {
            return $this->responseError([
                'message' => $e->getMessage(),
                'errors' => [
                    'type' => [
                        $e->getMessage()
                    ]
                ]
            ]);
        }
    }

    public function sendReport(Request $request, UserInterestService $userInterestService, SafetyService $safetyService)
    {
        $request->validate([
            'type' => ['required', 'string', Rule::in(ReportType::values())],
            'reason_index' => ['required', 'integer'],
            'reportable_id' => ['required', 'integer']
        ]);

        try {
            $reportableId = $request->input('reportable_id');
            $reportableType = $request->input('type');
            $reportReasonIndex = $request->input('reason_index');

            $reportReasonData = $this->fetchReportReasons($request->type);

            if(! isset($reportReasonData['reasons'][$reportReasonIndex])) {
                throw new Exception('Report reason index is invalid.');
            }

            $reportableData = $this->fetchReportableData($reportableId, $reportableType);

            // Delete all prev report, in case if user repeats this actions.

            $reportableData->reports()->where('reporter_id', me()->id)->delete();

            $reportableData->reports()->create([
                'reporter_id' => me()->id,
                'reason_index' => $reportReasonIndex,
                'type' => ReportType::from($reportableType)
            ]);

            if($reportableData instanceof Post) {
                $userInterestService->recordPostInteraction(me(), $reportableData, UserInterestService::EVENT_REPORT);
                $safetyService->recordContentReport($reportableData);
            }

            return $this->responseSuccess([
                'data' => null
            ]);
        }

        catch(Exception $e) {
            return $this->responseError([
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ]);
        }
    }

    private function fetchReportableData(int $reportableId, string $reportableType)
    {
        $reportableData = null;

        switch($reportableType) {
            case 'post':
                $reportableData = Post::activeById($reportableId)->excludeSelf()->first();
                break;
            case 'user':
                $reportableData = User::activeById($reportableId)->excludeSelf()->first();
                break;
            case 'group':
                $reportableData = Group::active()->where('id', $reportableId)->first();
                break;
        }

        if(! $reportableData) {
            throw new Exception('Reportable resource by given id not found.');
        }

        return $reportableData;
    }

    private function fetchReportReasons(string $type)
    {
        return (new ReportService($type))->getReasons();
    }
}
