@props(['type' => 'success', 'message' => null])
@php $msg = $message ?? trim((string) $slot); @endphp
@if($msg !== '')
<div class="rfq-toast-stack">
  <div class="rfq-toast rfq-toast-{{ $type === 'error' ? 'error' : 'success' }}" role="status" data-autohide="4000">{{ $msg }}</div>
</div>
@push('scripts')
<script>
  setTimeout(function () {
    document.querySelectorAll('.rfq-toast[data-autohide]').forEach(function (t) { t.remove(); });
  }, 4000);
</script>
@endpush
@endif
