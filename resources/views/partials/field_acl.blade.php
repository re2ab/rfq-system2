{{-- Usage: @include('partials.field_acl', ['field' => 'proposal_amount']) then @if($canViewField) ... @endif --}}
@php
  $canViewField = app(\App\Services\FieldAclService::class)->canView($field ?? '');
@endphp
