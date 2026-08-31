<?php
namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Services\PdfTemplateService;
use App\Support\ModuleGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CasePdfController extends Controller
{
    public function __invoke(Request $request, CaseModel $case, PdfTemplateService $pdf)
    {
        if (!ModuleGate::enabled('case_pdf')) {
            abort(403, 'ماژول PDF پرونده غیرفعال است');
        }
        $code = $request->get('template', 'FI');
        $tpl = null;
        if (Schema::hasTable('templates')) {
            $tpl = DB::table('templates')->where('code', $code)->orWhere('name', 'like', '%'.$code.'%')->first();
        }
        $vars = [
            'case_number' => $case->case_number ?? $case->id,
            'customer_name' => $case->organization->name ?? ($case->title ?? ''),
            'amount' => $case->proposal_amount ?? $case->gross_amount ?? '',
            'company_name' => \App\Models\AppSetting::get('company_name', 'Company'),
            'date' => function_exists('jdate') ? jdate(now()) : now()->format('Y-m-d'),
        ];
        $header = $tpl->header ?? '{{company_name}}';
        $body = $tpl->body ?? ("پرونده: {{case_number}}\nمشتری: {{customer_name}}\nمبلغ: {{amount}}\nتاریخ: {{date}}");
        $footer = $tpl->footer ?? '';
        $html = $pdf->toHtml($header, $body, $footer, $vars);
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
