@extends('layouts.app')
@section('object', 'پرونده')
@section('title', $case->case_number)
@section('subtitle', $case->title)
@section('actions')
  <x-btn href="{{ route('cases.edit', $case) }}">ویرایش</x-btn>
  <x-btn variant="ghost" href="{{ route('documents.create', ['case_id'=>$case->id]) }}">سند جدید</x-btn>
  <form method="POST" action="{{ route('cases.destroy', $case) }}" onsubmit="return confirm('حذف این پرونده؟')" style="display:inline">@csrf @method('DELETE')
    <x-btn variant="danger" type="submit">حذف</x-btn>
  </form>
@endsection

@php
  $chatOn = \App\Support\ModuleGate::enabled('case_chat');
  $pdfOn = \App\Support\ModuleGate::enabled('case_pdf');
  $chatMsgs = ($chatOn && \Illuminate\Support\Facades\Schema::hasTable('case_chat_messages'))
    ? \Illuminate\Support\Facades\DB::table('case_chat_messages')->where('case_id', $case->id)->orderBy('id')->limit(100)->get()
    : collect();
  // M13: برای آیکون دانلود PDF کنار هر سند در فهرست اسناد این پرونده
  $docPdfAvailable = false;
  try { $docPdfAvailable = app(\App\Services\Documents\PdfConversionService::class)->active()->isAvailable(); } catch (\Throwable $e) {}
@endphp

@section('content')
@include('partials.jalali')

{{-- نوار تب مدرن و یکپارچه --}}
<div class="card mb-4" style="background: var(--surface); padding: 8px;">
  <div style="display: flex; gap: 6px; background: var(--surface-2); padding: 4px; border-radius: var(--r-md); border: 1px solid var(--border-soft);">
    <button type="button" class="case-tab-btn active" onclick="switchCaseTab(event, 'tab-details')">جزئیات پرونده</button>
    <button type="button" class="case-tab-btn" onclick="switchCaseTab(event, 'tab-activities')">فعالیت‌ها</button>
    <button type="button" class="case-tab-btn" onclick="switchCaseTab(event, 'tab-documents')">اسناد</button>
    <button type="button" class="case-tab-btn" onclick="switchCaseTab(event, 'tab-emails')">ایمیل‌ها</button>
    <button type="button" class="case-tab-btn" onclick="switchCaseTab(event, 'tab-tasks')">وظایف</button>
  </div>
</div>

