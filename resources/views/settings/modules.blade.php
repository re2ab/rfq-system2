@extends('layouts.settings')

@section('title', 'مدیریت ماژول‌ها')

@section('settings')

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-right p-3">نام ماژول</th>
                <th class="text-right p-3">کلید</th>
                <th class="text-right p-3">وضعیت</th>
                <th class="text-right p-3">عملیات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($modules as $mod)
            <tr class="border-b">
                <td class="p-3">{{ $mod->name }} @if($mod->is_core)<span class="text-xs text-gray-400">(هسته)</span>@endif</td>
                <td class="p-3 font-mono text-xs">{{ $mod->key }}</td>
                <td class="p-3">
                    @if($mod->is_enabled)
                        <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 text-xs">فعال</span>
                    @else
                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600 text-xs">غیرفعال</span>
                    @endif
                </td>
                <td class="p-3">
                    @if(!$mod->is_core)
                    <form method="POST" action="{{ route('settings.modules.toggle', $mod->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-blue-600 text-xs hover:underline">
                            {{ $mod->is_enabled ? 'غیرفعال کردن' : 'فعال کردن' }}
                        </button>
                    </form>
                    @else
                        —
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
