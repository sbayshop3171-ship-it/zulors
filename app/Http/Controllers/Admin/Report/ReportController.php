<?php

namespace App\Http\Controllers\Admin\Report;

use App\Models\Report;
use App\Enums\ReportStatus;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ReportController extends Controller
{
    public function index()
    {
        $reports = Report::with(['reporter', 'reportable'])->latest()->paginate(10);

        return view('admin::reports.index.index', [
            'reports' => $reports
        ]);
    }

    public function show($reportId)
    {
        $reportData = Report::with(['reporter', 'reportable'])->findOrFail($reportId);

        return view('admin::reports.show.index', [
            'reportData' => $reportData
        ]);
    }

    public function destroy(int $reportId)
    {
        $reportData = Report::findOrFail($reportId);

        $reportData->delete();

        return redirect()->route('admin.reports.index')->with('flashMessage', (new Flash(content: __('admin/flash.report.deleted_success')))->get());
    }

    public function markAsIgnored(int $reportId)
    {
        $reportData = Report::findOrFail($reportId);

        $reportData->update([
            'status' => ReportStatus::IGNORED
        ]);

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.report.ignored_success')))->get());
    }

    public function markAsProcessed(int $reportId)
    {
        $reportData = Report::findOrFail($reportId);

        $reportData->update([
            'status' => ReportStatus::PROCESSED
        ]);

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.report.processed_success')))->get());
    }
}