<div class="case-tabs-content">

  {{-- ==========================================================
       تب اول: جزئیات پرونده (۵۰ درصد جزئیات راست | ۵۰ درصد وضعیت چپ)
       ========================================================== --}}
  <div id="tab-details" class="case-tab-pane" style="display: block;">
    <div class="case-row-50">
      <div>
        <x-card title="جزئیات پرونده">
          <div class="field-row"><span class="lbl">عنوان</span><span class="val">{{ $case->title }}</span><span></span></div>
          <div class="field-row"><span class="lbl">شماره درخواست مشتری</span><span class="val">{{ $case->customer_request_number ?? '—' }}</span><span></span></div>
          <div class="field-row"><span class="lbl">تاریخ ایجاد</span><span class="val">{{ $case->created_at ? jdate($case->created_at)->format('Y/m/d') : '—' }}</span><span></span></div>
          <div class="field-row"><span class="lbl">اولویت</span><span class="val">{{ $case->priority ?? '—' }}</span><span></span></div>
          <div class="field-row"><span class="lbl">مشتری</span><span class="val">@if($case->customer)<a href="{{ route('organizations.show', $case->customer) }}">{{ $case->customer->name }}</a>@else — @endif</span><span></span></div>
          <div class="field-row"><span class="lbl">مخاطب</span><span class="val">@if($case->contact)<a href="{{ route('contacts.show', $case->contact) }}">{{ trim(($case->contact->first_name ?? '').' '.($case->contact->last_name ?? '')) ?: ($case->contact->name ?? ('#'.$case->contact->id)) }}</a>@if($case->contact->position ?? null) <span class="text-xs text-gray-500">({{ $case->contact->position }})</span>@endif @else — @endif</span><span></span></div>
          <div class="field-row"><span class="lbl">کارشناس</span><span class="val">{{ $case->expert?->name ?? '—' }}</span><span></span></div>
          <div class="field-row"><span class="lbl">وضعیت</span><span class="val"><x-status-badge :status="$case->current_status" /></span><span></span></div>
          <div class="field-row"><span class="lbl">تخصیص‌یافته‌ها</span><span class="val"><x-user-avatars :users="$case->allAssignees()" :size="28" />
          <span style="margin-right:8px;font-size:12px">{{ $case->allAssignees()->pluck('name')->join('، ') }}</span></span><span></span></div>
          @if($case->tags->isNotEmpty())
          <div class="field-row"><span class="lbl">تگ‌ها</span><span class="val">@foreach($case->tags as $tag)<span style="display:inline-block;margin-left:4px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $tag->color }}22;color:{{ $tag->color }}">{{ $tag->name }}</span>@endforeach</span><span></span></div>
          @endif
          @if($case->proposal_amount !== null)
          <div class="field-row"><span class="lbl">مبلغ پیشنهاد (خالص)</span><span class="val" style="font-weight:800">{{ number_format((float)$case->proposal_amount, 2) }} {{ $case->currency }}</span><span></span></div>
          <div class="field-row"><span class="lbl">ارزش افزوده</span><span class="val">{{ number_format((float)($case->vat_percent ?? 0), 2) }}٪</span><span></span></div>
          <div class="field-row"><span class="lbl">مبلغ قابل دریافت (ناخالص)</span><span class="val" style="font-weight:800;color:var(--brand)">{{ number_format((float)($case->proposal_gross ?? $case->computeGross()), 2) }} {{ $case->currency }}</span><span></span></div>
          @endif
          @if($case->description)
          <div class="field-row"><span class="lbl">شرح</span><span class="val" style="white-space:pre-wrap">{{ $case->description }}</span><span></span></div>
          @endif
          @include('partials.custom_fields_display')
        </x-card>
      </div>
      
      <div>
        <x-card title="تغییر وضعیت">
          <form method="POST" action="{{ route('cases.change-status', $case) }}" class="space-y-2 text-sm" id="statusForm">@csrf
            <select name="status" id="statusSelect" class="w-full border rounded px-3 py-2">
              @foreach(\App\Models\CaseModel::statusLabels() as $k => $lab)
                <option value="{{ $k }}" @selected($case->current_status === $k)>{{ $lab }}</option>
              @endforeach
            </select>
            <div id="financialFields" style="display:none;padding:10px;background:var(--brand-soft-2);border-radius:10px;border:1px solid var(--border-soft)">
              <div class="font-semibold" style="margin-bottom:8px;color:var(--brand-dark)">مبالغ پیشنهاد مالی</div>
              <label class="block mb-1 text-xs font-semibold">مبلغ نهایی پیشنهاد (خالص) *</label>
              <input type="number" step="0.01" min="0" name="proposal_amount" id="proposal_amount"
                     value="{{ old('proposal_amount', $case->proposal_amount) }}"
                     class="w-full border rounded px-2 py-1.5 mb-2" placeholder="مثلاً 552800">
              <label class="block mb-1 text-xs font-semibold">ارزش افزوده (٪) *</label>
              <input type="number" step="0.01" min="0" max="100" name="vat_percent" id="vat_percent"
                     value="{{ old('vat_percent', $case->vat_percent ?? 0) }}"
                     class="w-full border rounded px-2 py-1.5 mb-2" placeholder="0 یا 10">
              <div class="muted" style="font-size:11px">اگر ترم DDP است معمولاً ۱۰٪؛ CPT/CFR معمولاً ۰. مبلغ ناخالص خودکار محاسبه و ذخیره می‌شود.</div>
            </div>
            <input type="text" name="reason" placeholder="دلیل (برای برد/باخت/توقف الزامی)" class="w-full border rounded px-3 py-2">
            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="is_override" value="1"> Override مدیر</label>
            <x-btn type="submit" size="sm">اعمال</x-btn>
          </form>
          <script>
            (function(){
              const sel = document.getElementById('statusSelect');
              const box = document.getElementById('financialFields');
              function sync(){ box.style.display = sel.value === 'financial_sent' ? 'block' : 'none'; }
              sel.addEventListener('change', sync); sync();
            })();
          </script>
        </x-card>
      </div>
    </div>
  </div>


  {{-- ==========================================================
       تب دوم: فعالیت‌ها (۷۰ درصد فعالیت‌ها راست | ۳۰ درصد گفتگوی داخلی چپ)
       ========================================================== --}}
  <div id="tab-activities" class="case-tab-pane" style="display: none;">
    <div class="case-row-50">
      <div>
        <x-card title="فعالیت‌ها و پیگیری‌ها" :pad="false">
          <div class="act-composer" style="flex-direction:column;align-items:stretch">
            <form method="POST" action="{{ route('cases.activities.store', $case) }}" id="activityForm">@csrf
              <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap">
                <label style="font-size:12px;font-weight:700;display:flex;align-items:center;gap:4px">
                  <input type="radio" name="type" value="note" checked> یادداشت
                </label>
                <label style="font-size:12px;font-weight:700;display:flex;align-items:center;gap:4px">
                  <input type="radio" name="type" value="phone_call_report"> گزارش تماس
                </label>
              </div>
              <textarea name="body" rows="3" required placeholder="متن فعالیت یا گزارش تماس…" class="w-full border rounded px-3 py-2" style="margin-bottom:8px"></textarea>
              <div id="callFields" style="display:none;gap:8px;flex-wrap:wrap;margin-bottom:8px">
                <x-jalali-datetime name="call_datetime" class="border rounded px-2 py-1 text-sm" />
                <select name="call_direction" class="border rounded px-2 py-1 text-sm">
                  <option value="outgoing">خروجی</option>
                  <option value="incoming">ورودی</option>
                </select>
                <input type="number" name="duration_minutes" placeholder="دقیقه" min="0" class="border rounded px-2 py-1 text-sm" style="width:90px">
                <input type="text" name="call_result" placeholder="نتیجه تماس" class="border rounded px-2 py-1 text-sm" style="flex:1">
              </div>
              <x-btn type="submit" size="sm">ثبت</x-btn>
            </form>
          </div>
          <script>
            document.querySelectorAll('#activityForm input[name=type]').forEach(r => {
              r.addEventListener('change', () => {
                document.getElementById('callFields').style.display =
                  document.querySelector('#activityForm input[name=type]:checked').value === 'phone_call_report' ? 'flex' : 'none';
              });
            });
          </script>

          <div class="timeline" style="padding:0 14px 8px">
            @php
              $acts = ($case->activities ?? collect())->whereNull('parent_id')->sortByDesc('created_at');
            @endphp
            @forelse($acts as $activity)
              @php
                $isCall = ($activity->type ?? '') === 'phone_call_report' || ($activity->type ?? '') === 'phone_call';
                $uname = $activity->user?->name ?? 'کاربر';
                $initial = mb_substr($uname, 0, 1);
              @endphp
              <div class="timeline-item">
                <div class="timeline-avatar {{ $isCall ? 'call' : '' }}">{{ $initial }}</div>
                <div class="timeline-content">
                  <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
                    <div style="font-weight:700;font-size:13px;color:var(--brand)">
                      {{ $uname }}
                      @if($isCall)
                        <x-badge tone="ok">تماس {{ ($activity->call_direction ?? '') === 'incoming' ? 'ورودی' : 'خروجی' }}</x-badge>
                      @elseif(($activity->type ?? '') === 'status_change')
                        <x-badge tone="info">تغییر وضعیت</x-badge>
                      @endif
                    </div>
                    <div class="timeline-meta">{{ $activity->created_at ? jdate($activity->created_at)->format('Y/m/d') : '—' }}</div>
                  </div>
                  @if($isCall)
                    <div class="call-card">
                      <div class="call-title">گزارش تماس تلفنی</div>
                      @if($activity->call_datetime)
                        <div class="timeline-meta">زمان: {{ $activity->call_datetime ? jdate($activity->call_datetime)->format('Y/m/d') : '—' }}</div>
                      @endif
                      @if($activity->duration_minutes)
                        <div class="timeline-meta">مدت: {{ $activity->duration_minutes }} دقیقه</div>
                      @endif
                      @if($activity->call_result)
                        <div class="timeline-meta">نتیجه: {{ $activity->call_result }}</div>
                      @endif
                      <div class="timeline-body">{{ $activity->body }}</div>
                    </div>
                  @else
                    <div class="timeline-body">{{ $activity->body }}</div>
                  @endif
                  @if(($activity->children ?? collect())->count())
                    <div style="margin-top:8px;padding-right:12px;border-right:2px solid var(--border-soft)">
                      @foreach($activity->children as $child)
                        <div style="font-size:12px;margin-top:6px">
                          <strong>{{ $child->user?->name }}</strong>: {{ $child->body }}
                          <span class="timeline-meta">{{ optional($child->created_at)->diffForHumans() }}</span>
                        </div>
                      @endforeach
                    </div>
                  @endif
                </div>
              </div>
            @empty
              <div style="padding:24px 0"><x-empty title="هنوز فعالیتی ثبت نشده است" /></div>
            @endforelse
          </div>
        </x-card>
      </div>
      
      <div>
        @if($chatOn)
        <div class="card mb-4">
          <div class="card-h">گفتگوی داخلی تیم (روی پرونده)</div>
          <div class="card-b text-sm">
            <div class="space-y-2 mb-3" style="max-height:280px;overflow:auto">
              @forelse($chatMsgs as $m)
                @php $u = \App\Models\User::find($m->user_id); @endphp
                <div class="border rounded p-2">
                  <div class="text-xs text-gray-500">{{ $u->name ?? ('#'.$m->user_id) }} · {{ $m->created_at ? jdate($m->created_at)->format('Y/m/d') : '—' }}</div>
                  <div>{{ $m->body }}</div>
                </div>
              @empty
                <p class="text-gray-500 text-xs">هنوز پیامی نیست. به‌جای واتساپ اینجا هماهنگ کنید.</p>
              @endforelse
            </div>
            <form method="POST" action="{{ route('cases.chat.store', $case) }}" class="space-y-2">@csrf
              <textarea name="body" rows="2" required class="w-full border rounded px-2 py-1" placeholder="پیام به تیم… با @نام می‌توانید منشن کنید"></textarea>
              <button class="btn btn-primary btn-sm">ارسال</button>
            </form>
          </div>
        </div>
        @endif
      </div>
    </div>
  </div>


  {{-- ==========================================================
       تب سوم: اسناد (۳۰ درصد پیوست‌ها راست | ۳۰ درصد اسناد وسط | ۳۰ درصد خروجی PDF چپ)
       ========================================================== --}}
  <div id="tab-documents" class="case-tab-pane" style="display: none;">

    {{-- ردیف اول: اسناد (راست) + پیوست‌ها (چپ) --}}
    <div class="case-row-50">
      <div>
        <x-card title="اسناد" :pad="false">
          <x-slot:actions>
            <a href="{{ route('documents.create', ['case_id'=>$case->id]) }}" class="text-xs font-semibold" style="color:var(--brand)">جدید</a>
          </x-slot:actions>
          @php
            $caseDocRows = collect();
            foreach (($case->documents ?? collect()) as $doc) {
              $publishedRevs = $doc->revisions->filter(fn ($r) => $r->isPublished())->sortBy('revision_number')->values();
              foreach ($publishedRevs as $i => $rev) {
                $caseDocRows->push(['doc' => $doc, 'rev' => $rev, 'isBase' => $i === 0]);
              }
            }
          @endphp
          @forelse($caseDocRows as $row)
            @php [$doc, $rev, $isBaseRow] = [$row['doc'], $row['rev'], $row['isBase']]; @endphp
            <div class="rel-item" style="display:flex;align-items:center;justify-content:space-between;gap:8px">
              <a href="{{ route('documents.show', ['document' => $doc, 'revision' => $rev->id]) }}" class="block" style="flex:1;min-width:0;{{ $isBaseRow ? '' : 'padding-inline-start:15px' }}">
                <div class="font-semibold" style="color:var(--brand)">{{ $rev->formatted_number }}</div>
                <div class="timeline-meta">{{ $rev->created_at ? jdate($rev->created_at)->format('Y/m/d') : '—' }}</div>
              </a>
              @if($rev->file_path)
                <div style="display:flex;gap:4px;flex-shrink:0">
                  <a href="{{ route('documents.revisions.download', $rev) }}" title="دانلود Word/Excel" class="btn btn-ghost btn-sm btn-icon" onclick="event.stopPropagation()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                  </a>
                  @if($docPdfAvailable)
                    <a href="{{ route('documents.revisions.download-pdf', $rev) }}" title="دانلود PDF" class="btn btn-ghost btn-sm btn-icon" style="font-size:9px;font-weight:800" onclick="event.stopPropagation()">PDF</a>
                  @endif
                </div>
              @endif
            </div>
          @empty
            <div style="padding:16px"><x-empty title="سندی ثبت نشده" /></div>
          @endforelse
        </x-card>
      </div>

      <div>
        <x-card title="پیوست‌ها" :pad="false">
          <div style="padding:12px 14px;border-bottom:1px solid var(--border-soft);background:var(--brand-soft-2)">
            <form method="POST" action="{{ route('cases.attachments.store', $case) }}" enctype="multipart/form-data" class="space-y-2 text-sm">@csrf
              <div class="font-semibold" style="margin-bottom:6px;color:var(--brand-dark)">آپلود فایل</div>
              <input type="file" name="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.xls,.xlsx"
                     class="w-full text-sm" style="font-size:12px">
              <div style="display:flex;gap:8px;align-items:center">
                <input type="text" name="note" placeholder="توضیح اختیاری" class="border rounded px-2 py-1.5" style="flex:1;min-width:0">
                <button type="submit" class="btn btn-primary btn-sm" style="flex-shrink:0">پیوست کردن</button>
              </div>
            </form>
          </div>
          @forelse(($case->attachments ?? collect())->sortByDesc('id') as $att)
            <div class="rel-item" style="display:flex;justify-content:space-between;align-items:center;gap:10px">
              <div style="min-width:0;flex:1">
                <a href="{{ route('attachments.download', $att) }}" class="font-semibold" style="color:var(--brand)">{{ $att->file_name }}</a>
              </div>
              <div style="display:flex;gap:8px;flex-shrink:0;align-items:center">
                <a href="{{ route('attachments.download', $att) }}" class="text-xs font-semibold" style="color:var(--brand)">دانلود</a>
              </div>
            </div>
          @empty
            <div style="padding:16px"><x-empty title="پیوستی ثبت نشده" /></div>
          @endforelse
        </x-card>
      </div>
    </div>

    {{-- ردیف دوم: فقط وقتی خروجی PDF قدیمیِ مبتنی بر قالب فعال است --}}
    @if($pdfOn)
    <div class="case-row-50" style="margin-top:var(--s-4)">
      <div>
        <div class="card">
          <div class="card-h">خروجی PDF از قالب</div>
          <div class="card-b text-sm flex flex-col gap-2">
            <a class="btn btn-primary btn-sm w-full" target="_blank" href="{{ route('cases.pdf', [$case, 'template' => 'FI']) }}">PDF پیشنهاد مالی</a>
            <a class="btn btn-primary btn-sm w-full" target="_blank" href="{{ route('cases.pdf', [$case, 'template' => 'TC']) }}">PDF پیشنهاد فنی</a>
          </div>
        </div>
      </div>
      <div></div>
    </div>
    @endif
  </div>


  {{-- ==========================================================
       تب چهارم: وظایف (عرض ۱۰۰ درصد)
       ========================================================== --}}

  {{-- تب ایمیل‌های لینک‌شده (فاز D) --}}
  <div id="tab-emails" class="case-tab-pane" style="display: none;">
    <x-card title="تایم‌لاین ایمیل" :pad="false">
      <x-slot:actions>
        <a href="{{ route('mail.compose', ['case_id' => $case->id, 'use_case_meta' => 1]) }}" class="text-xs font-semibold" style="color:var(--brand)">ارسال ایمیل</a>
      </x-slot:actions>
      <div style="padding:12px 14px">
        @php $mailTimeline = $mailTimeline ?? collect(); @endphp
        @forelse($mailTimeline as $m)
          <div style="border:1px solid var(--border-soft,#e5e7eb);border-radius:10px;padding:12px;margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;color:#6b7280;margin-bottom:6px">
              <span>
                @if($m->folder && $m->folder->role === 'sent')
                  <strong style="color:#059669">ارسالی</strong>
                @else
                  <strong style="color:#2563eb">دریافتی</strong>
                @endif
                — {{ $m->from_name ?: $m->from_address }}
                <span dir="ltr">{{ $m->from_address }}</span>
              </span>
              <span>{{ $m->date_sent ? jdatetime($m->date_sent) : '—' }}</span>
            </div>
            <div style="font-weight:700;margin-bottom:6px">
              <a href="{{ route('mail.inbox', ['account' => $m->mail_account_id, 'folder' => $m->mail_folder_id, 'msg' => $m->id]) }}">
                {{ $m->subject ?: '(بدون موضوع)' }}
              </a>
            </div>
            <div style="font-size:13px;line-height:1.6;color:#374151;max-height:120px;overflow:hidden">
              @if($m->body_text)
                {{ \Illuminate\Support\Str::limit(strip_tags($m->body_text), 280) }}
              @else
                {{ \Illuminate\Support\Str::limit(strip_tags($m->body_html ?? ''), 280) }}
              @endif
            </div>
          </div>
        @empty
          <div class="text-sm text-gray-500">هنوز ایمیلی به این پرونده لینک نشده است.</div>
        @endforelse
      </div>
    </x-card>
  </div>

  <div id="tab-tasks" class="case-tab-pane" style="display: none;">
    <div class="case-row-50">
      <div>
      <x-card title="وظایف این پرونده" :pad="false">
        @if(!empty($canAssignTask))
        <div style="padding:12px 14px;border-bottom:1px solid var(--border-soft);background:var(--brand-soft-2)">
          <form method="POST" action="{{ route('tasks.store') }}" class="space-y-2 text-sm">@csrf
            <input type="hidden" name="case_id" value="{{ $case->id }}">
            <input type="hidden" name="return_to_case" value="1">
            <div class="font-semibold" style="margin-bottom:6px;color:var(--brand-dark)">ارجاع وظیفه جدید روی این پرونده</div>
            <input name="title" required placeholder="عنوان وظیفه *" class="w-full border rounded px-2 py-1.5">
            <textarea name="description" rows="2" placeholder="توضیح کوتاه (اختیاری)" class="w-full border rounded px-2 py-1.5"></textarea>
            <div class="rfq-grid-2" style="gap:8px">
              <div>
                <label class="text-xs font-semibold">مسئول *</label>
                <select name="assigned_to" required class="w-full border rounded px-2 py-1.5">
                  <option value="">انتخاب کارشناس…</option>
                  @foreach(($assignableUsers ?? []) as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="text-xs font-semibold">سررسید *</label>
                <x-jalali-datetime name="due_at" :required="true" class="w-full border rounded px-2 py-1.5" />
              </div>
            </div>
            <div class="rfq-grid-2" style="gap:8px">
              <div>
                <label class="text-xs font-semibold">اولویت</label>
                <select name="priority" class="w-full border rounded px-2 py-1.5">
                  <option value="medium">متوسط</option>
                  <option value="low">پایین</option>
                  <option value="high">بالا</option>
                  <option value="urgent">فوری</option>
                </select>
              </div>
              <div style="display:flex;align-items:flex-end">
                <button type="submit" class="btn btn-primary btn-sm w-full">ارجاع وظیفه</button>
              </div>
            </div>
          </form>
        </div>
        @endif
        @forelse(($case->tasks ?? collect())->sortByDesc('id') as $task)
          @php
            $done = in_array($task->status, ['done','completed'], true);
            $overdue = $task->due_at && $task->due_at->lt(now()) && !$done;
          @endphp
          <a href="{{ route('tasks.show', $task) }}" class="rel-item block">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
              <div class="font-semibold" style="color:var(--brand)">{{ $task->title }}</div>
              @if($done)
                <x-badge tone="ok">انجام‌شده</x-badge>
              @elseif($overdue)
                <x-badge tone="danger">معوق</x-badge>
              @else
                <x-badge tone="info">{{ $task->status_label ?? $task->status }}</x-badge>
              @endif
            </div>
            <div class="rel-meta">
              {{ $task->assignee?->name ?? 'بدون مسئول' }}
              · اولویت: {{ $task->priority_label ?? $task->priority }}
              @if($task->due_at)
                · سررسید: <span style="{{ $overdue ? 'color:var(--danger);font-weight:700' : '' }}">{{ $task->due_at ? jdate($task->due_at)->format('Y/m/d') : '—' }}</span>
              @endif
            </div>
          </a>
        @empty
          <div style="padding:16px"><x-empty title="هنوز وظیفه‌ای روی این پرونده ارجاع نشده" /></div>
        @endforelse
      </x-card>
      </div>
      <div></div>
    </div>
  </div>

</div>

{{-- اسکریپت ساده برای مدیریت تب‌ها --}}
<script>
  function switchCaseTab(evt, tabId) {
    // مخفی کردن تمام تب‌ها
    const panes = document.querySelectorAll('.case-tab-pane');
    panes.forEach(pane => pane.style.display = 'none');

    // غیرفعال کردن کلاس active از تمام دکمه‌ها
    const tabs = document.querySelectorAll('.case-tab-btn');
    tabs.forEach(tab => tab.classList.remove('active'));

    // نمایش تب انتخاب شده و فعال کردن دکمه آن
    document.getElementById(tabId).style.display = 'block';
    evt.currentTarget.classList.add('active');
  }
</script>
@endsection