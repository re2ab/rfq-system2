<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardLayoutController extends Controller
{
    public function index()
    {
        $catalog = dashboard_widget_catalog();
        $layout = dashboard_layout();
        return view('settings.dashboard_layout', compact('catalog', 'layout'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.key' => 'required|string',
            'items.*.width' => 'required|integer|in:33,50,70,100',
        ]);

        $catalog = dashboard_widget_catalog();
        $clean = [];
        $seen = [];
        foreach ($data['items'] as $item) {
            if (!isset($catalog[$item['key']]) || isset($seen[$item['key']])) {
                continue;
            }
            $seen[$item['key']] = true;
            $clean[] = ['key' => $item['key'], 'width' => (int) $item['width']];
        }
        if (empty($clean)) {
            return back()->withErrors(['items' => 'چیدمان نامعتبر است.']);
        }

        \App\Models\AppSetting::set('dashboard_layout', json_encode($clean));

        return back()->with('success', 'چیدمان داشبورد ذخیره شد.');
    }
}
